import { db, id, toId } from '@/lib/db';
import { formatNaive, now, parseNaive, startOfDay, today } from '@/lib/time';
import { tracking } from '@/lib/config';
import { logOnDocument } from '@/lib/activity';
import { queueMail } from '@/lib/mail/send';
import { statusUpdatedMail, actionNeededMail } from '@/lib/mail/status-updated';
import { broadcastStatusUpdated } from '@/lib/realtime';
import { AUTHOR, VISIBILITY, logSystemComment, postComment } from '@/lib/domain/comments';
import {
  DocumentStatus,
  fromLoose,
  isTerminal,
  label as statusLabel,
  next as nextStatus,
  previous as previousStatus,
} from '@/lib/domain/document-status';
import { BLOCKED_BY, canBeAdvancedBy, statusChangeData, statusOf } from '@/lib/domain/document';
import { GateError, assertExpectedStatus, validate } from '@/lib/domain/status-gate';
import { followClaimDate } from '@/lib/domain/lending-desk';
import type { Actor } from '@/lib/auth/actor';

/**
 * Manual status progression. The document's assigned staff member (and admins)
 * move it through the flow with explicit Advance / Move back / Return controls
 * — no scans, no routing decide this.
 */
export class ActionError extends Error {
  constructor(
    message: string,
    public readonly status: 403 | 422 = 422,
  ) {
    super(message);
    this.name = 'ActionError';
  }
}

export type TransitionResult = {
  message: string;
  status: string;
  statusLabel: string;
};

const DOCUMENT_SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  status: true,
  status_before_hold: true,
  status_changed_at: true,
  held_at: true,
  assigned_to: true,
  accepted_at: true,
  citizen_name: true,
  citizen_email: true,
  notify_citizen: true,
  qr_code_path: true,
  remarks: true,
  created_at: true,
} as const;

async function loadOrFail(documentId: number) {
  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: DOCUMENT_SELECT,
  });

  if (!document) {
    throw new ActionError('That request no longer exists.');
  }

  return document;
}

/**
 * Only the assigned staff member or an admin may change a document's stage.
 * `advance documents` covers staff; `assign documents` / `manage system` cover
 * admins (who can act on any document).
 */
function authorizeChange(
  document: { assigned_to: bigint | null },
  actor: Actor | null,
): asserts actor is Actor {
  const assignedTo = document.assigned_to === null ? null : toId(document.assigned_to);

  if (!canBeAdvancedBy({ assigned_to: assignedTo }, actor)) {
    throw new ActionError(
      "Only the assigned staff member or an admin can change this document's status.",
      403,
    );
  }
}

/**
 * Advancing sets the day the citizen collects. It replaced a free-text review
 * note: the same decision, but a date the citizen can act on rather than prose
 * only staff read.
 */
export async function advance(
  documentId: number,
  actor: Actor | null,
  input: { expectedStatus: string; claimDate: string; note?: string | null },
): Promise<TransitionResult> {
  const document = await loadOrFail(documentId);
  authorizeChange(document, actor);
  assertExpectedStatus(document, input.expectedStatus);

  const next = nextStatus(statusOf(document));

  if (next === null) {
    throw new ActionError('This document is already at the final stage.');
  }

  if (next === DocumentStatus.Completed) {
    throw new ActionError(
      'Use Approve on the Requests page to mark this document completed and move it to History.',
    );
  }

  const parsed = parseNaive(input.claimDate);

  if (!parsed) {
    throw new ActionError('Pick the claiming date for the citizen.');
  }

  const claimDate = startOfDay(parsed);

  if (claimDate.getTime() < today().getTime()) {
    throw new ActionError('The claiming date cannot be in the past.');
  }

  // The claiming date is the work evidence the gate used to take as a note, so
  // the stage checks still have something concrete to accept.
  const note =
    (input.note ?? '').trim() || `Claiming date set to ${formatNaive(claimDate, 'M d, Y')}.`;

  // Hard stage gates: unmet requirements block the transition with a
  // per-requirement message.
  await validate({ id: documentId }, next, note);

  // The booking follows the claiming date.
  const refusal = await followClaimDate(documentId, claimDate);

  if (refusal) {
    throw new ActionError(refusal);
  }

  await db.documents.update({
    where: { id: id(documentId) },
    data: { claim_date: claimDate, updated_at: now() },
  });

  // The transition note is a real work note: persist it as an internal staff
  // comment so it shows in the feed and satisfies future gates.
  await postComment({
    documentId,
    body: note,
    authorId: actor.id,
    authorType: AUTHOR.Staff,
    visibility: VISIBILITY.Internal,
  });

  return transition(document, next, 'Advanced', actor, null);
}

export async function revert(
  documentId: number,
  actor: Actor | null,
  input: { expectedStatus: string; reason: string },
): Promise<TransitionResult> {
  const document = await loadOrFail(documentId);
  authorizeChange(document, actor);
  assertExpectedStatus(document, input.expectedStatus);

  // Moving a document backwards always carries a "why" for the audit trail.
  const reason = (input.reason ?? '').trim();

  if (reason === '') {
    throw new ActionError('A reason is required to move a document back.');
  }

  const previous = previousStatus(statusOf(document));

  if (previous === null) {
    throw new ActionError('This document is already at the first stage.');
  }

  return transition(document, previous, 'Moved back', actor, reason);
}

/** Set an explicit stage — used for the off-line "Returned / For Revision" state. */
export async function setStatus(
  documentId: number,
  actor: Actor | null,
  input: { expectedStatus: string; status: string; reason?: string | null },
): Promise<TransitionResult> {
  const document = await loadOrFail(documentId);
  authorizeChange(document, actor);
  assertExpectedStatus(document, input.expectedStatus);

  const target = fromLoose(input.status, DocumentStatus.Pending);

  // On Hold has its own endpoint (it needs a reason); Completed is reached via
  // advance(), so neither may be set here.
  if (
    input.status !== target ||
    target === DocumentStatus.OnHold ||
    target === DocumentStatus.Completed
  ) {
    throw new GateError(['Unknown or disallowed status stage.']);
  }

  const reason = (input.reason ?? '').trim() || null;

  if (target === DocumentStatus.Returned && reason === null) {
    throw new GateError(['A reason is required when returning a document for revision.']);
  }

  // The reason is stored as remarks so the citizen tracking page can show what
  // to fix — mirrors the triage revision path.
  if (reason !== null) {
    await db.documents.update({
      where: { id: id(documentId) },
      data: { remarks: reason, updated_at: now() },
    });
  }

  return transition(document, target, 'Set status', actor, reason);
}

/**
 * Park a blocked document (waiting on the citizen, an external office, or a
 * future date). Requires a reason.
 *
 * The SLA clock PAUSES: `status_changed_at` and the SLA-notified markers are
 * left untouched so the stage resumes cleanly on un-hold, and On Hold has a
 * null SLA budget so the sweep skips it.
 */
export async function hold(
  documentId: number,
  actor: Actor | null,
  input: { holdReason: string; blockedBy: string; holdUntil?: string | null },
): Promise<TransitionResult> {
  const document = await loadOrFail(documentId);
  authorizeChange(document, actor);

  const stage = statusOf(document);

  if (stage === DocumentStatus.OnHold) {
    throw new ActionError('This document is already on hold.');
  }

  if (isTerminal(stage)) {
    throw new ActionError('A completed document cannot be put on hold.');
  }

  const holdReason = (input.holdReason ?? '').trim();

  if (holdReason === '') {
    throw new ActionError('A reason is required to put a document on hold.');
  }

  if (!(BLOCKED_BY as readonly string[]).includes(input.blockedBy)) {
    throw new ActionError('Say who the request is waiting on.');
  }

  const holdUntil = input.holdUntil ? startOfDay(parseNaive(input.holdUntil)!) : null;

  if (holdUntil && holdUntil.getTime() < today().getTime()) {
    throw new ActionError('The resume date cannot be in the past.');
  }

  await db.documents.update({
    where: { id: id(documentId) },
    data: {
      status_before_hold: document.status,
      status: DocumentStatus.OnHold,
      hold_reason: holdReason,
      blocked_by: input.blockedBy,
      hold_until: holdUntil,
      held_at: now(),
      held_by: id(actor.id),
      // Fresh hold = fresh backstop clock; the hold sweep re-nags only once
      // this hold runs past its own deadline.
      hold_reminder_sent_at: null,
      updated_at: now(),
    },
  });

  await logOnDocument(
    documentId,
    `On hold (waiting on ${input.blockedBy}): ${holdReason}`,
    actor.id,
    { blocked_by: input.blockedBy, hold_until: holdUntil ? formatNaive(holdUntil, 'Y-m-d') : null },
  );

  await broadcastStatusUpdated(documentId);
  await logSystemComment(
    documentId,
    `Put on hold by ${actor.name} — waiting on ${input.blockedBy}: ${holdReason}`,
  );

  if (input.blockedBy === 'citizen') {
    await notifyCitizenActionNeeded(document, actor);
  }

  return {
    message: 'Document placed on hold.',
    status: DocumentStatus.OnHold,
    statusLabel: statusLabel(DocumentStatus.OnHold),
  };
}

/**
 * Manually lift a hold (never automatic). Restores the pre-hold stage and
 * resumes the SLA exactly where it paused by shifting `status_changed_at`
 * forward by the hold duration — the hold window is not counted against SLA.
 */
export async function unhold(documentId: number, actor: Actor | null): Promise<TransitionResult> {
  const document = await loadOrFail(documentId);
  authorizeChange(document, actor);

  if (statusOf(document) !== DocumentStatus.OnHold) {
    throw new ActionError('This document is not on hold.');
  }

  const restored = fromLoose(document.status_before_hold, DocumentStatus.InProgress);

  // Resume the stage clock: push the anchor forward by however long the hold
  // lasted, so the paused window is not charged against the stage budget.
  let statusChangedAt = document.status_changed_at;

  if (statusChangedAt && document.held_at) {
    statusChangedAt = new Date(statusChangedAt.getTime() + (now().getTime() - document.held_at.getTime()));
  } else if (!statusChangedAt) {
    statusChangedAt = now();
  }

  await db.documents.update({
    where: { id: id(documentId) },
    data: {
      status: restored,
      status_changed_at: statusChangedAt,
      status_before_hold: null,
      hold_reason: null,
      hold_until: null,
      blocked_by: null,
      held_at: null,
      held_by: null,
      hold_reminder_sent_at: null,
      updated_at: now(),
    },
  });

  await logOnDocument(documentId, `Resumed from hold → ${statusLabel(restored)}`, actor.id);
  await broadcastStatusUpdated(documentId);
  await logSystemComment(
    documentId,
    `Resumed from hold → ${statusLabel(restored)} (by ${actor.name})`,
  );

  return {
    message: `Hold lifted — back to ${statusLabel(restored)}.`,
    status: restored,
    statusLabel: statusLabel(restored),
  };
}

type DocumentRow = Awaited<ReturnType<typeof loadOrFail>>;

async function transition(
  document: DocumentRow,
  to: DocumentStatus,
  verb: string,
  actor: Actor,
  note: string | null,
): Promise<TransitionResult> {
  const from = statusOf(document);
  const documentId = toId(document.id);
  const trimmed = (note ?? '').trim() || null;

  await db.documents.update({
    where: { id: document.id },
    data: { ...statusChangeData(to), updated_at: now() },
  });

  await logOnDocument(
    documentId,
    `${verb}: ${statusLabel(from)} → ${statusLabel(to)}`,
    actor.id,
    { from, to, ...(trimmed ? { note: trimmed } : {}) },
  );

  await broadcastStatusUpdated(documentId);

  // Mirror into the unified per-document feed (staff timeline).
  const line =
    `${verb}: ${statusLabel(from)} → ${statusLabel(to)} (by ${actor.name})` +
    (trimmed ? ` — ${trimmed}` : '');

  await logSystemComment(documentId, line);
  await notifyCitizen(document, to, actor);

  return {
    message: `Status updated to ${statusLabel(to)}.`,
    status: to,
    statusLabel: statusLabel(to),
  };
}

/**
 * Email the citizen about a status change, gated by: the global kill switch,
 * the per-stage toggle, the document's `notify_citizen` opt-out, and a
 * `citizen_email` being present.
 */
async function notifyCitizen(
  document: DocumentRow,
  to: DocumentStatus,
  actor: Actor,
): Promise<void> {
  if (!tracking.notifyCitizen.enabled) {
    return;
  }

  if (!document.notify_citizen || !document.citizen_email) {
    return;
  }

  if (!tracking.notifyCitizen.stages[to]) {
    return;
  }

  queueMail(
    statusUpdatedMail({
      to: document.citizen_email,
      trackingNumber: document.tracking_number,
      citizenName: document.citizen_name,
      stage: to,
    }),
  );

  await logOnDocument(
    toId(document.id),
    `Emailed StatusUpdated (${statusLabel(to)}) to citizen`,
    actor.id,
  );
}

/** "Action needed" email when a hold is blocked on the citizen. Same gate. */
async function notifyCitizenActionNeeded(document: DocumentRow, actor: Actor): Promise<void> {
  if (!tracking.notifyCitizen.enabled) {
    return;
  }

  if (!document.notify_citizen || !document.citizen_email) {
    return;
  }

  if (!tracking.notifyCitizen.stages[DocumentStatus.OnHold]) {
    return;
  }

  queueMail(
    actionNeededMail({
      to: document.citizen_email,
      trackingNumber: document.tracking_number,
      citizenName: document.citizen_name,
    }),
  );

  await logOnDocument(toId(document.id), 'Emailed ActionNeeded (on hold) to citizen', actor.id);
}
