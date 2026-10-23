'use server';

import { revalidatePath } from 'next/cache';
import { currentActor, AuthError, type Actor } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';
import type { Permission } from '@/lib/auth/rbac';
import * as offices from '@/lib/domain/admin-departments';
import * as accounts from '@/lib/domain/admin-users';
import * as catalog from '@/lib/domain/admin-catalog';

/**
 * The Setup group's write paths: the office directory, the public request
 * catalog, the endorsement chains and the resource catalog.
 *
 * Same `{ ok, message }` contract as the other action modules rather than a
 * thrown error, because every refusal here is a sentence the person setting the
 * office up has to read — a code already taken, a request type still pointed at
 * the item they are deleting, a turnaround outside the allowed range.
 */
export type AdminResult = {
  ok: boolean;
  message: string;
};

/**
 * Runs a setup write behind its permission.
 *
 * The gate is re-checked here and not only on the page: a server action is a
 * POST endpoint of its own, so a page-level check alone would leave the write
 * reachable by anyone who can guess the action id.
 */
async function run(
  permission: Permission,
  fn: (actor: Actor) => Promise<{ message: string }>,
): Promise<AdminResult> {
  const actor = await currentActor();

  if (!actor || !actor.can(permission)) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  try {
    const { message } = await fn(actor);

    // The sidebar, the request form's catalog and these lists all read from the
    // server, so one save refreshes the lot.
    revalidatePath('/', 'layout');

    return { ok: true, message };
  } catch (error) {
    if (error instanceof ActionError || error instanceof AuthError) {
      return { ok: false, message: error.message };
    }

    console.error('[admin action]', error);

    return { ok: false, message: 'Something went wrong. Nothing was saved.' };
  }
}

/* ── Departments ─────────────────────────────────────────────────────────── */

/** Create or rename an office. One action for both, as the Blade form was. */
export async function saveDepartment(
  departmentId: number | null,
  input: offices.DepartmentInput,
): Promise<AdminResult> {
  return run('manage system', () =>
    departmentId === null
      ? offices.createDepartment(input)
      : offices.updateDepartment(departmentId, input),
  );
}

/**
 * Offices are deactivated, never deleted — users, route templates and the
 * chains of requests already filed all point at them.
 */
export async function toggleDepartmentActive(departmentId: number): Promise<AdminResult> {
  return run('manage system', () => offices.toggleDepartment(departmentId));
}

/* ── Users ───────────────────────────────────────────────────────────────── */

/**
 * Create or edit an account. On create the password is required; on edit a
 * blank password field leaves the current one alone, which is what an admin
 * fixing a typo in someone's name expects.
 */
export async function saveUser(
  userId: number | null,
  input: accounts.UserInput,
): Promise<AdminResult> {
  return run('manage users', () =>
    userId === null ? accounts.createUser(input) : accounts.updateUser(userId, input),
  );
}

export async function toggleUserActive(userId: number): Promise<AdminResult> {
  return run('manage users', (actor) => accounts.toggleUser(userId, actor));
}

/** Archiving is a soft delete: the row stays listed, with Restore beside it. */
export async function archiveUserAccount(userId: number): Promise<AdminResult> {
  return run('manage users', (actor) => accounts.archiveUser(userId, actor));
}

export async function restoreUserAccount(userId: number): Promise<AdminResult> {
  return run('manage users', () => accounts.restoreUser(userId));
}

/**
 * Permanent deletion, refused once the account has document activity — an
 * audit trail that can be erased by deleting its author is not an audit trail.
 */
export async function deleteUserAccount(userId: number): Promise<AdminResult> {
  return run('manage users', (actor) => accounts.deleteUser(userId, actor));
}

export async function bulkUserAction(
  action: accounts.BulkAction,
  userIds: number[],
): Promise<AdminResult> {
  return run('manage users', (actor) => accounts.bulkUsers(action, userIds, actor));
}

/* ── Request types (the public catalog) ──────────────────────────────────── */

export async function saveRequestType(
  requestTypeId: number | null,
  input: catalog.RequestTypeInput,
): Promise<AdminResult> {
  return run('manage system', () =>
    requestTypeId === null
      ? catalog.createRequestType(input)
      : catalog.updateRequestType(requestTypeId, input),
  );
}

export async function toggleRequestTypeActive(requestTypeId: number): Promise<AdminResult> {
  return run('manage system', () => catalog.toggleRequestType(requestTypeId));
}

/**
 * Deleting a type does not rewrite history: requests already submitted keep
 * their own snapshot of each requirement's label and whether it was required.
 */
export async function removeRequestType(requestTypeId: number): Promise<AdminResult> {
  return run('manage system', () => catalog.deleteRequestType(requestTypeId));
}

/* ── Route templates (endorsement chains) ────────────────────────────────── */

export async function saveRouteTemplate(
  templateId: number | null,
  input: catalog.RouteTemplateInput,
): Promise<AdminResult> {
  return run('manage system', () =>
    templateId === null
      ? catalog.createRouteTemplate(input)
      : catalog.updateRouteTemplate(templateId, input),
  );
}

export async function toggleRouteTemplateActive(templateId: number): Promise<AdminResult> {
  return run('manage system', () => catalog.toggleRouteTemplate(templateId));
}

export async function removeRouteTemplate(templateId: number): Promise<AdminResult> {
  return run('manage system', () => catalog.deleteRouteTemplate(templateId));
}

/* ── Resources (the lending catalog) ─────────────────────────────────────── */

export async function saveResource(
  resourceId: number | null,
  input: catalog.ResourceInput,
): Promise<AdminResult> {
  return run('manage system', (actor) =>
    resourceId === null
      ? catalog.createResource(input, actor)
      : catalog.updateResource(resourceId, input, actor),
  );
}

export async function toggleResourceActive(resourceId: number): Promise<AdminResult> {
  return run('manage system', (actor) => catalog.toggleResource(resourceId, actor));
}

export async function removeResource(resourceId: number): Promise<AdminResult> {
  return run('manage system', () => catalog.deleteResource(resourceId));
}
