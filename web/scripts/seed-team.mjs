#!/usr/bin/env node
/**
 * Staffs every municipal office so no internal request route can dead-end.
 *
 *   node --env-file=.env scripts/seed-team.mjs
 *
 * Port of Laravel's `TeamUsersSeeder`. Only a Supervisor may approve, return or
 * deny an internal request, so an office without one is a dead end: a request
 * routed there arrives and can never be acted on.
 *
 * Run `seed-departments.mjs` first. `TRSM` (the old Tourism Office) is now
 * CTCAO, so those accounts are attached to CTCAO.
 *
 * Idempotent. New accounts get the demo password `staff1234` (the super admin
 * gets ADMIN_PASSWORD, and is skipped when that is unset). Existing accounts
 * keep their password; their office and role are brought in line.
 */
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');

const MODEL_TYPE = 'App\\Models\\User';
const DEMO_PASSWORD = 'staff1234';
/** Office codes renamed since the Laravel seeder was written. */
const RENAMED = { TRSM: 'CTCAO' };

/** [name, email, role, office code (null = org-wide)] */
const TEAM = [
  ['Super Admin', 'admin@speedtraqr.com', 'super_admin', null],

  // Department heads — one per office, so no route can dead-end.
  ['Ramon Bautista', 'om.head@speedtraqr.com', 'Supervisor', 'OM'],
  ['Victor Salazar', 'cadm.head@speedtraqr.com', 'Supervisor', 'CADM'],
  ['Elena Marquez', 'budget.head@speedtraqr.com', 'Supervisor', 'BO'],
  ['Teresa Lim', 'accounting.head@speedtraqr.com', 'Supervisor', 'ACC'],
  ['Noel Aguilar', 'bac.head@speedtraqr.com', 'Supervisor', 'BAC'],
  ['Grace Villanueva', 'treasury.head@speedtraqr.com', 'Supervisor', 'TRSY'],
  ['Arnel Domingo', 'engineering.head@speedtraqr.com', 'Supervisor', 'ENG'],
  ['Divina Ocampo', 'health.head@speedtraqr.com', 'Supervisor', 'MHO'],
  ['Maria Santos', 'maria.santos@speedtraqr.com', 'Supervisor', 'TRSM'],
  ['Jose Reyes', 'jose.reyes@speedtraqr.com', 'Supervisor', 'GSO'],
  ['Carlos Dela Cruz', 'carlos.delacruz@speedtraqr.com', 'Supervisor', 'HRMO'],

  // Staff — the people a Supervisor assigns citizen requests to.
  ['Ana Cruz', 'ana.cruz@speedtraqr.com', 'staff', 'TRSM'],
  ['Liza Reyes', 'liza.reyes@speedtraqr.com', 'staff', 'OM'],
  ['Paolo Mendoza', 'paolo.mendoza@speedtraqr.com', 'staff', 'GSO'],
  ['Rosa Ilagan', 'rosa.ilagan@speedtraqr.com', 'staff', 'ENG'],
];

const db = new PrismaClient();
/** Naive Manila wall-clock, the encoding every timestamp column uses. */
const now = () => new Date(Date.now() + 8 * 60 * 60_000);

async function main() {
  const roles = new Map((await db.roles.findMany()).map((role) => [role.name, role]));
  const offices = new Map(
    (await db.departments.findMany({ select: { id: true, code: true } })).map((row) => [row.code, row.id]),
  );
  const demoHash = await bcrypt.hash(DEMO_PASSWORD, 12);

  for (const [name, email, roleName, rawCode] of TEAM) {
    const role = roles.get(roleName);

    if (!role) {
      throw new Error(`Role ${roleName} is missing — copy the roles in first.`);
    }

    const code = rawCode === null ? null : (RENAMED[rawCode] ?? rawCode);
    const departmentId = code === null ? null : offices.get(code);

    if (code !== null && departmentId === undefined) {
      throw new Error(`Office ${code} is missing — run seed-departments.mjs first.`);
    }

    const existing = await db.users.findFirst({
      where: { email: { equals: email, mode: 'insensitive' } },
    });

    let user;

    if (existing) {
      user = await db.users.update({
        where: { id: existing.id },
        data: { department_id: departmentId, is_active: true, deleted_at: null, updated_at: now() },
      });
    } else {
      let hash = demoHash;

      if (roleName === 'super_admin') {
        if (!process.env.ADMIN_PASSWORD) {
          console.warn(`Skipped ${email}: set ADMIN_PASSWORD to create the super admin.`);
          continue;
        }
        hash = await bcrypt.hash(process.env.ADMIN_PASSWORD, 12);
      }

      user = await db.users.create({
        data: {
          name,
          email,
          password: hash,
          department_id: departmentId,
          is_active: true,
          email_verified_at: now(),
          created_at: now(),
          updated_at: now(),
        },
      });
    }

    // One role per person, as the Users page keeps it.
    await db.model_has_roles.deleteMany({ where: { model_type: MODEL_TYPE, model_id: user.id } });
    await db.model_has_roles.create({ data: { role_id: role.id, model_type: MODEL_TYPE, model_id: user.id } });

    console.log(`${existing ? 'kept ' : 'added'}  ${roleName.padEnd(12)}${(code ?? 'org-wide').padEnd(10)}${email}`);
  }

  // An office with no Supervisor cannot act on an internal request — say so.
  const supervisor = roles.get('Supervisor');
  const heads = await db.model_has_roles.findMany({
    where: { model_type: MODEL_TYPE, role_id: supervisor.id },
    select: { model_id: true },
  });
  const staffed = new Set(
    (
      await db.users.findMany({
        where: { id: { in: heads.map((h) => h.model_id) }, is_active: true, deleted_at: null },
        select: { department_id: true },
      })
    ).map((u) => String(u.department_id)),
  );
  const orphans = [...offices.entries()].filter(([, officeId]) => !staffed.has(String(officeId)));

  console.log(
    orphans.length
      ? `Offices with NO Supervisor (requests routed there will stall): ${orphans.map(([c]) => c).join(', ')}`
      : 'Every office has a Supervisor — no route can dead-end.',
  );
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
