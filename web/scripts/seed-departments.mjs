#!/usr/bin/env node
/**
 * Puts back the municipal offices internal requests are routed between.
 *
 *   node --env-file=.env scripts/seed-departments.mjs
 *
 * The list is Laravel's `DepartmentSeeder` (also used throughout
 * TESTING-INTERNAL-PROCESS.md). The Supabase database only ever received the
 * Tourism Office, which `seed-tourism.mjs` renamed to CTCAO, so the filing
 * wizard's "First Department Route" offered nothing but the filer's own office.
 *
 * Idempotent: matches on code, refreshes the name and switches the office back
 * on. CTCAO (formerly TRSM) is left as it is.
 */
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { PrismaClient } = require('@prisma/client');

const DEPARTMENTS = [
  ['OM', 'Office of the Mayor'],
  ['BO', 'Municipal Budget Office'],
  ['ACC', 'Municipal Accounting Office'],
  ['BAC', 'Bids and Awards Committee'],
  ['GSO', 'General Services Office'],
  ['TRSY', "Municipal Treasurer's Office"],
  ['HRMO', 'Human Resource Management Office'],
  ['ENG', 'Municipal Engineering Office'],
  ['MHO', 'Municipal Health Office'],
  ['AM', 'Action Mamamayan'],
];

const db = new PrismaClient();
/** Naive Manila wall-clock, the encoding every timestamp column uses. */
const now = () => new Date(Date.now() + 8 * 60 * 60_000);

async function main() {
  for (const [code, name] of DEPARTMENTS) {
    const existing = await db.departments.findFirst({ where: { code } });

    if (existing) {
      await db.departments.update({
        where: { id: existing.id },
        data: { name, is_active: true, updated_at: now() },
      });
      console.log(`Office: kept ${code} "${name}"`);
    } else {
      await db.departments.create({
        data: { code, name, is_active: true, created_at: now(), updated_at: now() },
      });
      console.log(`Office: added ${code} "${name}"`);
    }
  }

  console.log('Done.');
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
