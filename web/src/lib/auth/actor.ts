import { cache } from 'react';
import { forbidden } from 'next/navigation';
import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { readSession } from '@/lib/auth/session';
import {
  type Permission,
  ROLE_SUPERVISOR,
  ROLE_SUPER_ADMIN,
  grantsFor,
} from '@/lib/auth/rbac';

/**
 * The signed-in user as the rest of the app sees them: the `users` row plus the
 * `can()` / `hasRole()` checks that Spatie's `HasRoles` trait provided on the
 * Eloquent model. Build one with `currentActor()` (request-scoped) or
 * `actorById()` (jobs, cron, notification fan-out).
 */
export type Actor = {
  id: number;
  name: string;
  email: string;
  departmentId: number | null;
  departmentName: string | null;
  avatarPath: string | null;
  signaturePath: string | null;
  isActive: boolean;
  roles: string[];
  permissions: string[];
  can(permission: Permission): boolean;
  hasRole(role: string): boolean;
  isSuperAdmin(): boolean;
  isSupervisor(): boolean;
};

type UserRow = {
  id: bigint;
  name: string;
  email: string;
  department_id: bigint | null;
  avatar_path: string | null;
  signature_path: string | null;
  is_active: boolean;
  department: { name: string } | null;
};

function build(user: UserRow, roles: string[], permissions: string[]): Actor {
  const roleSet = new Set(roles);
  const permissionSet = new Set(permissions);

  return {
    id: toId(user.id),
    name: user.name,
    email: user.email,
    departmentId: user.department_id === null ? null : toId(user.department_id),
    departmentName: user.department?.name ?? null,
    avatarPath: user.avatar_path,
    signaturePath: user.signature_path,
    isActive: user.is_active,
    roles,
    permissions,
    can: (permission) => permissionSet.has(permission),
    hasRole: (role) => roleSet.has(role),
    isSuperAdmin: () => roleSet.has(ROLE_SUPER_ADMIN),
    isSupervisor: () => roleSet.has(ROLE_SUPERVISOR),
  };
}

const SELECT = {
  id: true,
  name: true,
  email: true,
  department_id: true,
  avatar_path: true,
  signature_path: true,
  is_active: true,
  department: { select: { name: true } },
} as const;

/**
 * Loads any user as an Actor. Archived (`deleted_at`) and deactivated accounts
 * resolve to null — SoftDeletes powers "archive", and an archived user must not
 * be able to act even while holding a valid cookie.
 */
export async function actorById(userId: number): Promise<Actor | null> {
  const user = await db.users.findFirst({
    where: { id: id(userId), deleted_at: null, is_active: true },
    select: SELECT,
  });

  if (!user) {
    return null;
  }

  const { roles, permissions } = await grantsFor(toId(user.id));

  return build(user, roles, permissions);
}

/**
 * The signed-in user for this request, or null for a guest.
 *
 * `cache()` dedupes within a single render pass, so a layout and the page it
 * wraps share one lookup instead of re-querying per component — Laravel got
 * this for free from the resolved `auth()->user()`.
 */
export const currentActor = cache(async (): Promise<Actor | null> => {
  const session = await readSession();

  return session ? actorById(session.sub) : null;
});

/** For pages already behind the auth middleware, where a guest is a bug. */
export async function requireActor(): Promise<Actor> {
  const actor = await currentActor();

  if (!actor) {
    throw new AuthError('Unauthenticated.', 401);
  }

  return actor;
}

/**
 * Permission gate. Laravel's `permission:` middleware aborted with 403 and the
 * app rendered an error page for it (RoutingAuthHardening); throwing here lets
 * route handlers and server actions share one path to that same 403.
 */
export async function requirePermission(...permissions: Permission[]): Promise<Actor> {
  const actor = await requireActor();

  if (!permissions.some((permission) => actor.can(permission))) {
    // `forbidden()` renders app/forbidden.tsx with a real 403. Throwing a plain
    // error here answered 500, which told the user the site was broken rather
    // than that the page was not theirs.
    forbidden();
  }

  return actor;
}

export async function requireRole(...roles: string[]): Promise<Actor> {
  const actor = await requireActor();

  if (!roles.some((role) => actor.hasRole(role))) {
    forbidden();
  }

  return actor;
}

export class AuthError extends Error {
  constructor(
    message: string,
    public readonly status: 401 | 403,
  ) {
    super(message);
    this.name = 'AuthError';
  }
}

/**
 * Stamps `last_active_at`, which drives the "Active now" marker on the staff
 * directory and profile. Laravel did this in the `TrackLastActive` middleware;
 * here it is called from the authenticated layout. Throttled to one write a
 * minute so ordinary browsing does not hammer the row.
 */
export async function touchLastActive(userId: number): Promise<void> {
  const current = now();

  await db.users.updateMany({
    where: {
      id: id(userId),
      OR: [{ last_active_at: null }, { last_active_at: { lt: new Date(current.getTime() - 60_000) } }],
    },
    data: { last_active_at: current },
  });
}
