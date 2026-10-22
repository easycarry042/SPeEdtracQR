'use server';

import { revalidatePath } from 'next/cache';
import { currentActor, AuthError } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';
import type { Permission } from '@/lib/auth/rbac';
import * as offices from '@/lib/domain/admin-departments';

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
  fn: () => Promise<{ message: string }>,
): Promise<AdminResult> {
  const actor = await currentActor();

  if (!actor || !actor.can(permission)) {
    return { ok: false, message: 'This action is unauthorized.' };
  }

  try {
    const { message } = await fn();

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
