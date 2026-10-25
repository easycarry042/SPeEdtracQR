#!/usr/bin/env node
/**
 * Action Mamamayan: the office every internal request goes to first, to be
 * validated and confirmed before the Office of the Mayor (see
 * `src/lib/domain/internal-route-plan.ts`).
 *
 *   npm run db:seed:am
 *
 * Run `seed-roles.mjs` first (it defines the Clerk role). Idempotent: creates
 * or reactivates the office (code AM) and its one placeholder account — the
 * office is a single person, so one Clerk account both receives and decides.
 * New accounts get the demo password `staff1234`; an existing one keeps its
 * password, and its office and role are brought in line.
 *
 * The Clerk approves with an e-signature, registered on their Profile page.
 */
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');

const WEB_ROOT = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
try {
  process.loadEnvFile(path.join(WEB_ROOT, '.env'));
} catch {}

const MODEL_TYPE = 'App\\Models\\User';
const OFFICE = { code: 'AM', name: 'Action Mamamayan' };
const CLERK = { name: 'Action Mamamayan Clerk', email: 'clerk.am@speedtraqr.com' };

const db = new PrismaClient();
/** Naive Manila wall-clock, the encoding every timestamp column uses (lib/time.ts `now()`). */
const now = () => new Date(Date.now() + 8 * 60 * 60_000);

async function main() {
  const existingOffice = await db.departments.findFirst({ where: { code: OFFICE.code } });
  const office = existingOffice
    ? await db.departments.update({
        where: { id: existingOffice.id },
        data: { name: OFFICE.name, is_active: true, updated_at: now() },
      })
    : await db.departments.create({
        data: { ...OFFICE, is_active: true, created_at: now(), updated_at: now() },
      });
  console.log(`Office: ${existingOffice ? 'kept' : 'added'} ${office.code} ${office.name}`);

  const role = await db.roles.findFirst({ where: { name: 'Clerk', guard_name: 'web' } });

  if (!role) {
    throw new Error('The Clerk role is missing — run `node scripts/seed-roles.mjs` first.');
  }

  const existing = await db.users.findFirst({
    where: { email: { equals: CLERK.email, mode: 'insensitive' } },
  });

  const user = existing
    ? await db.users.update({
        where: { id: existing.id },
        data: { department_id: office.id, is_active: true, deleted_at: null, updated_at: now() },
      })
    : await db.users.create({
        data: {
          name: CLERK.name,
          email: CLERK.email,
          password: await bcrypt.hash('staff1234', 12),
          department_id: office.id,
          is_active: true,
          email_verified_at: now(),
          created_at: now(),
          updated_at: now(),
        },
      });

  await db.model_has_roles.deleteMany({ where: { model_type: MODEL_TYPE, model_id: user.id } });
  await db.model_has_roles.create({ data: { role_id: role.id, model_type: MODEL_TYPE, model_id: user.id } });

  console.log(`Account: ${existing ? 'kept' : 'added'} ${CLERK.name} <${CLERK.email}> as Clerk`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
