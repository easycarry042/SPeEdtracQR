import { db, id } from '@/lib/db';
import { now } from '@/lib/time';
import type { Actor } from '@/lib/auth/actor';
import { ROLE_SUPERVISOR } from '@/lib/auth/rbac';
import { DocumentStatus, activeValues } from '@/lib/domain/document-status';
import { citizenFacing, internalOnly } from '@/lib/domain/scope';
import { BOOKING_STATUS, KIND_EQUIPMENT } from '@/lib/domain/lending';
import { STEP_STATUS } from '@/lib/domain/request-step';
import { currentRequestStep, currentStepHasCustody, isSoftCopy } from '@/lib/domain/document';

/**
 * The counts on the sidebar's Dashboard, Internal and Resources rows: how many
 * requests have come to this person and are waiting on them. A count of work
 * waiting, not of things unseen — it falls when the work is picked up, not when
 * the page is opened.
 *
 * Super admins get none: they oversee, they are not sent requests.
 */
export type Badges = { home: number; internal: number; resources: number };

const NONE: Badges = { home: 0, internal: 0, resources: 0 };

export async function badgesFor(actor: Actor | null): Promise<Badges> {
  if (!actor || actor.can('manage system')) {
    return NONE;
  }

  const [home, internal, resources] = await Promise.all([
    homeBadge(actor),
    internalBadge(actor),
    resourcesBadge(actor),
  ]);

  return { home, internal, resources };
}

/**
 * Dashboard: a supervisor's unassigned citizen requests (their office's, plus
 * any that reached no office — the same queue their dashboard triages); a staff
 * member's new assignments still to accept.
 */
async function homeBadge(actor: Actor): Promise<number> {
  if (actor.hasRole(ROLE_SUPERVISOR)) {
    return db.documents.count({
      where: {
        ...citizenFacing,
        deleted_at: null,
        status: DocumentStatus.Pending,
        assigned_to: null,
        ...(actor.departmentId === null
          ? {}
          : { OR: [{ department_id: id(actor.departmentId) }, { department_id: null }] }),
      },
    });
  }

  // Assigned to them, not yet accepted.
  return db.documents.count({
    where: {
      ...citizenFacing,
      deleted_at: null,
      assigned_to: id(actor.id),
      accepted_at: null,
      status: { in: [DocumentStatus.Pending, DocumentStatus.InProgress] },
    },
  });
}

/**
 * Internal: a supervisor's internal requests now at their office; a staff
 * member's soft copies sent to them, plus paper folders at their office that
 * nobody has scanned in yet. Both also count requests returned to them for
 * revision (the supervisor: any their office filed; staff: their own).
 */
async function internalBadge(actor: Actor): Promise<number> {
  if (actor.departmentId === null) {
    return 0;
  }

  const departmentId = id(actor.departmentId);
  const canAct = actor.can('act on internal requests');

  const atMyOfficeWhere = {
    ...internalOnly,
    deleted_at: null,
    status: { in: activeValues() },
    request_steps: { some: { status: STEP_STATUS.Current, department_id: departmentId } },
  };

  // Returned for revision: waiting on the office that filed it.
  const returnedToMe = await db.documents.count({
    where: {
      ...internalOnly,
      deleted_at: null,
      status: DocumentStatus.Returned,
      ...(canAct ? { requesting_department_id: departmentId } : { created_by: id(actor.id) }),
    },
  });

  if (canAct) {
    return (await db.documents.count({ where: atMyOfficeWhere })) + returnedToMe;
  }

  // Plain staff: only the ones actually theirs to pick up — a soft copy sent to
  // them by name, or a paper folder nobody at the office has scanned in yet.
  const candidates = await db.documents.findMany({
    where: atMyOfficeWhere,
    select: { id: true, origin: true, has_hard_copy: true },
  });

  let mine = 0;

  for (const candidate of candidates) {
    const documentId = Number(candidate.id);
    const step = await currentRequestStep(documentId);

    if (step?.recipient_id === actor.id) {
      mine++;
      continue;
    }

    if (!isSoftCopy(candidate) && !(await currentStepHasCustody(documentId, step))) {
      mine++;
    }
  }

  return returnedToMe + mine;
}

/**
 * Resources: borrowing/reservation requests waiting for approval, plus loans
 * past their return date that nobody has marked returned.
 */
async function resourcesBadge(actor: Actor): Promise<number> {
  if (!actor.can('manage inventory')) {
    return 0;
  }

  const at = now();

  const [toApprove, overdue] = await Promise.all([
    db.bookings.count({
      where: {
        status: BOOKING_STATUS.Pending,
        ends_at: { gt: at },
        // Only what can be approved now: the request is assigned and accepted.
        document: {
          is: {
            deleted_at: null,
            status: { in: activeValues() },
            assigned_to: { not: null },
            accepted_at: { not: null },
          },
        },
      },
    }),
    db.bookings.count({
      where: {
        status: BOOKING_STATUS.Approved,
        returned_at: null,
        released_at: { not: null },
        ends_at: { lt: at },
        document: { is: { deleted_at: null } },
        resource: { is: { kind: KIND_EQUIPMENT } },
      },
    }),
  ]);

  return toApprove + overdue;
}
