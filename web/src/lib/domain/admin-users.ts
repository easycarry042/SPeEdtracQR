import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { hashPassword } from '@/lib/auth/password';
import { MODEL_TYPE } from '@/lib/auth/rbac';
import { ActionError } from '@/lib/domain/status-actions';
import { forUsers as staffLoad, type Load } from '@/lib/domain/staff-load';
import type { Actor } from '@/lib/auth/actor';

/**
 * Accounts: who can sign in, as what, for which office — and what they are
 * carrying, because the staff directory this page absorbed was a second list of
 * the same people.
 *
 * Port of `Admin\UserController`. Archiving is a soft delete and the row stays
 * listed with a Restore action, so an account is never silently gone.
 */
export type UserRow = {
  id: number;
  name: string;
  email: string;
  role: string | null;
  office: string | null;
  isActive: boolean;
  isArchived: boolean;
  load: Load | null;
};

export type UserListing = {
  rows: UserRow[];
  page: number;
  pages: number;
  total: number;
  roles: string[];
};

const PER_PAGE = 20;

export async function userList(options: {
  search?: string | null;
  role?: string | null;
  page?: number;
}): Promise<UserListing> {
  const search = (options.search ?? '').trim();
  const role = (options.role ?? '').trim();
  const page = Math.max(1, Math.trunc(options.page ?? 1));

  // Spatie's pivot has no FK to users, so a role filter is two queries.
  const roleIds =
    role === ''
      ? null
      : (
          await db.model_has_roles.findMany({
            where: { model_type: MODEL_TYPE, roles: { name: role } },
            select: { model_id: true },
          })
        ).map((link) => link.model_id);

  const where = {
    ...(search === ''
      ? {}
      : {
          OR: [
            { name: { contains: search, mode: 'insensitive' as const } },
            { email: { contains: search, mode: 'insensitive' as const } },
          ],
        }),
    ...(roleIds === null ? {} : { id: { in: roleIds } }),
  };

  const [total, users, allRoles] = await Promise.all([
    db.users.count({ where }),
    db.users.findMany({
      where,
      orderBy: { name: 'asc' },
      skip: (page - 1) * PER_PAGE,
      take: PER_PAGE,
      select: {
        id: true,
        name: true,
        email: true,
        is_active: true,
        deleted_at: true,
        department: { select: { name: true } },
      },
    }),
    db.roles.findMany({ orderBy: { name: 'asc' }, select: { name: true } }),
  ]);

  const ids = users.map((user) => toId(user.id));

  const [roleLinks, load] = await Promise.all([
    ids.length === 0
      ? Promise.resolve([])
      : db.model_has_roles.findMany({
          where: { model_type: MODEL_TYPE, model_id: { in: users.map((user) => user.id) } },
          select: { model_id: true, roles: { select: { name: true } } },
        }),
    staffLoad(ids),
  ]);

  const roleByUser = new Map<string, string>(
    roleLinks.map((link) => [String(link.model_id), link.roles.name]),
  );

  return {
    rows: users.map((user) => ({
      id: toId(user.id),
      name: user.name,
      email: user.email,
      role: roleByUser.get(String(user.id)) ?? null,
      office: user.department?.name ?? null,
      isActive: user.is_active,
      isArchived: user.deleted_at !== null,
      load: load.get(toId(user.id)) ?? null,
    })),
    page,
    pages: Math.max(1, Math.ceil(total / PER_PAGE)),
    total,
    roles: allRoles.map((row) => row.name),
  };
}

/**
 * The roles an account can be given, straight from Spatie's table so the
 * Laravel seeder stays the one source of truth for what roles exist.
 */
export async function assignableRoles(): Promise<string[]> {
  const roles = await db.roles.findMany({ orderBy: { name: 'asc' }, select: { name: true } });

  return roles.map((role) => role.name);
}

export async function userById(userId: number) {
  const user = await db.users.findFirst({
    where: { id: id(userId) },
    select: { id: true, name: true, email: true, is_active: true, department_id: true },
  });

  if (!user) {
    return null;
  }

  const link = await db.model_has_roles.findFirst({
    where: { model_type: MODEL_TYPE, model_id: user.id },
    select: { roles: { select: { name: true } } },
  });

  return {
    id: toId(user.id),
    name: user.name,
    email: user.email,
    isActive: user.is_active,
    departmentId: user.department_id === null ? null : toId(user.department_id),
    role: link?.roles.name ?? null,
  };
}

export type UserInput = {
  name: string;
  email: string;
  password: string | null;
  passwordConfirmation: string | null;
  role: string;
  departmentId: number | null;
};

async function validate(
  input: UserInput,
  ignoreId: number | null,
  passwordRequired: boolean,
): Promise<{ name: string; email: string; role: string; departmentId: number | null }> {
  const name = input.name.trim();
  const email = input.email.trim().toLowerCase();

  if (name === '' || name.length > 255) {
    throw new ActionError('Give the account a name of up to 255 characters.');
  }

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    throw new ActionError('That email address does not look right.');
  }

  if (passwordRequired || (input.password ?? '') !== '') {
    const password = input.password ?? '';

    if (password.length < 8) {
      throw new ActionError('The password must be at least 8 characters.');
    }

    if (password !== (input.passwordConfirmation ?? '')) {
      throw new ActionError('The two passwords do not match.');
    }
  }

  const role = await db.roles.findFirst({ where: { name: input.role }, select: { name: true } });

  if (!role) {
    throw new ActionError('Choose a role for this account.');
  }

  if (input.departmentId !== null) {
    const office = await db.departments.findFirst({
      where: { id: id(input.departmentId) },
      select: { id: true },
    });

    if (!office) {
      throw new ActionError('That office no longer exists.');
    }
  }

  // Only ACTIVE accounts block the address. An archived account keeps its row
  // and the database's unique index, so it is revived rather than collided with.
  const clash = await db.users.findFirst({
    where: {
      email: { equals: email, mode: 'insensitive' },
      deleted_at: null,
      ...(ignoreId === null ? {} : { NOT: { id: id(ignoreId) } }),
    },
    select: { id: true },
  });

  if (clash) {
    throw new ActionError('An account already uses that email address.');
  }

  return { name, email, role: role.name, departmentId: input.departmentId };
}

/** One role per account, so adding a role replaces whatever was there. */
async function syncRole(userId: bigint, role: string): Promise<void> {
  const row = await db.roles.findFirst({ where: { name: role }, select: { id: true } });

  if (!row) {
    throw new ActionError('That role no longer exists.');
  }

  await db.model_has_roles.deleteMany({ where: { model_type: MODEL_TYPE, model_id: userId } });
  await db.model_has_roles.create({
    data: { role_id: row.id, model_type: MODEL_TYPE, model_id: userId },
  });
}

export async function createUser(input: UserInput): Promise<{ message: string }> {
  const { name, email, role, departmentId } = await validate(input, null, true);
  const password = await hashPassword(input.password as string);

  // Re-adding an archived account's address restores and updates it.
  const archived = await db.users.findFirst({
    where: { email: { equals: email, mode: 'insensitive' }, NOT: { deleted_at: null } },
    select: { id: true },
  });

  if (archived) {
    await db.users.update({
      where: { id: archived.id },
      data: {
        name,
        password,
        is_active: true,
        deleted_at: null,
        department_id: departmentId === null ? null : id(departmentId),
        updated_at: now(),
      },
    });

    await syncRole(archived.id, role);

    return { message: `Archived account for ${name} was restored and updated.` };
  }

  const created = await db.users.create({
    data: {
      name,
      email,
      password,
      is_active: true,
      department_id: departmentId === null ? null : id(departmentId),
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  await syncRole(created.id, role);

  return { message: `User ${name} created successfully.` };
}

export async function updateUser(
  userId: number,
  input: UserInput,
): Promise<{ message: string }> {
  const existing = await userById(userId);

  if (!existing) {
    throw new ActionError('That account no longer exists.');
  }

  const { name, email, role, departmentId } = await validate(input, userId, false);

  await db.users.update({
    where: { id: id(userId) },
    data: {
      name,
      email,
      department_id: departmentId === null ? null : id(departmentId),
      ...((input.password ?? '') === ''
        ? {}
        : { password: await hashPassword(input.password as string) }),
      updated_at: now(),
    },
  });

  await syncRole(id(userId), role);

  return { message: `User ${name} updated successfully.` };
}

/** An admin must not be able to lock themselves out. */
function refuseSelf(userId: number, actor: Actor, verb: string): void {
  if (userId === actor.id) {
    throw new ActionError(`You cannot ${verb} your own account.`);
  }
}

export async function toggleUser(userId: number, actor: Actor): Promise<{ message: string }> {
  refuseSelf(userId, actor, 'deactivate');

  const existing = await userById(userId);

  if (!existing) {
    throw new ActionError('That account no longer exists.');
  }

  await db.users.update({
    where: { id: id(userId) },
    data: { is_active: !existing.isActive, updated_at: now() },
  });

  return {
    message: `Account for ${existing.name} has been ${existing.isActive ? 'deactivated' : 'activated'}.`,
  };
}

export async function archiveUser(userId: number, actor: Actor): Promise<{ message: string }> {
  refuseSelf(userId, actor, 'archive');

  const existing = await userById(userId);

  if (!existing) {
    throw new ActionError('That account no longer exists.');
  }

  await db.users.update({
    where: { id: id(userId) },
    data: { deleted_at: now(), updated_at: now() },
  });

  return { message: `Account for ${existing.name} has been archived.` };
}

export async function restoreUser(userId: number): Promise<{ message: string }> {
  const existing = await userById(userId);

  if (!existing) {
    throw new ActionError('That account no longer exists.');
  }

  await db.users.update({
    where: { id: id(userId) },
    data: { deleted_at: null, updated_at: now() },
  });

  return { message: `Account for ${existing.name} has been restored.` };
}

/**
 * Permanent deletion. Refused when the account has document activity —
 * `documents.created_by` and the scan trail restrict it, and an audit trail
 * that can be erased by deleting its author is not an audit trail.
 */
export async function deleteUser(userId: number, actor: Actor): Promise<{ message: string }> {
  refuseSelf(userId, actor, 'delete');

  const existing = await userById(userId);

  if (!existing) {
    throw new ActionError('That account no longer exists.');
  }

  const [documents, custody, decisions] = await Promise.all([
    db.documents.count({ where: { OR: [{ created_by: id(userId) }, { assigned_to: id(userId) }] } }),
    db.document_custody_events.count({ where: { user_id: id(userId) } }),
    db.request_steps.count({ where: { acted_by: id(userId) } }),
  ]);

  if (documents > 0 || custody > 0 || decisions > 0) {
    throw new ActionError(
      `${existing.name} has document activity and cannot be permanently deleted. Archive the account instead.`,
    );
  }

  await db.model_has_roles.deleteMany({ where: { model_type: MODEL_TYPE, model_id: id(userId) } });
  await db.users.delete({ where: { id: id(userId) } });

  return { message: `Account for ${existing.name} has been permanently deleted.` };
}

export type BulkAction = 'activate' | 'deactivate' | 'archive';

/**
 * One action across several accounts. The caller's own account is always
 * dropped from the selection rather than refused, so a sweep over a page of
 * users does what was meant instead of failing wholesale.
 */
const BULK_ACTIONS: BulkAction[] = ['activate', 'deactivate', 'archive'];

export async function bulkUsers(
  action: BulkAction,
  userIds: number[],
  actor: Actor,
): Promise<{ message: string }> {
  /**
   * The union type is a compile-time promise only — this is called from a
   * server action, so the string arrives over the wire. Laravel validated it
   * with an `in:` rule; without the same check here an unknown action would
   * fall through to the deactivate branch and quietly lock people out.
   */
  if (!BULK_ACTIONS.includes(action)) {
    throw new ActionError('That is not an action that can be applied to several accounts.');
  }

  const ids = [...new Set(userIds.filter((value) => Number.isInteger(value) && value !== actor.id))];

  if (ids.length === 0) {
    throw new ActionError(
      'No eligible accounts were selected (your own account is always excluded).',
    );
  }

  const keys = ids.map(id);

  const affected =
    action === 'archive'
      ? (
          await db.users.updateMany({
            where: { id: { in: keys }, deleted_at: null },
            data: { deleted_at: now(), updated_at: now() },
          })
        ).count
      : (
          await db.users.updateMany({
            where: { id: { in: keys }, is_active: action === 'deactivate' },
            data: { is_active: action === 'activate', updated_at: now() },
          })
        ).count;

  if (affected === 0) {
    throw new ActionError('Nothing changed — the selected accounts were already in that state.');
  }

  const verb = { activate: 'activated', deactivate: 'deactivated', archive: 'archived' }[action];

  return { message: `${affected} ${affected === 1 ? 'account' : 'accounts'} ${verb}.` };
}
