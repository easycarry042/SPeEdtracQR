import { db, id, toId } from '@/lib/db';
import { addDays, diffInDays, diffInHours, diffInHoursExact, endOfDay, isPast, now } from '@/lib/time';
import { fromHours } from '@/lib/domain/duration';
import { tracking } from '@/lib/config';
import type { Actor } from '@/lib/auth/actor';
import { ROLE_SUPER_ADMIN, ROLE_SUPERVISOR } from '@/lib/auth/rbac';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import {
  DocumentStatus,
  fromLoose,
  isTerminal,
  label as statusLabel,
  slaHours,
} from '@/lib/domain/document-status';
import { STEP_STATUS, type RequestStepRow } from '@/lib/domain/request-step';

/** Allowed values for the `blocked_by` hold tag. */
export const BLOCKED_BY = ['citizen', 'internal', 'external'] as const;

/**
 * The document fields the domain helpers below need. Deliberately a structural
 * type rather than Prisma's full `documents`: callers select what they use, and
 * a helper that only reads `status` should not force a caller to fetch 46
 * columns.
 */
export type DocumentCore = {
  id: number;
  tracking_number: string;
  status: string;
  origin: string;
  has_hard_copy: boolean;
  status_changed_at: Date | null;
  updated_at: Date | null;
  created_at: Date | null;
  assigned_to: number | null;
  created_by: number | null;
  accepted_at: Date | null;
  department_id: number | null;
  requesting_department_id: number | null;
  status_before_hold: string | null;
  hold_until: Date | null;
  held_at: Date | null;
};

export type DocumentLike = Partial<DocumentCore> & { id: number; status: string };

/** The current stage as an enum. `status` is a varchar; the enum is the authority. */
export function statusOf(document: { status: string }): DocumentStatus {
  return fromLoose(document.status);
}

/** The stage a held/denied request was in when it stopped, if recorded. */
export function statusBeforeHold(document: {
  status_before_hold?: string | null;
}): DocumentStatus | null {
  return document.status_before_hold ? fromLoose(document.status_before_hold) : null;
}

/** Whether this is an internal dept-to-dept request (vs a citizen ticket). */
export function isInternal(document: { origin?: string | null }): boolean {
  return document.origin === ORIGIN_INTERNAL;
}

/**
 * A paper-backed request travels as a folder that each office scans in; a soft
 * copy exists only in the system.
 */
export function isSoftCopy(document: {
  origin?: string | null;
  has_hard_copy?: boolean;
}): boolean {
  return isInternal(document) && document.has_hard_copy === false;
}

/**
 * The field changes for a stage move. Does NOT save — callers own permission
 * checks, persistence, activity logging and broadcasting.
 */
export function statusChangeData(status: DocumentStatus): {
  status: string;
  status_changed_at: Date;
  sla_warning_notified_at: null;
  sla_breach_notified_at: null;
  completed_at: Date | null;
} {
  const stamp = now();

  return {
    status,
    status_changed_at: stamp,
    // New stage = fresh SLA stay: restart the clock and let the scheduled sweep
    // re-notify if it overstays this stage.
    sla_warning_notified_at: null,
    sla_breach_notified_at: null,
    // Keep the legacy completion timestamp coherent for analytics/history.
    completed_at: isTerminal(status) ? stamp : null,
  };
}

/**
 * Overdue = sitting in its current stage longer than the stage's SLA budget.
 * Anchored to `status_changed_at`; terminal stages and stages with no budget
 * (including On Hold, which pauses the clock) are never overdue.
 */
export function isOverdue(document: {
  status: string;
  status_changed_at?: Date | null;
  updated_at?: Date | null;
  created_at?: Date | null;
}): boolean {
  const stage = statusOf(document);
  const sla = slaHours(stage);

  if (sla === null || isTerminal(stage)) {
    return false;
  }

  const anchor = document.status_changed_at ?? document.updated_at ?? document.created_at;

  return anchor ? diffInHours(anchor) > sla : false;
}

/**
 * How far past its current stage's budget an open request is, as a readable
 * span ("5h 18m", "3d 2h"), or null when it is not overdue at all.
 *
 * The same anchor and the same exemptions as `isOverdue()` — this is only the
 * number behind the "+… late" tag the oversight queues show, so a request can
 * never read as late on one screen and on time on the next.
 */
export function overdueBy(
  document: {
    status: string;
    status_changed_at?: Date | null;
    updated_at?: Date | null;
    created_at?: Date | null;
  },
  at: Date = now(),
): string | null {
  const stage = statusOf(document);
  const sla = slaHours(stage);
  const anchor = document.status_changed_at ?? document.updated_at ?? document.created_at;

  if (sla === null || isTerminal(stage) || !anchor) {
    return null;
  }

  const over = diffInHoursExact(anchor, at) - sla;

  return over > 0 ? fromHours(over) : null;
}

/**
 * When a hold stops being legitimate and becomes a stall.
 *
 * A hold deliberately pauses the SLA clock, so `isOverdue()` and the SLA sweep
 * both ignore held documents. Without a second deadline a parked document would
 * be accountable to nothing, so every hold gets one:
 *   - with a `hold_until`: the end of that day (staff named the date);
 *   - open-ended: `held_at` + `tracking.holds.staleAfterDays`.
 *
 * Null when the document is not on hold, or on hold with no anchor date at all.
 */
export function holdDueAt(document: {
  status: string;
  hold_until?: Date | null;
  held_at?: Date | null;
}): Date | null {
  if (statusOf(document) !== DocumentStatus.OnHold) {
    return null;
  }

  if (document.hold_until) {
    return endOfDay(document.hold_until);
  }

  return document.held_at ? addDays(document.held_at, tracking.holds.staleAfterDays) : null;
}

/** True once a hold has run past `holdDueAt()` — the stall the sweep chases. */
export function isHoldOverdue(document: Parameters<typeof holdDueAt>[0]): boolean {
  const due = holdDueAt(document);

  return due !== null && isPast(due);
}

/** Whole days a hold has overrun its deadline (0 when not overdue). */
export function holdOverdueDays(document: Parameters<typeof holdDueAt>[0]): number {
  const due = holdDueAt(document);

  return due !== null && isPast(due) ? diffInDays(due) : 0;
}

/**
 * Only the assigned staff member (holding `advance documents`) or an admin
 * (`manage system` / `assign documents`) may move this document's stage. Single
 * source of truth for the server-side gate and every place that renders the
 * advance/revert/hold controls, so the UI never offers an action the backend
 * will then reject.
 */
export function canBeAdvancedBy(
  document: { assigned_to?: number | null },
  actor: Actor | null,
): boolean {
  if (!actor) {
    return false;
  }

  if (actor.can('manage system') || actor.can('assign documents')) {
    return true;
  }

  return (
    actor.can('advance documents') &&
    document.assigned_to !== null &&
    document.assigned_to === actor.id
  );
}

/**
 * Whether this person may resend or withdraw a request returned to the office
 * that filed it: whoever filed it, or anyone at that office who can file
 * internal requests (the same people who could have filed it).
 */
export function canBeRevisedBy(
  document: {
    status: string;
    origin?: string | null;
    created_by?: number | null;
    requesting_department_id?: number | null;
  },
  actor: Actor | null,
): boolean {
  if (!actor || !isInternal(document) || statusOf(document) !== DocumentStatus.Returned) {
    return false;
  }

  return (
    document.created_by === actor.id ||
    (document.requesting_department_id !== null &&
      document.requesting_department_id === actor.departmentId &&
      actor.can('create internal requests'))
  );
}

/**
 * Whether this user is the one who must act on the request's current hop: a
 * supervisor of the office the request is sitting at, or — for a soft copy —
 * the person the filing office named to receive it there (a soft copy has no
 * folder to hand to whoever is at the desk, so it goes to someone by name).
 */
export function canActOnCurrentStep(
  document: { origin?: string | null },
  step: Pick<RequestStepRow, 'department_id' | 'recipient_id'> | null,
  actor: Actor | null,
): boolean {
  if (!actor || !isInternal(document) || !step) {
    return false;
  }

  if (actor.departmentId === null || actor.departmentId !== step.department_id) {
    return false;
  }

  return (
    actor.can('act on internal requests') ||
    (step.recipient_id !== null && step.recipient_id === actor.id)
  );
}

/**
 * Whether this person may RECEIVE the request at the office holding it: scan
 * the paper folder in, or pick up a soft copy. Wider than deciding: whoever at
 * the office is handed the folder scans it in, but only the office's supervisor
 * — or a soft copy's named recipient — approves, returns, denies or completes
 * it. A soft copy is still received only by the person it was sent to or their
 * supervisor.
 */
export function canReceiveCurrentStep(
  document: { origin?: string | null; has_hard_copy?: boolean },
  step: Pick<RequestStepRow, 'department_id' | 'recipient_id'> | null,
  actor: Actor | null,
): boolean {
  if (canActOnCurrentStep(document, step, actor)) {
    return true;
  }

  if (!actor || !isInternal(document) || isSoftCopy(document) || actor.departmentId === null) {
    return false;
  }

  return step?.department_id === actor.departmentId;
}

/** Citizen-facing role wording — never the raw Spatie role name. */
export function roleLabelFor(user: { roles: string[] }): string {
  if (user.roles.includes(ROLE_SUPER_ADMIN)) {
    return 'Administrator';
  }

  if (user.roles.includes(ROLE_SUPERVISOR)) {
    return 'Supervisor';
  }

  if (user.roles.includes('department_admin')) {
    return 'Department Head';
  }

  return 'Staff';
}

/**
 * Colour band for `internalStatusLabel()` — maps to a pill/dot tone.
 * One of: green | amber | red | returned | muted.
 */
export function internalStatusBand(document: { status: string }): string {
  switch (statusOf(document)) {
    case DocumentStatus.Completed:
      return 'green';
    case DocumentStatus.Denied:
      return 'red';
    case DocumentStatus.Returned:
      return 'returned';
    case DocumentStatus.Withdrawn:
      return 'muted';
    default:
      return 'amber';
  }
}

/**
 * A human, chain-aware headline for an internal request — derived from the
 * endorsement chain rather than the citizen-oriented status enum. The office
 * currently holding the request is shown alongside.
 */
export function internalStatusLabel(
  document: { status: string; requesting_department_id?: number | null },
  step: Pick<RequestStepRow, 'step_order' | 'department_id'> | null,
  hasCustody: boolean,
): string {
  const status = statusOf(document);

  switch (status) {
    case DocumentStatus.Completed:
      return 'Completed';
    case DocumentStatus.Denied:
      return 'Denied';
    case DocumentStatus.Returned:
      return 'Returned for revision';
    case DocumentStatus.Withdrawn:
      return 'Withdrawn';
    default:
      break;
  }

  if (!step) {
    return statusLabel(status);
  }

  // step_order 0 is the requesting office's own endorsement hop.
  if (step.step_order === 0 && step.department_id === document.requesting_department_id) {
    return 'Awaiting endorsement';
  }

  // Sent but not yet picked up: nobody at the office has scanned (or, for a
  // soft copy, dropped) its QR to receive it.
  return hasCustody ? 'Under review' : 'Awaiting receipt';
}

// ===== Queries that need the database =====

/** The hop the request is sitting at right now (null when no chain or all done). */
export async function currentRequestStep(documentId: number): Promise<RequestStepRow | null> {
  const step = await db.request_steps.findFirst({
    where: { document_id: id(documentId), status: STEP_STATUS.Current },
    orderBy: [{ step_order: 'asc' }, { id: 'asc' }],
    include: {
      department: { select: { id: true, name: true, code: true } },
      recipient: { select: { id: true, name: true } },
      actor: { select: { id: true, name: true } },
    },
  });

  if (!step) {
    return null;
  }

  return {
    id: toId(step.id),
    document_id: toId(step.document_id),
    step_order: step.step_order,
    department_id: toId(step.department_id),
    recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
    action: step.action,
    status: step.status,
    acted_by: step.acted_by === null ? null : toId(step.acted_by),
    acted_at: step.acted_at,
    started_at: step.started_at,
    remarks: step.remarks,
    signature_path: step.signature_path,
    departmentName: step.department.name,
    departmentCode: step.department.code,
    recipientName: step.recipient?.name ?? null,
    actorName: step.actor?.name ?? null,
  };
}

/**
 * Whether the office holding the current hop has physically taken custody of
 * the folder since that hop began. This is the precondition for acting on the
 * endorsement chain: it binds every digital endorsement to a QR scan (an
 * audited manual override still counts). Custody recorded while the request sat
 * at an earlier office does not carry over to the new hop.
 *
 * A soft copy has no folder, but it still has to be RECEIVED: the person it was
 * sent to (or their supervisor) scans its QR — or drops the QR image — and that
 * receipt is recorded the same way, so nothing moves until someone at the
 * office has actually picked it up.
 */
export async function currentStepHasCustody(
  documentId: number,
  step: Pick<RequestStepRow, 'department_id' | 'started_at'> | null,
): Promise<boolean> {
  if (!step || step.started_at === null) {
    return false;
  }

  const held = await db.document_custody_events.findFirst({
    where: {
      document_id: id(documentId),
      created_at: { gte: step.started_at },
      user: { department_id: id(step.department_id) },
    },
    select: { id: true },
  });

  return held !== null;
}

/** Mandatory requirements a staffer has not yet verified — blocks approval. */
export async function unverifiedMandatoryRequirements(
  documentId: number,
): Promise<Array<{ id: number; label: string }>> {
  const rows = await db.document_requirements.findMany({
    where: { document_id: id(documentId), is_mandatory: true, verified_at: null },
    select: { id: true, label: true },
    orderBy: { id: 'asc' },
  });

  return rows.map((row) => ({ id: toId(row.id), label: row.label }));
}

/**
 * Log a department onto the endorsement chain — used when an office scans the
 * folder into its custody. The chain is a growing record of who handled the
 * paper, not a route fixed at filing: it ends only when a department marks the
 * request done.
 *
 * The office that was holding it is closed as `forwarded` (it passed the paper
 * on) unless it had already been formally acted on.
 */
export async function appendEndorsementStep(
  documentId: number,
  departmentId: number,
  action = 'Received and handling',
  recipientId: number | null = null,
): Promise<number> {
  return db.$transaction(async (tx) => {
    const open = await tx.request_steps.findMany({
      where: {
        document_id: id(documentId),
        status: { in: [STEP_STATUS.Current, STEP_STATUS.Pending] },
      },
      select: { id: true, status: true },
    });

    for (const step of open) {
      await tx.request_steps.update({
        where: { id: step.id },
        data: {
          status:
            step.status === STEP_STATUS.Current ? STEP_STATUS.Forwarded : STEP_STATUS.Skipped,
        },
      });
    }

    const highest = await tx.request_steps.aggregate({
      where: { document_id: id(documentId) },
      _max: { step_order: true },
    });

    const created = await tx.request_steps.create({
      data: {
        document_id: id(documentId),
        step_order: (highest._max.step_order ?? 0) + 1,
        department_id: id(departmentId),
        recipient_id: recipientId === null ? null : id(recipientId),
        action,
        status: STEP_STATUS.Current,
        started_at: now(),
        created_at: now(),
        updated_at: now(),
      },
      select: { id: true },
    });

    return toId(created.id);
  });
}

/**
 * The tracking page as the citizen sees it. Every citizen-facing link (receipt,
 * public lookup, citizen emails) uses this, so a staff member signed in on the
 * same browser still lands on the citizen page rather than the staff desk.
 */
export function citizenTrackingUrl(trackingNumber: string): string {
  return `/track/${encodeURIComponent(trackingNumber)}?view=citizen`;
}
