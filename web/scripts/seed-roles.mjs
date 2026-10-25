#!/usr/bin/env node
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

const db = new PrismaClient();
const now = () => new Date(Date.now() + 8 * 60 * 60_000);
const MODEL_TYPE = 'App\\Models\\User';

const PERMISSIONS = [
  'create documents',
  'create internal requests',
  'act on internal requests',
  'scan documents',
  'advance documents',
  'assign documents',
  'accept documents',
  'manage bookings',
  'manage inventory',
  'view reports',
  'manage users',
  'view all documents',
  'delete documents',
  'manage system',
];

const ROLES = {
  staff: [
    'scan documents',
    'advance documents',
    'accept documents',
    'manage bookings',
    'manage inventory',
    'create internal requests',
  ],
  Supervisor: [
    'assign documents',
    'view reports',
    'view all documents',
    'create internal requests',
    'act on internal requests',
    'manage inventory',
  ],
  // Clerk: Action Mamamayan's one-person account. Every internal request is
  // validated there first, so it receives the paper (staff), decides on it
  // (supervisor) and can file its own — and has nothing for citizen requests.
  Clerk: [
    'scan documents',
    'create internal requests',
    'act on internal requests',
  ],
  super_admin: PERMISSIONS,
};

async function main() {
  console.log('Seeding permissions...');
  const permMap = new Map();
  for (const name of PERMISSIONS) {
    let perm = await db.permissions.findFirst({ where: { name, guard_name: 'web' } });
    if (!perm) {
      perm = await db.permissions.create({
        data: { name, guard_name: 'web', created_at: now(), updated_at: now() },
      });
    }
    permMap.set(name, perm);
  }

  console.log('Seeding roles & role_has_permissions...');
  const roleMap = new Map();
  for (const [roleName, perms] of Object.entries(ROLES)) {
    let role = await db.roles.findFirst({ where: { name: roleName, guard_name: 'web' } });
    if (!role) {
      role = await db.roles.create({
        data: { name: roleName, guard_name: 'web', created_at: now(), updated_at: now() },
      });
    }
    roleMap.set(roleName, role);

    for (const pName of perms) {
      const p = permMap.get(pName);
      if (p) {
        const link = await db.role_has_permissions.findFirst({
          where: { permission_id: p.id, role_id: role.id },
        });
        if (!link) {
          await db.role_has_permissions.create({
            data: { permission_id: p.id, role_id: role.id },
          });
        }
      }
    }
  }

  console.log('Seeding default Super Admin...');
  const adminEmail = 'admin@speedtraqr.com';
  let admin = await db.users.findFirst({ where: { email: adminEmail } });
  if (!admin) {
    const passwordHash = await bcrypt.hash('password123', 12);
    admin = await db.users.create({
      data: {
        name: 'Super Admin',
        email: adminEmail,
        password: passwordHash,
        is_active: true,
        created_at: now(),
        updated_at: now(),
      },
    });
  }

  const superAdminRole = roleMap.get('super_admin');
  const roleLink = await db.model_has_roles.findFirst({
    where: { role_id: superAdminRole.id, model_id: admin.id, model_type: MODEL_TYPE },
  });
  if (!roleLink) {
    await db.model_has_roles.create({
      data: {
        role_id: superAdminRole.id,
        model_id: admin.id,
        model_type: MODEL_TYPE,
      },
    });
  }

  console.log('Roles, permissions, and super admin seeded successfully.');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
