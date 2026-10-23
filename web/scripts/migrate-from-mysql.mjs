#!/usr/bin/env node
/**
 * One-off cutover: copy the Laravel MySQL data and the storage/app files into
 * Supabase.
 *
 *   npm run migrate:from-mysql                  # rows, then buckets + files
 *   npm run migrate:from-mysql -- --no-files    # rows only (Supabase keys not needed)
 *   npm run migrate:from-mysql -- --files-only  # buckets + files only
 *
 * Needs, in web/.env:
 *   LEGACY_MYSQL_URL           the Laravel database (read only — nothing is written to it)
 *   DIRECT_URL                 Supabase session connection (port 5432), migrations already
 *                              applied with `npm run db:deploy`
 *   SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY
 *
 * Rows are copied table by table in foreign-key order, primary keys included,
 * then every id sequence is moved past the highest copied id. Timestamps are
 * copied as the naive Manila wall-clock values Laravel stored (lib/time.ts
 * relies on that encoding), so nothing shifts by eight hours.
 *
 * It refuses to run into a target that already has rows: re-running after a
 * partial copy means resetting the Supabase database first
 * (`npx prisma migrate reset`), not merging into it.
 */
import { createRequire } from 'node:module';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createClient } from '@supabase/supabase-js';

const require = createRequire(import.meta.url);
const { PrismaClient: TargetClient, Prisma } = require('@prisma/client');
const { PrismaClient: LegacyClient } = require('../node_modules/.prisma/legacy-mysql-client');

const WEB_ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const STORAGE_ROOT = path.join(WEB_ROOT, '..', 'storage', 'app');
const BATCH = 1000;

const args = new Set(process.argv.slice(2));
const copyRows = !args.has('--files-only');
const copyFiles = !args.has('--no-files');

function requireEnv(name) {
  if (!process.env[name]) {
    console.error(`Set ${name} in web/.env first.`);
    process.exit(1);
  }

  return process.env[name];
}

/** Models in an order where every row's foreign keys already exist. */
function copyOrder(models) {
  const byName = new Map(models.map((model) => [model.name, model]));
  const done = new Set();
  const order = [];

  const visit = (model, trail = new Set()) => {
    if (done.has(model.name)) {
      return;
    }

    if (trail.has(model.name)) {
      throw new Error(`Foreign-key cycle through ${model.name}; copy order needs a deferred column.`);
    }

    trail.add(model.name);

    for (const field of model.fields) {
      // A self-reference (comment replies) is satisfied by copying in id order.
      if (field.relationFromFields?.length && field.type !== model.name) {
        visit(byName.get(field.type), trail);
      }
    }

    done.add(model.name);
    order.push(model);
  };

  models.forEach((model) => visit(model));

  return order;
}

function primaryKey(model) {
  return model.primaryKey?.fields ?? model.fields.filter((field) => field.isId).map((field) => field.name);
}

/** Prisma refuses a bare `null` for a Json column on write. */
function prepare(model, row) {
  for (const field of model.fields) {
    if (field.type === 'Json' && row[field.name] === null) {
      row[field.name] = Prisma.DbNull;
    }
  }

  return row;
}

async function copyTables(legacy, target) {
  const models = copyOrder(Prisma.dmmf.datamodel.models);
  const delegate = (client, model) => client[model.name];

  for (const model of models) {
    if ((await delegate(target, model).count()) > 0) {
      throw new Error(`Supabase table ${model.name} already has rows — reset it before copying.`);
    }
  }

  for (const model of models) {
    const orderBy = primaryKey(model).map((field) => ({ [field]: 'asc' }));
    let copied = 0;

    for (let skip = 0; ; skip += BATCH) {
      const rows = await delegate(legacy, model).findMany({ orderBy, skip, take: BATCH });

      if (rows.length === 0) {
        break;
      }

      await delegate(target, model).createMany({ data: rows.map((row) => prepare(model, row)) });
      copied += rows.length;
    }

    console.log(`  ${model.name.padEnd(28)} ${copied}`);
  }

  // Explicit ids were inserted, so each sequence still sits at 1.
  for (const model of models) {
    const idField = model.fields.find((field) => field.isId && field.default?.name === 'autoincrement');

    if (idField) {
      await target.$executeRawUnsafe(
        `SELECT setval(pg_get_serial_sequence('"${model.name}"', '${idField.name}'), COALESCE(MAX("${idField.name}"), 1), MAX("${idField.name}") IS NOT NULL) FROM "${model.name}"`,
      );
    }
  }
}

async function ensureBuckets(supabase) {
  for (const [name, isPublic] of [
    ['private', false],
    ['public', true],
  ]) {
    const { error } = await supabase.storage.createBucket(name, { public: isPublic });

    if (error && !/already exists/i.test(error.message)) {
      throw new Error(`Could not create bucket ${name}: ${error.message}`);
    }
  }
}

const CONTENT_TYPES = {
  png: 'image/png',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  webp: 'image/webp',
  gif: 'image/gif',
  svg: 'image/svg+xml',
  pdf: 'application/pdf',
  doc: 'application/msword',
  docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
};

async function* walk(directory) {
  let entries;

  try {
    entries = await readdir(directory, { withFileTypes: true });
  } catch {
    return;
  }

  for (const entry of entries) {
    const absolute = path.join(directory, entry.name);

    if (entry.isDirectory()) {
      yield* walk(absolute);
    } else if (entry.isFile() && !entry.name.startsWith('.')) {
      yield absolute;
    }
  }
}

async function copyStorage(supabase) {
  for (const disk of ['private', 'public']) {
    const root = path.join(STORAGE_ROOT, disk);
    let count = 0;

    for await (const absolute of walk(root)) {
      const relative = path.relative(root, absolute).split(path.sep).join('/');
      const extension = path.extname(absolute).slice(1).toLowerCase();
      const { error } = await supabase.storage.from(disk).upload(relative, await readFile(absolute), {
        contentType: CONTENT_TYPES[extension] ?? 'application/octet-stream',
        upsert: true,
      });

      if (error) {
        throw new Error(`Upload of ${disk}/${relative} failed: ${error.message}`);
      }

      count += 1;
    }

    console.log(`  ${disk.padEnd(28)} ${count} files`);
  }
}

async function main() {
  try {
    process.loadEnvFile(path.join(WEB_ROOT, '.env'));
  } catch {
    // No .env: everything must already be in the environment.
  }

  if (copyRows) {
    const legacy = new LegacyClient({ datasourceUrl: requireEnv('LEGACY_MYSQL_URL') });
    const target = new TargetClient({ datasourceUrl: requireEnv('DIRECT_URL') });

    try {
      console.log('Rows');
      await copyTables(legacy, target);
    } finally {
      await Promise.all([legacy.$disconnect(), target.$disconnect()]);
    }
  }

  if (copyFiles) {
    const supabase = createClient(requireEnv('SUPABASE_URL'), requireEnv('SUPABASE_SERVICE_ROLE_KEY'), {
      auth: { persistSession: false, autoRefreshToken: false },
    });

    console.log('Buckets');
    await ensureBuckets(supabase);

    console.log('Files');
    await copyStorage(supabase);
  }

  console.log('Done.');
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
});
