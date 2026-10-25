import { db, id, toId } from '@/lib/db';
import { now, startOfDay, parseNaive, today } from '@/lib/time';
import { tracking } from '@/lib/config';
import { logOnDocument } from '@/lib/activity';
import { queueMail } from '@/lib/mail/send';
import { assignmentNoticeMail } from '@/lib/mail/assignment-notice';
import { broadcastStatusUpdated } from '@/lib/realtime';
import { logSystemComment } from '@/lib/domain/comments';
import { DocumentStatus, isTerminal, label as statusLabel } from '@/lib/domain/document-status';
import { statusChangeData, statusOf } from '@/lib/domain/document';
import { canAssignWithinDepartment, canViewAll } from '@/lib/domain/scope';
import { needsTriage } from '@/lib/domain/request-review';
import { cancel as cancelBooking, followClaimDate } from '@/lib/domain/lending-desk';
import { notifyMany } from '@/lib/domain/notifications';
import { grantsFor, ROLE_SUPERVISOR, usersWithRole } from '@/lib/auth/rbac';
import { ActionError, finish } from '@/lib/domain/status-actions';
import { notifyOfficeOfProgress } from '@/lib/domain/office-progress';
import { routes } from '@/lib/routes';
import type { Actor } from '@/lib/auth/actor';

/**
 * Staff review lifecycle: a supervisor assigns or denies, the assignee accepts,
 * declines or asks for a revision, and the final sign-off completes the request.
 *
 * Mirrors `status-actions`' logging and broadcasting so the timeline and the
 * live stream stay consistent however a stage was reached.
 */
const SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  status: true,
  assigned_to: true,
  accepted_at: true,
  department_id: true,
  citizen_name: true,
  created_at: true,
  claim_date: true,
} as const;

async function loadOrFail(documentId: number) {
  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: SELECT,
  });

  if (!document) {
    throw new ActionError('That request no longer exists.');
  }

  return document;
}

type DocumentRow = Awaited<ReturnType<typeof loadOrFail>>;

/** Applies a stage change with the shared logging, feed line and broadcast. */
async function applyAndLog(
  document: DocumentRow,
  to: DocumentStatus,
  verb: string,
  actor: Actor,
  note: string | null = null,
): Promise<void> {
  const from = statusOf(document);
  const documentId = toId(document.id);

  await db.documents.update({
    where: { id: document.id },
    data: { ...statusChangeData(to), updated_at: now() },
  });

  await logOnDocument(
    documentId,
    `${verb}: ${statusLabel(from)} → ${statusLabel(to)}`,
    actor.id,
    { from, to, ...(note ? { note } : {}) },
  );

  await broadcastStatusUpdated(documentId);
  await logSystemComment(
    documentId,
    `${verb}: ${statusLabel(from)} → ${statusLabel(to)} (by ${actor.name})${note ? ` — ${note}` : ''}`,
  );
}

/**
 * Supervisor approve = assign the request to a staff member and move it to In
 * Progress. Gated by role rather than the `assign documents` permission row, so
 * it works even on a partially-seeded database.
 */
export async function assign(
  documentId: number,
  actor: Actor | null,
  assigneeId: number,
): Promise<{ message: string }> {
  if (!canViewAll(actor)) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const document = await loadOrFail(documentId);

  const assignee = await db.users.findFirst({
    where: { id: id(assigneeId), deleted_at: null, is_active: true },
    select: { id: true, name: true, email: true, department_id: true },
  });

  if (!assignee) {
    throw new ActionError('That staff member no longer exists.');
  }

  // An assignee without this permission would see a live Advance button on
  // their dashboard that the status endpoint then rejects.
  const grants = await grantsFor(toId(assignee.id));

  if (!grants.permissions.includes('advance documents')) {
    throw new ActionError('That user does not have permission to advance documents.');
  }

  // Guest → Supervisor → Staff: a department head assigns only within their own
  // office (super admins may assign across offices).
  const assigneeDepartment = assignee.department_id === null ? null : toId(assignee.department_id);

  if (!canAssignWithinDepartment(actor!, { department_id: assigneeDepartment })) {
    throw new ActionError('You can only assign staff in your own department.');
  }

  await db.documents.update({
    where: { id: document.id },
    data: {
      assigned_to: assignee.id,
      assigned_by: id(actor!.id),
      assigned_at: now(),
      // A fresh assignment is unaccepted, even if the previous assignee had
      // accepted it — reassignment must not inherit their acceptance.
      accepted_at: null,
      updated_at: now(),
    },
  });

  await broadcastStatusUpdated(documentId);
  await logOnDocument(documentId, `Assigned to ${assignee.name}`, actor!.id);
  await logSystemComment(documentId, `Assigned to ${assignee.name} — awaiting staff acceptance`);

  if (assignee.email && tracking.notifyStaffOnAssignment) {
    queueMail(
      assignmentNoticeMail({
        to: assignee.email,
        assigneeName: assignee.name,
        trackingNumber: document.tracking_number,
        documentType: document.document_type,
      }),
    );

    await logOnDocument(documentId, `Emailed AssignmentNotice to ${assignee.name}`, actor!.id);
  }

  // Header-bell ping for the assignee.
  await notifyMany([toId(assignee.id)], {
    title: `New assignment: ${document.tracking_number}`,
    body: `${actor!.name} assigned you ${document.document_type}.`,
    url: routes.staffDashboard({ open: document.tracking_number }),
    tracking_number: document.tracking_number,
    event: 'assigned',
  });

  return { message: `Assigned ${document.tracking_number} to ${assignee.name}.` };
}

/** Supervisor deny = reject the request (terminal). */
export async function deny(
  documentId: number,
  actor: Actor | null,
  reason: string | null,
): Promise<{ message: string }> {
  if (!canViewAll(actor)) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const document = await loadOrFail(documentId);

  if (isTerminal(statusOf(document))) {
    return { message: 'This request is already closed.' };
  }

  const trimmed = (reason ?? '').trim() || null;
  const from = statusOf(document);

  await db.documents.update({
    where: { id: document.id },
    data: {
      ...statusChangeData(DocumentStatus.Denied),
      ...(trimmed ? { remarks: trimmed } : {}),
      // Attribute the decision so the citizen page can name who denied it.
      decided_by: id(actor!.id),
      decided_at: now(),
      updated_at: now(),
    },
  });

  await logOnDocument(
    documentId,
    `Denied request${trimmed ? `: ${trimmed}` : ''}`,
    actor!.id,
    { from, to: DocumentStatus.Denied },
  );

  await broadcastStatusUpdated(documentId);
  await logSystemComment(documentId, `Denied by ${actor!.name}${trimmed ? ` — ${trimmed}` : ''}`);

  // A denied request holds no chairs and no venue: free them for others.
  // Quietly — the denial itself is what the citizen hears about.
  const booking = await db.bookings.findFirst({
    where: { document_id: document.id },
    select: { id: true, status: true, released_at: true },
  });

  if (booking && booking.status !== 'cancelled' && booking.released_at === null) {
    await cancelBooking(toId(booking.id), false);
  }

  return { message: `${document.tracking_number} denied.` };
}

/**
 * Clicking "Review" used to move In Progress → In Review. That stage is retired
 * (Pending → Ongoing → Done), so opening a request no longer changes it.
 */
export async function openReview(
  documentId: number,
  actor: Actor | null,
): Promise<{ status: string; statusLabel: string }> {
  const document = await loadOrFail(documentId);
  authorizeAssignedOrAdmin(document, actor);

  return { status: document.status, statusLabel: statusLabel(statusOf(document)) };
}

/** Final staff sign-off: Ongoing → Done. Kept for callers of the old name. */
export async function complete(
  documentId: number,
  actor: Actor | null,
  note: string | null = null,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);

  return finish(documentId, actor, { expectedStatus: document.status, note });
}

/**
 * Staff claims an unassigned request from the department pool ("Open Requests").
 * Locks the record to their user_id, sets accepted_at, and advances Pending → Ongoing.
 */
export async function claim(
  documentId: number,
  actor: Actor | null,
): Promise<{ message: string }> {
  if (!actor) {
    throw new ActionError('Please sign in again.', 403);
  }

  const canClaim =
    actor.can('accept documents') ||
    actor.can('assign documents') ||
    actor.can('manage system') ||
    actor.hasRole('staff');

  if (!canClaim) {
    throw new ActionError('You do not have permission to claim requests.', 403);
  }

  const document = await loadOrFail(documentId);

  if (document.assigned_to !== null) {
    throw new ActionError('This request has already been claimed or assigned.');
  }

  if (isTerminal(statusOf(document))) {
    throw new ActionError('This request is already closed.');
  }

  // Department scope check: staff may only claim within their department unless they have global access
  const docDept = document.department_id === null ? null : toId(document.department_id);
  if (
    actor.departmentId !== null &&
    docDept !== null &&
    actor.departmentId !== docDept &&
    !canViewAll(actor)
  ) {
    throw new ActionError('You can only claim requests within your department.', 403);
  }

  const stamp = now();
  const from = statusOf(document);
  const shouldMoveToInProgress = from === DocumentStatus.Pending;

  // Atomic update to prevent race conditions if multiple staff members click Claim at the same instant
  const { count } = await db.documents.updateMany({
    where: { id: document.id, assigned_to: null },
    data: {
      assigned_to: id(actor.id),
      assigned_by: id(actor.id),
      assigned_at: stamp,
      accepted_at: stamp,
      ...(shouldMoveToInProgress ? statusChangeData(DocumentStatus.InProgress) : {}),
      updated_at: stamp,
    },
  });

  if (count === 0) {
    throw new ActionError('This request was just claimed by another staff member.');
  }

  const to = shouldMoveToInProgress ? DocumentStatus.InProgress : from;
  await logOnDocument(
    documentId,
    `Claimed and started processing: ${statusLabel(from)} → ${statusLabel(to)}`,
    actor.id,
    { from, to },
  );

  await broadcastStatusUpdated(documentId);
  await logSystemComment(
    documentId,
    `Claimed from department pool by ${actor.name} — now Ongoing`,
  );

  // Notify office supervisors that the request was claimed and is now Ongoing
  await notifyOfficeOfProgress(document, actor, 'accepted');

  return { message: `Claimed ${document.tracking_number}. It is now in your Ongoing requests.` };
}

/** Staff accepts a supervisor assignment: Pending → In Progress. */
export async function acceptAssignment(
  documentId: number,
  actor: Actor | null,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  authorizeAssignedStaff(document, actor);

  if (!needsTriage(document)) {
    throw new ActionError('This request is no longer awaiting acceptance.');
  }

  if (statusOf(document) === DocumentStatus.Pending) {
    await applyAndLog(document, DocumentStatus.InProgress, 'Accepted assignment', actor!);
  } else {
    await logOnDocument(documentId, 'Accepted assignment', actor!.id);
    await logSystemComment(documentId, `Accepted assignment by ${actor!.name}`);
  }

  await db.documents.update({
    where: { id: document.id },
    data: { accepted_at: now(), updated_at: now() },
  });

  await broadcastStatusUpdated(documentId);

  // CTCAO: accepting tells the office's supervisors it is now Ongoing.
  await notifyOfficeOfProgress(document, actor!, 'accepted');

  return { message: `Accepted ${document.tracking_number}. It is now Ongoing.` };
}

/** Staff declines an assignment — returns it to the supervisor queue. */
export async function declineAssignment(
  documentId: number,
  actor: Actor | null,
  reason: string | null,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  authorizeAssignedStaff(document, actor);

  if (!needsTriage(document)) {
    throw new ActionError('This request is no longer awaiting acceptance.');
  }

  const trimmed = (reason ?? '').trim() || null;

  await db.documents.update({
    where: { id: document.id },
    data: {
      assigned_to: null,
      assigned_by: null,
      assigned_at: null,
      accepted_at: null,
      // Back to the unassigned queue it came from.
      ...(statusOf(document) === DocumentStatus.InProgress
        ? statusChangeData(DocumentStatus.Pending)
        : {}),
      updated_at: now(),
    },
  });

  await logOnDocument(
    documentId,
    `Declined assignment${trimmed ? `: ${trimmed}` : ''}`,
    actor!.id,
  );
  await logSystemComment(
    documentId,
    `Assignment declined by ${actor!.name}${trimmed ? ` — ${trimmed}` : ''}`,
  );
  await broadcastStatusUpdated(documentId);

  // Header-bell ping for supervisors: the request is back in the queue.
  const supervisors = await usersWithRole(
    ROLE_SUPERVISOR,
    document.department_id === null ? null : toId(document.department_id),
  );

  await notifyMany(
    supervisors.map((user) => user.id).filter((userId) => userId !== actor!.id),
    {
      title: `Assignment declined: ${document.tracking_number}`,
      body: `${actor!.name} declined it${trimmed ? ` — ${trimmed}` : ''}.`,
      url: routes.dashboard({ tab: 'pending', q: document.tracking_number }),
      tracking_number: document.tracking_number,
      event: 'declined',
    },
  );

  return { message: `Declined ${document.tracking_number}.` };
}

/** Staff sends the request back for citizen revision. */
export async function requestRevision(
  documentId: number,
  actor: Actor | null,
  reason: string,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  authorizeAssignedStaff(document, actor);

  if (!needsTriage(document)) {
    throw new ActionError('This request cannot be returned for revision at its current stage.');
  }

  const trimmed = (reason ?? '').trim();

  if (trimmed === '') {
    throw new ActionError('Say what the citizen needs to fix.');
  }

  const from = statusOf(document);

  await db.documents.update({
    where: { id: document.id },
    data: {
      ...statusChangeData(DocumentStatus.Returned),
      remarks: trimmed,
      updated_at: now(),
    },
  });

  await logOnDocument(documentId, `Returned for revision: ${trimmed}`, actor!.id, {
    from,
    to: DocumentStatus.Returned,
  });

  await broadcastStatusUpdated(documentId);
  await logSystemComment(documentId, `Returned for revision by ${actor!.name} — ${trimmed}`);

  return { message: `${document.tracking_number} returned for revision.` };
}

/**
 * Staff set or moved the claiming date from the Review modal's calendar. The
 * booking follows it, so the calendar and the request never show two different
 * days.
 */
export async function setClaimDate(
  documentId: number,
  actor: Actor | null,
  claimDate: string,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  authorizeAssignedOrAdmin(document, actor);

  const parsed = parseNaive(claimDate);

  if (!parsed) {
    throw new ActionError('Pick a claiming date.');
  }

  const day = startOfDay(parsed);

  if (day.getTime() < today().getTime()) {
    throw new ActionError('The claiming date cannot be in the past.');
  }

  // Refused with nothing saved if an approved booking's stock or venue cannot
  // cover the new dates.
  const refusal = await followClaimDate(documentId, day);

  if (refusal) {
    throw new ActionError(refusal);
  }

  await db.documents.update({
    where: { id: document.id },
    data: { claim_date: day, updated_at: now() },
  });

  await logOnDocument(documentId, `Claiming date set to ${claimDate}`, actor!.id);

  return { message: 'Claiming date saved.' };
}

function authorizeAssignedOrAdmin(
  document: DocumentRow,
  actor: Actor | null,
): asserts actor is Actor {
  const assignedTo = document.assigned_to === null ? null : toId(document.assigned_to);
  const allowed =
    actor !== null &&
    (actor.can('manage system') ||
      actor.can('assign documents') ||
      (actor.can('advance documents') && assignedTo === actor.id));

  if (!allowed) {
    throw new ActionError('Only the assigned staff member or an admin can act on this request.', 403);
  }
}

function authorizeAssignedStaff(
  document: DocumentRow,
  actor: Actor | null,
): asserts actor is Actor {
  const assignedTo = document.assigned_to === null ? null : toId(document.assigned_to);

  // Triage is the assignee's own decision — an admin cannot accept on their
  // behalf, because accepting is a statement that this person will do the work.
  if (actor === null || assignedTo !== actor.id) {
    throw new ActionError('Only the assigned staff member can respond to this assignment.', 403);
  }
}
