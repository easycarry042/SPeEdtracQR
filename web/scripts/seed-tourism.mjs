#!/usr/bin/env node
/**
 * Sets the system up for the City Tourism, Culture and Arts Office (CTCAO),
 * from the client's REQUEST ASSIGNMENT workflow and paper form CTOUR-008-0.
 *
 *   npm run db:seed:tourism
 *
 * Needs DATABASE_URL (or DIRECT_URL) pointing at a database with the
 * migrations applied and the roles in place (`npm run migrate:from-mysql`).
 * Idempotent: run it again and it converges on the same state.
 *
 * What it does:
 *   1. Renames the Tourism Office (code TRSM) to CTCAO, or creates it.
 *   2. Adds the facilities and items CTCAO lends (counts are placeholders —
 *      correct them on the Resources page).
 *   3. Creates CTCAO's placeholder accounts: 3 supervisors (the workflow's
 *      "S/Admin") and 8 staff, password `staff1234`. Existing accounts keep
 *      their password.
 *   4. Adds CTCAO's request types with each one's usual handlers (the
 *      workflow's assignment table) and its extra question.
 *   5. Switches every other request type off, so the public form offers only
 *      CTCAO's services. Reversible per type on the Request Types page.
 *
 * The people are placeholders standing in for the real staff on the
 * workflow (Ana Cruz for Jhacky Ong, Bea Santiago for Susi Miranda, Mark Dizon
 * for Sergio Machado, Joy Navarro for Jezther Ygnacio, Kevin Ramos for Michael
 * Tabuyan); swap in real accounts on the Users page when the office has them.
 */
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { PrismaClient } = require('@prisma/client');
const bcrypt = require('bcryptjs');

const MODEL_TYPE = 'App\\Models\\User';
const PLACEHOLDER_PASSWORD = 'staff1234';

const OFFICE = { code: 'CTCAO', name: 'City Tourism, Culture and Arts Office', legacyCode: 'TRSM' };

/** [name, kind, total units (equipment), location] */
const RESOURCES = [
  ['City Plaza', 'facility', null, 'City Plaza, Poblacion'],
  ['Gateway Park', 'facility', null, 'Gateway Park'],
  ['Spider Banner', 'equipment', 10, 'CTCAO stockroom'],
  ['Philippine Flag', 'equipment', 20, 'CTCAO stockroom'],
  ['San Pedro Seal Flag', 'equipment', 10, 'CTCAO stockroom'],
];

/** [name, email, role] — placeholders, like the other offices' test accounts. */
const PEOPLE = [
  ['Maria Santos', 'maria.santos@speedtraqr.com', 'Supervisor'],
  ['Carmela Torres', 'carmela.torres@speedtraqr.com', 'Supervisor'],
  ['Dennis Aquino', 'dennis.aquino@speedtraqr.com', 'Supervisor'],
  ['Ana Cruz', 'ana.cruz@speedtraqr.com', 'staff'],
  ['Bea Santiago', 'bea.santiago@speedtraqr.com', 'staff'],
  ['Mark Dizon', 'mark.dizon@speedtraqr.com', 'staff'],
  ['Joy Navarro', 'joy.navarro@speedtraqr.com', 'staff'],
  ['Kevin Ramos', 'kevin.ramos@speedtraqr.com', 'staff'],
  ['Tess Gomez', 'tess.gomez@speedtraqr.com', 'staff'],
  ['Leo Pascual', 'leo.pascual@speedtraqr.com', 'staff'],
  ['Ella Manalo', 'ella.manalo@speedtraqr.com', 'staff'],
];

/**
 * CTCAO's catalog, in the paper form's order.
 * [name, kind, resource, extra question, description, usual handlers (emails)]
 */
const TYPES = [
  ['City Plaza Reservation', 'booking', 'City Plaza', null,
    'Schedule the use of City Plaza.', ['ana.cruz', 'bea.santiago']],
  ['Gateway Park Reservation', 'booking', 'Gateway Park', null,
    'Schedule the use of Gateway Park.', ['ana.cruz', 'bea.santiago']],
  ['Other Venue Request', 'service', null, 'Which venue?',
    'Schedule the use of a venue not listed.', ['ana.cruz', 'bea.santiago']],
  ['Events Set-up', 'service', null, 'Venue of the event',
    'Set-up for an event at the venue you name.', ['mark.dizon']],
  ['Spider Banner Borrowing', 'equipment', 'Spider Banner', null,
    'Borrow a spider banner; give the date it will be returned.', ['ana.cruz']],
  ['Philippine Flag Borrowing', 'equipment', 'Philippine Flag', null,
    'Borrow a Philippine flag; give the date it will be returned.', ['ana.cruz']],
  ['Philippine Flag Requisition', 'service', null, 'Size of the flag',
    'Request a Philippine flag to keep (not returned).', ['ana.cruz']],
  ['San Pedro Seal Flag Borrowing', 'equipment', 'San Pedro Seal Flag', null,
    'Borrow a San Pedro seal flag; give the date it will be returned.', ['ana.cruz']],
  ['Lei Making', 'service', null, null,
    'Leis prepared for ceremonies, visits and inaugurations.', ['mark.dizon']],
  ['Layout Request', 'service', null, 'Size (e.g. 3 x 6 ft tarpaulin)',
    'A layout or design for a tarpaulin, poster or program.', ['joy.navarro']],
  ['Social Media Posting', 'service', null, null,
    'Post an event or announcement on the office’s social media.', ['kevin.ramos']],
  ['Program Assistance', 'service', null, 'Name of the program or event',
    'Staff assistance for a program or event.', ['mark.dizon']],
];

const db = new PrismaClient();
/** Naive Manila wall-clock, the encoding every timestamp column uses (lib/time.ts `now()`). */
const now = () => new Date(Date.now() + 8 * 60 * 60_000);

async function office() {
  const existing = await db.departments.findFirst({
    where: { code: { in: [OFFICE.code, OFFICE.legacyCode] } },
    orderBy: { id: 'asc' },
  });

  if (existing) {
    const row = await db.departments.update({
      where: { id: existing.id },
      data: { code: OFFICE.code, name: OFFICE.name, is_active: true, updated_at: now() },
    });
    console.log(`Office: ${existing.code} "${existing.name}" → ${row.code} "${row.name}"`);

    return row;
  }

  const row = await db.departments.create({
    data: { code: OFFICE.code, name: OFFICE.name, is_active: true, created_at: now(), updated_at: now() },
  });
  console.log(`Office: created ${row.code} "${row.name}"`);

  return row;
}

async function resources() {
  const byName = new Map();

  for (const [index, [name, kind, total, location]] of RESOURCES.entries()) {
    const existing = await db.resources.findFirst({ where: { name } });
    const data = { kind, location, is_active: true, sort_order: 300 + index, updated_at: now() };

    const row = existing
      ? await db.resources.update({ where: { id: existing.id }, data })
      : await db.resources.create({
          data: { name, ...data, total_quantity: total, created_at: now() },
        });

    byName.set(name, row);
    console.log(`Resource: ${existing ? 'kept' : 'added'} ${name}${total ? ` (${row.total_quantity} units)` : ''}`);
  }

  return byName;
}

async function people(department) {
  const roles = new Map((await db.roles.findMany()).map((role) => [role.name, role]));
  const byHandle = new Map();
  const hash = await bcrypt.hash(PLACEHOLDER_PASSWORD, 12);

  for (const [name, email, roleName] of PEOPLE) {
    const role = roles.get(roleName);

    if (!role) {
      throw new Error(`Role ${roleName} is missing — copy the roles in first (npm run migrate:from-mysql).`);
    }

    const existing = await db.users.findFirst({ where: { email: { equals: email, mode: 'insensitive' } } });

    const user = existing
      ? await db.users.update({
          where: { id: existing.id },
          data: { department_id: department.id, is_active: true, deleted_at: null, updated_at: now() },
        })
      : await db.users.create({
          data: {
            name,
            email,
            password: hash,
            department_id: department.id,
            is_active: true,
            email_verified_at: now(),
            created_at: now(),
            updated_at: now(),
          },
        });

    // One role per person, as the Users page keeps it.
    await db.model_has_roles.deleteMany({ where: { model_type: MODEL_TYPE, model_id: user.id } });
    await db.model_has_roles.create({ data: { role_id: role.id, model_type: MODEL_TYPE, model_id: user.id } });

    byHandle.set(email.split('@')[0], user);
    console.log(`Account: ${existing ? 'kept' : 'added'} ${name} <${email}> as ${roleName}`);
  }

  return byHandle;
}

async function requestTypes(department, resourcesByName, peopleByHandle) {
  for (const [index, [name, kind, resourceName, detailsLabel, description, handlers]] of TYPES.entries()) {
    const resource = resourceName ? resourcesByName.get(resourceName) : null;
    const data = {
      kind,
      department_id: department.id,
      resource_id: resource ? resource.id : null,
      details_label: detailsLabel,
      description,
      is_active: true,
      sort_order: index + 1,
      updated_at: now(),
    };

    const existing = await db.request_types.findFirst({ where: { name } });
    const type = existing
      ? await db.request_types.update({ where: { id: existing.id }, data })
      : await db.request_types.create({ data: { name, ...data, created_at: now() } });

    await db.request_type_handlers.deleteMany({ where: { request_type_id: type.id } });
    await db.request_type_handlers.createMany({
      data: handlers.map((handle, order) => ({
        request_type_id: type.id,
        user_id: peopleByHandle.get(handle).id,
        sort_order: order,
        created_at: now(),
        updated_at: now(),
      })),
    });

    const names = handlers.map((handle) => peopleByHandle.get(handle).name).join(' / ');
    console.log(`Request type: ${existing ? 'updated' : 'added'} ${name} → ${names}`);
  }

  // Tourism only, for now: everything else comes off the public form.
  const others = await db.request_types.updateMany({
    where: { name: { notIn: TYPES.map(([name]) => name) }, is_active: true },
    data: { is_active: false, updated_at: now() },
  });
  console.log(`Switched off ${others.count} other request type(s) (Request Types page turns them back on).`);
}

async function main() {
  const department = await office();
  const resourcesByName = await resources();
  const peopleByHandle = await people(department);
  await requestTypes(department, resourcesByName, peopleByHandle);
  console.log('Done.');
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
