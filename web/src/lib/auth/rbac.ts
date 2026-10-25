import { db, id, toId } from '@/lib/db';

/**
 * Roles and permissions, read out of Spatie-shaped tables (seeded by
 * `scripts/seed-roles.mjs`).
 *
 * Roles: `staff`, `Supervisor`, `super_admin`, and `Clerk` — the single
 * account of an office that is one person (Action Mamamayan): it receives and
 * decides on internal requests, so it holds staff and supervisor permissions
 * for those, and nothing for citizen requests. Guests hold no role.
 */
export const ROLE_STAFF = 'staff';
export const ROLE_SUPERVISOR = 'Supervisor';
export const ROLE_SUPER_ADMIN = 'super_admin';
export const ROLE_CLERK = 'Clerk';

/** Every permission the seeder defines. Used to type-check `can()` call sites. */
export const PERMISSIONS = [
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
] as const;

export type Permission = (typeof PERMISSIONS)[number];

export const MODEL_TYPE = 'App\\Models\\User';

export type Grants = {
  roles: string[];
  permissions: string[];
};

/**
 * A user's effective grants: roles, the permissions those roles carry, and any
 * permission granted to the user directly (`model_has_permissions`) — the same
 * union Spatie's `HasRoles` resolves.
 */
export async function grantsFor(userId: number): Promise<Grants> {
  const [roleRows, directRows] = await Promise.all([
    db.model_has_roles.findMany({
      where: { model_id: id(userId), model_type: MODEL_TYPE },
      select: {
        roles: {
          select: {
            name: true,
            role_has_permissions: { select: { permissions: { select: { name: true } } } },
          },
        },
      },
    }),
    db.model_has_permissions.findMany({
      where: { model_id: id(userId), model_type: MODEL_TYPE },
      select: { permissions: { select: { name: true } } },
    }),
  ]);

  const roles = roleRows.map((row) => row.roles.name);
  const permissions = new Set<string>();

  for (const row of roleRows) {
    for (const link of row.roles.role_has_permissions) {
      permissions.add(link.permissions.name);
    }
  }

  for (const row of directRows) {
    permissions.add(row.permissions.name);
  }

  return { roles, permissions: [...permissions] };
}

export type Recipient = { id: number; name: string; email: string; department_id: number | null };

/**
 * Everyone who holds a permission, by role or granted directly — Spatie's
 * `User::permission(…)` scope.
 *
 * The internal-request fan-outs are written against permissions, not roles, on
 * purpose: a named soft-copy recipient may be plain staff, and an office may
 * grant `act on internal requests` to someone who is not a supervisor.
 */
export async function usersWithPermission(
  permission: string,
  departmentId: number | null = null,
): Promise<Recipient[]> {
  const [viaRole, direct] = await Promise.all([
    db.model_has_roles.findMany({
      where: {
        model_type: MODEL_TYPE,
        roles: { role_has_permissions: { some: { permissions: { name: permission } } } },
      },
      select: { model_id: true },
    }),
    db.model_has_permissions.findMany({
      where: { model_type: MODEL_TYPE, permissions: { name: permission } },
      select: { model_id: true },
    }),
  ]);

  const ids = [...new Set([...viaRole, ...direct].map((link) => link.model_id))];

  if (ids.length === 0) {
    return [];
  }

  const users = await db.users.findMany({
    where: {
      id: { in: ids },
      deleted_at: null,
      is_active: true,
      ...(departmentId === null ? {} : { department_id: id(departmentId) }),
    },
    orderBy: { name: 'asc' },
    select: { id: true, name: true, email: true, department_id: true },
  });

  return users.map((user) => ({
    id: toId(user.id),
    name: user.name,
    email: user.email,
    department_id: user.department_id === null ? null : toId(user.department_id),
  }));
}

/** Everyone at an office holding a given role — used for notification fan-out. */
export async function usersWithRole(
  role: string,
  departmentId: number | null = null,
): Promise<Array<{ id: number; name: string; email: string; department_id: number | null }>> {
  const links = await db.model_has_roles.findMany({
    where: { model_type: MODEL_TYPE, roles: { name: role } },
    select: { model_id: true },
  });

  if (links.length === 0) {
    return [];
  }

  const users = await db.users.findMany({
    where: {
      id: { in: links.map((link) => link.model_id) },
      deleted_at: null,
      is_active: true,
      ...(departmentId === null ? {} : { department_id: id(departmentId) }),
    },
    select: { id: true, name: true, email: true, department_id: true },
  });

  return users.map((user) => ({
    id: toId(user.id),
    name: user.name,
    email: user.email,
    department_id: user.department_id === null ? null : toId(user.department_id),
  }));
}
