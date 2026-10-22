import { db, id, toId } from '@/lib/db';
import { MODEL_TYPE, ROLE_STAFF, ROLE_SUPERVISOR } from '@/lib/auth/rbac';
import type { Actor } from '@/lib/auth/actor';

/**
 * What the internal-request filing form needs.
 *
 * Port of `App\Support\InternalRequestForm`. The people who can receive a soft
 * copy are fetched per office when one is picked, not shipped with the form: it
 * opens from the header on every staff page, so it must not carry the whole
 * staff list.
 */
export type FormDepartment = { id: number; name: string; code: string };

export type FormRecipient = { id: number; name: string; role: string };

/** Filing needs the permission and an office to file from. */
export function canFile(actor: Actor | null): boolean {
  return actor !== null && actor.can('create internal requests') && actor.departmentId !== null;
}

/** The offices a request can be routed to first. */
export async function activeDepartments(): Promise<FormDepartment[]> {
  const rows = await db.departments.findMany({
    where: { is_active: true },
    orderBy: { name: 'asc' },
    select: { id: true, name: true, code: true },
  });

  return rows.map((row) => ({ id: toId(row.id), name: row.name, code: row.code }));
}

const RECEIVING_ROLES = [ROLE_STAFF, ROLE_SUPERVISOR];

/**
 * Who can be named to receive a soft copy at each office: its active staff and
 * supervisors, keyed by office id.
 */
export async function recipients(
  departmentIds: number[],
): Promise<Record<number, FormRecipient[]>> {
  if (departmentIds.length === 0) {
    return {};
  }

  // Spatie's pivot carries no foreign key to `users`, so the roles are read
  // separately rather than joined — Prisma has no relation to follow.
  const links = await db.model_has_roles.findMany({
    where: { model_type: MODEL_TYPE, roles: { name: { in: RECEIVING_ROLES } } },
    select: { model_id: true, roles: { select: { name: true } } },
  });

  if (links.length === 0) {
    return {};
  }

  const rolesByUser = new Map<string, string[]>();

  for (const link of links) {
    const key = String(link.model_id);
    rolesByUser.set(key, [...(rolesByUser.get(key) ?? []), link.roles.name]);
  }

  const users = await db.users.findMany({
    where: {
      id: { in: [...new Set(links.map((link) => link.model_id))] },
      department_id: { in: departmentIds.map(id) },
      is_active: true,
      deleted_at: null,
    },
    orderBy: { name: 'asc' },
    select: { id: true, name: true, department_id: true },
  });

  const grouped: Record<number, FormRecipient[]> = {};

  for (const user of users) {
    if (user.department_id === null) {
      continue;
    }

    const office = toId(user.department_id);
    const roles = rolesByUser.get(String(user.id)) ?? [];

    grouped[office] = [
      ...(grouped[office] ?? []),
      {
        id: toId(user.id),
        name: user.name,
        role: roles.includes(ROLE_SUPERVISOR) ? 'Supervisor' : 'Staff',
      },
    ];
  }

  return grouped;
}

/** Whether this person may be named to receive a soft copy at that office. */
export async function isRecipientAt(userId: number, departmentId: number): Promise<boolean> {
  const [user, role] = await Promise.all([
    db.users.findFirst({
      where: {
        id: id(userId),
        department_id: id(departmentId),
        is_active: true,
        deleted_at: null,
      },
      select: { id: true },
    }),
    db.model_has_roles.findFirst({
      where: {
        model_type: MODEL_TYPE,
        model_id: id(userId),
        roles: { name: { in: RECEIVING_ROLES } },
      },
      select: { model_id: true },
    }),
  ]);

  return user !== null && role !== null;
}
