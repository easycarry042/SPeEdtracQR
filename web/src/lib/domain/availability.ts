import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import type { Actor } from '@/lib/auth/actor';
import { ROLE_SUPERVISOR, ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';
import { ActionError } from '@/lib/domain/status-actions';

/**
 * Manages daily staff availability ("On Duty" vs "On Leave").
 *
 * Rules:
 * 1. Self-service: Any staff member can toggle their own availability (starting/ending shift).
 * 2. Supervisor Override: Supervisors can toggle availability for staff in their department.
 * 3. Super Admin Override: Org-wide administrators can toggle availability for anyone.
 */
export async function setUserAvailability(
  targetUserId: number,
  isAvailable: boolean,
  actor: Actor,
): Promise<{ ok: boolean; isAvailable: boolean; message: string }> {
  const target = await db.users.findFirst({
    where: { id: id(targetUserId), deleted_at: null },
    select: {
      id: true,
      name: true,
      department_id: true,
      is_available: true,
    },
  });

  if (!target) {
    throw new ActionError('User not found.');
  }

  const isSelf = actor.id === targetUserId;
  const isSuperAdmin = actor.isSuperAdmin() || actor.can('manage system') || actor.can('manage users');
  const isDeptSupervisor =
    actor.isSupervisor() &&
    actor.departmentId !== null &&
    target.department_id !== null &&
    toId(target.department_id) === actor.departmentId;

  if (!isSelf && !isSuperAdmin && !isDeptSupervisor) {
    throw new ActionError(
      'You do not have permission to change this staff member’s duty status.',
      403,
    );
  }

  await db.users.update({
    where: { id: target.id },
    data: {
      is_available: isAvailable,
      updated_at: now(),
    },
  });

  // Going On Leave hands their not-yet-accepted requests on now — to another
  // on-duty usual handler, else the office pool — instead of leaving them to
  // wait for the morning escalation sweep. Work they already accepted stays
  // theirs: they release it, or the supervisor reassigns it.
  let moved = 0;
  if (!isAvailable && target.is_available) {
    const { reclaimUnacceptedRequests } = await import('@/lib/sweeps');
    const result = await reclaimUnacceptedRequests(0, { assigneeId: targetUserId, why: 'is on leave' });
    moved = (result.counts.reassigned ?? 0) + (result.counts.reclaimedToPool ?? 0);
  }

  const label = isAvailable ? 'On Duty' : 'On Leave';
  const handedOn = moved > 0 ? ` ${moved} request${moved === 1 ? '' : 's'} waiting for acceptance ${moved === 1 ? 'was' : 'were'} passed on.` : '';
  const message = isSelf
    ? `You are now marked as ${label}.${handedOn}`
    : `${target.name} has been marked as ${label}.${handedOn}`;

  return { ok: true, isAvailable, message };
}

export async function toggleUserAvailability(
  targetUserId: number,
  actor: Actor,
): Promise<{ ok: boolean; isAvailable: boolean; message: string }> {
  const target = await db.users.findFirst({
    where: { id: id(targetUserId), deleted_at: null },
    select: { id: true, is_available: true },
  });

  if (!target) {
    throw new ActionError('User not found.');
  }

  return setUserAvailability(targetUserId, !target.is_available, actor);
}

export async function toggleMyAvailability(
  actor: Actor,
): Promise<{ ok: boolean; isAvailable: boolean; message: string }> {
  return setUserAvailability(actor.id, !actor.isAvailable, actor);
}
