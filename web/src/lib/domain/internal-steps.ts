import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { logOnDocument } from '@/lib/activity';
import { logSystemComment } from '@/lib/domain/comments';
import { notify, notifyMany } from '@/lib/domain/notifications';
import { putPrivate, privateExists, readPrivate, storePrivate } from '@/lib/storage';
import { ActionError } from '@/lib/domain/status-actions';
import { DocumentStatus } from '@/lib/domain/document-status';
import { STEP_STATUS, type RequestStepRow } from '@/lib/domain/request-step';
import {
  canActOnCurrentStep,
  statusChangeData,
  canBeRevisedBy,
  currentRequestStep,
  currentStepHasCustody,
  isSoftCopy,
  statusOf,
} from '@/lib/domain/document';
import { trackingNumber as decodeScan, FOREIGN_CODE_MESSAGE } from '@/lib/domain/scanned-code';
import { internalOutcome, tellTheHop } from '@/lib/domain/internal-events';
import { isRecipientAt } from '@/lib/domain/internal-request-form';
import { checkPaperScan } from '@/lib/domain/upload-rules';
import { addOfficeStamp, cleanStampPlacement } from '@/lib/domain/office-stamp';
import type { StampPlacement } from '@/lib/domain/qr-stamp';
import type { Actor } from '@/lib/auth/actor';

/**
 * Hop actions on an internal request's endorsement chain.
 *
 * Port of `RequestStepController`. Two rules carry the whole flow:
 *
 *  - only someone who may act on the current hop may decide, and only after the
 *    office has taken custody — the QR scan that proves the paper is on the
 *    desk before any digital sign-off;
 *  - approving never completes a request, however long the chain grows. Only
 *    "Mark as done" finishes one, because nothing else knows which office is
 *    the last to handle the paper.
 */
const REMARKS_LIMIT = 500;

const STEP_DOCUMENT_SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  purpose: true,
  status: true,
  origin: true,
  has_hard_copy: true,
  created_by: true,
  requesting_department_id: true,
} as const;

type StepDocument = {
  id: bigint;
  tracking_number: string;
  document_type: string;
  purpose: string | null;
  status: string;
  origin: string;
  has_hard_copy: boolean;
  created_by: bigint | null;
  requesting_department_id: bigint | null;
};

export type StepDecisionInput = {
  remarks?: string | null;
  /** The decoded QR payload from the folder's sticker. */
  documentScan?: string | null;
  /** Why it could not be scanned — audited, and only accepted instead of a scan. */
  scanOverrideReason?: string | null;
  /** Where the paper goes next (approve only, when no hop was queued at filing). */
  nextDepartmentId?: number | null;
  /** Who receives it there (approve only, soft copies). */
  nextRecipientId?: number | null;
  /** Where to put this office's RECEIVED stamp on the file (approve only, optional). */
  stamp?: StampPlacement | null;
};

async function loadOrFail(documentId: number): Promise<StepDocument> {
  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: STEP_DOCUMENT_SELECT,
  });

  if (!document) {
    throw new ActionError('That request no longer exists.');
  }

  return document;
}

/**
 * Gate + resolve the current hop. Refused unless this user holds it, and the
 * endorsement stays locked until the office has taken custody of the folder for
 * THIS hop — custody recorded at an earlier office does not carry over.
 */
async function authorizeAction(
  document: StepDocument,
  actor: Actor | null,
): Promise<RequestStepRow> {
  const documentId = toId(document.id);
  const step = await currentRequestStep(documentId);

  if (!canActOnCurrentStep(document, step, actor)) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  if (!(await currentStepHasCustody(documentId, step))) {
    throw new ActionError(
      isSoftCopy(document)
        ? 'Receive this request before acting on it — scan its QR to pick it up first.'
        : "Scan the folder's QR to take custody before acting on this request — the paper must be physically in your office first.",
    );
  }

  // `canActOnCurrentStep` already refused a null step.
  return step as RequestStepRow;
}

function requireRemarks(input: StepDecisionInput, required: boolean): string | null {
  const remarks = (input.remarks ?? '').trim();

  if (required && remarks === '') {
    throw new ActionError('Explain the decision so the filing office knows what to fix.');
  }

  if (remarks.length > REMARKS_LIMIT) {
    throw new ActionError(`Keep remarks to ${REMARKS_LIMIT} characters.`);
  }

  return remarks === '' ? null : remarks;
}

/**
 * Every hop decision is confirmed against the folder itself: the QR sticker in
 * front of the signer. Only codes this system issued count, and the code must
 * be THIS request's, so a decision cannot be recorded against a folder that is
 * not in the room.
 *
 * A written override is accepted when the QR genuinely cannot be read — a torn
 * sticker, or far more often a machine with no working camera. Without that
 * escape hatch a broken webcam halts the office, which is worse than a logged
 * manual confirmation; so it is audited and mirrored into the request's feed
 * rather than silent.
 */
async function confirmScannedFolder(
  document: StepDocument,
  actor: Actor,
  input: StepDecisionInput,
): Promise<void> {
  if (isSoftCopy(document)) {
    return;
  }

  const override = (input.scanOverrideReason ?? '').trim();
  const scan = (input.documentScan ?? '').trim();

  if (override !== '' && scan === '') {
    if (override.length < 10) {
      throw new ActionError('Explain briefly why the QR could not be scanned.');
    }

    await logOnDocument(
      toId(document.id),
      `Decision confirmed WITHOUT a QR scan — reason: ${override}`,
      actor.id,
      { reason: override, tracking_number: document.tracking_number },
    );

    await logSystemComment(
      toId(document.id),
      `${actor.name} confirmed a decision without scanning the QR. Reason: ${override}`,
    );

    return;
  }

  if (scan === '') {
    throw new ActionError(
      "Scan this request's QR code to confirm the decision, or record why you could not.",
    );
  }

  const scanned = decodeScan(scan);

  if (scanned === null) {
    throw new ActionError(
      `Scan the QR on this request to confirm the decision. ${FOREIGN_CODE_MESSAGE}`,
    );
  }

  if (scanned !== document.tracking_number.toUpperCase()) {
    throw new ActionError(
      `That QR belongs to a different request (${scanned}). Scan the sticker on this folder.`,
    );
  }
}

/** The log line, the request's own feed, and the filer's bell. */
async function recordOutcome(
  document: StepDocument,
  step: RequestStepRow,
  actor: Actor,
  summary: string,
): Promise<void> {
  const documentId = toId(document.id);

  await logOnDocument(documentId, summary, actor.id, {
    step_id: step.id,
    department: step.departmentName,
    action: step.action,
  });

  await logSystemComment(documentId, summary);

  if (document.created_by !== null && toId(document.created_by) !== actor.id) {
    await notify(
      toId(document.created_by),
      internalOutcome({ ...document, id: documentId }, summary),
    );
  }
}

async function closeStep(
  stepId: number,
  status: string,
  actor: Actor,
  remarks: string | null,
  signaturePath: string | null = null,
): Promise<void> {
  await db.request_steps.update({
    where: { id: id(stepId) },
    data: {
      status,
      acted_by: id(actor.id),
      acted_at: now(),
      remarks,
      ...(signaturePath === null ? {} : { signature_path: signaturePath }),
      updated_at: now(),
    },
  });
}

/** Everything still queued behind this hop is moot once the request closes. */
async function skipOpenSteps(documentId: number): Promise<void> {
  await db.request_steps.updateMany({
    where: {
      document_id: id(documentId),
      status: { in: [STEP_STATUS.Pending, STEP_STATUS.Current] },
    },
    data: { status: STEP_STATUS.Skipped, updated_at: now() },
  });
}

async function applyStatus(
  documentId: number,
  status: DocumentStatus,
  decidedBy: number | null = null,
): Promise<void> {
  await db.documents.update({
    where: { id: id(documentId) },
    data: {
      ...statusChangeData(status),
      ...(decidedBy === null ? {} : { decided_by: id(decidedBy), decided_at: now() }),
      updated_at: now(),
    },
  });
}

/**
 * Approve this hop and pass the paper on.
 *
 * A hop queued at filing already says where it goes; past that, the office
 * holding it chooses, which is how the chain grows — and how the Office of the
 * Mayor sends a request filed through it to the office it is for. "Nowhere" is
 * not an empty approval — it is the Done action.
 */
export async function approve(
  documentId: number,
  actor: Actor | null,
  input: StepDecisionInput,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const step = await authorizeAction(document, actor);
  const signer = actor as Actor;
  const remarks = requireRemarks(input, false);

  const queued = await db.request_steps.findFirst({
    where: { document_id: document.id, status: STEP_STATUS.Pending },
    orderBy: [{ step_order: 'asc' }, { id: 'asc' }],
    select: { id: true, department_id: true, action: true, recipient_id: true },
  });

  const soft = isSoftCopy(document);
  const nextDepartmentId = input.nextDepartmentId ?? null;
  const nextRecipientId = input.nextRecipientId ?? null;

  if (queued === null) {
    if (nextDepartmentId === null) {
      throw new ActionError(
        'Choose the office this request goes to next — or use Done if your office is the last to handle it.',
      );
    }

    if (nextDepartmentId === step.department_id) {
      throw new ActionError('Pick a different office: the request is already with yours.');
    }

    const office = await db.departments.findFirst({
      where: { id: id(nextDepartmentId), is_active: true },
      select: { id: true },
    });

    if (!office) {
      throw new ActionError('That office is no longer active. Choose another.');
    }

    if (soft) {
      if (nextRecipientId === null) {
        throw new ActionError(
          'This request has no paper copy. Choose who in the next office receives it.',
        );
      }

      if (!(await isRecipientAt(nextRecipientId, nextDepartmentId))) {
        throw new ActionError('Choose someone who works in the next office.');
      }
    }
  }

  await confirmScannedFolder(document, signer, input);

  // Freeze the signature as of this approval — re-registering later must never
  // rewrite what was affixed here.
  const signature = await freezeSignature(document, step, signer);

  await closeStep(step.id, STEP_STATUS.Approved, signer, remarks, signature);

  // The office's RECEIVED stamp, when it chose to add one. Never blocks the
  // approval: a failed stamp is logged and the request moves on.
  const stampPlacement = cleanStampPlacement(input.stamp);

  if (stampPlacement) {
    const stamped = await addOfficeStamp(toId(document.id), step, signer, stampPlacement, signature);

    if (stamped) {
      await logSystemComment(
        toId(document.id),
        `${step.departmentName} stamped the file RECEIVED (${signer.name}).`,
      );
    }
  }

  if (statusOf(document) === DocumentStatus.Pending) {
    await applyStatus(toId(document.id), DocumentStatus.InProgress);
  }

  const next = queued
    ? await openQueuedStep(queued.id)
    : await createNextStep(
        toId(document.id),
        nextDepartmentId as number,
        soft ? nextRecipientId : null,
      );

  await tellTheHop({ ...document, id: toId(document.id) }, next, signer.id);

  await recordOutcome(
    document,
    step,
    signer,
    `${step.action} approved at ${step.departmentName} by ${signer.name} — forwarded to ${next.departmentName}`,
  );

  return { message: `Approved — the request moves on to ${next.departmentName}.` };
}

/** Registering a signature is a precondition for approving, not a nicety. */
async function freezeSignature(
  document: StepDocument,
  step: RequestStepRow,
  actor: Actor,
): Promise<string> {
  const user = await db.users.findFirst({
    where: { id: id(actor.id) },
    select: { signature_path: true },
  });

  const source = user?.signature_path ?? null;

  if (!(await privateExists(source))) {
    throw new ActionError(
      'Register your e-signature on your Profile page before approving requests.',
    );
  }

  const bytes = await readPrivate(source as string);

  if (bytes === null) {
    throw new ActionError(
      'Register your e-signature on your Profile page before approving requests.',
    );
  }

  const copy = `request-signatures/${document.tracking_number}-step-${step.id}.png`;

  await putPrivate(copy, bytes, 'image/png');

  return copy;
}

type NextStep = { id: number; department_id: number; recipient_id: number | null; action: string; departmentName: string };

async function openQueuedStep(stepId: bigint): Promise<NextStep> {
  const step = await db.request_steps.update({
    where: { id: stepId },
    data: { status: STEP_STATUS.Current, started_at: now(), updated_at: now() },
    select: {
      id: true,
      department_id: true,
      recipient_id: true,
      action: true,
      department: { select: { name: true } },
    },
  });

  return {
    id: toId(step.id),
    department_id: toId(step.department_id),
    recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
    action: step.action,
    departmentName: step.department.name,
  };
}

async function createNextStep(
  documentId: number,
  departmentId: number,
  recipientId: number | null,
): Promise<NextStep> {
  const highest = await db.request_steps.aggregate({
    where: { document_id: id(documentId) },
    _max: { step_order: true },
  });

  const step = await db.request_steps.create({
    data: {
      document_id: id(documentId),
      step_order: (highest._max.step_order ?? 0) + 1,
      department_id: id(departmentId),
      recipient_id: recipientId === null ? null : id(recipientId),
      action: 'Review and action',
      status: STEP_STATUS.Current,
      started_at: now(),
      created_at: now(),
      updated_at: now(),
    },
    select: {
      id: true,
      department_id: true,
      recipient_id: true,
      action: true,
      department: { select: { name: true } },
    },
  });

  return {
    id: toId(step.id),
    department_id: toId(step.department_id),
    recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
    action: step.action,
    departmentName: step.department.name,
  };
}

/**
 * The office holding the request declares the work finished. This is the only
 * path to Completed: the chain otherwise keeps growing as offices scan the
 * folder, because nothing else knows which hop is the last one.
 */
export async function complete(
  documentId: number,
  actor: Actor | null,
  input: StepDecisionInput,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const step = await authorizeAction(document, actor);
  const signer = actor as Actor;
  const remarks = requireRemarks(input, false);

  // The route queued at filing (Action Mamamayan → Office of the Mayor → the
  // destination) must be walked to the end: an office on the way approves it
  // on, it never closes it early.
  const queued = await db.request_steps.findFirst({
    where: { document_id: document.id, status: STEP_STATUS.Pending },
    orderBy: [{ step_order: 'asc' }, { id: 'asc' }],
    select: { department: { select: { name: true } } },
  });

  if (queued) {
    throw new ActionError(
      `This request still goes to ${queued.department.name}. Approve it to pass it on — it can be marked done at the end of its route.`,
    );
  }

  await confirmScannedFolder(document, signer, input);

  await closeStep(step.id, STEP_STATUS.Approved, signer, remarks);
  await skipOpenSteps(toId(document.id));
  await applyStatus(toId(document.id), DocumentStatus.Completed, signer.id);

  await recordOutcome(
    document,
    step,
    signer,
    `Marked done at ${step.departmentName} by ${signer.name} — the request is complete${
      remarks ? `: ${remarks}` : '.'
    }`,
  );

  return { message: 'Marked as done — the request is complete.' };
}

export async function deny(
  documentId: number,
  actor: Actor | null,
  input: StepDecisionInput,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const step = await authorizeAction(document, actor);
  const signer = actor as Actor;
  const remarks = requireRemarks(input, true) as string;

  await confirmScannedFolder(document, signer, input);

  await closeStep(step.id, STEP_STATUS.Denied, signer, remarks);
  // Attribute the decision so the tracker can name who denied it.
  await applyStatus(toId(document.id), DocumentStatus.Denied, signer.id);

  await recordOutcome(
    document,
    step,
    signer,
    `Denied at ${step.departmentName} by ${signer.name}: ${remarks}`,
  );

  return { message: 'Request denied. The filing office has been notified.' };
}

export async function returnToRequester(
  documentId: number,
  actor: Actor | null,
  input: StepDecisionInput,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const step = await authorizeAction(document, actor);
  const signer = actor as Actor;
  const remarks = requireRemarks(input, true) as string;

  await confirmScannedFolder(document, signer, input);

  await closeStep(step.id, STEP_STATUS.Returned, signer, remarks);
  await applyStatus(toId(document.id), DocumentStatus.Returned);

  await recordOutcome(
    document,
    step,
    signer,
    `Returned for revision at ${step.departmentName} by ${signer.name}: ${remarks}`,
  );

  return { message: 'Request returned to the filing office for revision.' };
}

/**
 * Send a returned request back to the office that returned it, saying what
 * changed. That office receives it again before acting: the folder is scanned
 * in, or a soft copy is picked up by the person it goes to.
 */
export async function resend(
  documentId: number,
  actor: Actor | null,
  input: { note: string; paperScan?: File | null },
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const filer = await requireReviser(document, actor);
  const note = (input.note ?? '').trim();

  if (note === '') {
    throw new ActionError('Say what you changed, so the office knows what to look at.');
  }

  if (note.length > REMARKS_LIMIT) {
    throw new ActionError(`Keep the note to ${REMARKS_LIMIT} characters.`);
  }

  const returned = await db.request_steps.findFirst({
    where: { document_id: document.id, status: STEP_STATUS.Returned },
    orderBy: [{ acted_at: 'desc' }, { id: 'desc' }],
    select: {
      department_id: true,
      recipient_id: true,
      acted_by: true,
      department: { select: { id: true, name: true, is_active: true } },
    },
  });

  if (!returned || !returned.department.is_active) {
    throw new ActionError(
      'The office that returned this request is no longer active. File it again to send it somewhere else.',
    );
  }

  const departmentId = toId(returned.department_id);

  /**
   * A soft copy goes to a person: whoever it went to before, or failing that
   * whoever returned it, while they still work there. Otherwise the office's
   * supervisor receives it.
   */
  let recipientId: number | null = null;

  if (isSoftCopy(document)) {
    for (const candidate of [returned.recipient_id, returned.acted_by]) {
      if (candidate === null) {
        continue;
      }

      if (await isRecipientAt(toId(candidate), departmentId)) {
        recipientId = toId(candidate);
        break;
      }
    }
  }

  // The revision is reviewed BEFORE the rest of the route queued at filing
  // (the Office of the Mayor, the destination), so it takes the place of the
  // first waiting hop and those move one down — the trail stays in route order.
  const firstWaiting = await db.request_steps.aggregate({
    where: { document_id: document.id, status: STEP_STATUS.Pending },
    _min: { step_order: true },
  });
  const highest = await db.request_steps.aggregate({
    where: { document_id: document.id },
    _max: { step_order: true },
  });
  let stepOrder = (highest._max.step_order ?? 0) + 1;

  if (firstWaiting._min.step_order !== null && firstWaiting._min.step_order !== undefined) {
    stepOrder = firstWaiting._min.step_order;
    await db.request_steps.updateMany({
      where: { document_id: document.id, status: STEP_STATUS.Pending },
      data: { step_order: { increment: 1 }, updated_at: now() },
    });
  }

  const step = await db.request_steps.create({
    data: {
      document_id: document.id,
      step_order: stepOrder,
      department_id: id(departmentId),
      recipient_id: recipientId === null ? null : id(recipientId),
      action: 'Review the revision',
      status: STEP_STATUS.Current,
      started_at: now(),
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true, department_id: true, recipient_id: true, action: true },
  });

  // A chain that already carries an approval is in progress, not pending.
  const approvedBefore = await db.request_steps.count({
    where: { document_id: document.id, status: STEP_STATUS.Approved },
  });

  await applyStatus(
    toId(document.id),
    approvedBefore > 0 ? DocumentStatus.InProgress : DocumentStatus.Pending,
  );

  // The returned folder came back to the filing office; the trail shows it
  // there until the office it goes to scans it in.
  if (!isSoftCopy(document)) {
    await db.document_custody_events.create({
      data: {
        document_id: document.id,
        user_id: id(filer.id),
        capture_method: 'manual',
        override_reason: 'Filing office holds the revised paper.',
        created_at: now(),
        updated_at: now(),
      },
    });
  }

  // The revised paper is added to the request's files; the original stays, so
  // the office can see what changed rather than only the latest version.
  if (input.paperScan) {
    const problem = checkPaperScan(input.paperScan);

    if (problem !== null) {
      throw new ActionError(problem);
    }

    const path = await storePrivate(input.paperScan, 'document-attachments');
    const highestSort = await db.document_attachments.aggregate({
      where: { document_id: document.id },
      _max: { sort_order: true },
    });

    await db.document_attachments.create({
      data: {
        document_id: document.id,
        file_path: path,
        uploaded_by: id(filer.id),
        sort_order: (highestSort._max.sort_order ?? 0) + 1,
        created_at: now(),
        updated_at: now(),
      },
    });
  }

  const summary = `Resent to ${returned.department.name} by ${filer.name} after revision: ${note}`;

  await logOnDocument(toId(document.id), summary, filer.id, {
    department: returned.department.name,
    note,
  });
  await logSystemComment(toId(document.id), summary);

  await tellTheHop(
    { ...document, id: toId(document.id) },
    {
      department_id: toId(step.department_id),
      recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
      action: step.action,
    },
    filer.id,
  );

  return {
    message: `Resent to ${returned.department.name}. They receive it again before acting on it.`,
  };
}

/**
 * The filing office closes a returned request instead of revising it. Final,
 * like a denial, but the filer's own choice — and counted apart from denials.
 */
export async function withdraw(
  documentId: number,
  actor: Actor | null,
  reason: string | null = null,
): Promise<{ message: string }> {
  const document = await loadOrFail(documentId);
  const filer = await requireReviser(document, actor);
  const note = (reason ?? '').trim();

  if (note.length > REMARKS_LIMIT) {
    throw new ActionError(`Keep the reason to ${REMARKS_LIMIT} characters.`);
  }

  await skipOpenSteps(toId(document.id));
  await applyStatus(toId(document.id), DocumentStatus.Withdrawn, filer.id);

  const office = document.requesting_department_id
    ? await db.departments.findFirst({
        where: { id: document.requesting_department_id },
        select: { name: true },
      })
    : null;

  const summary = `Withdrawn by ${filer.name} (${office?.name ?? 'filing office'})${
    note ? `: ${note}` : '.'
  }`;

  await logOnDocument(toId(document.id), summary, filer.id);
  await logSystemComment(toId(document.id), summary);

  // Whoever returned it stops waiting for a revision.
  const returned = await db.request_steps.findFirst({
    where: { document_id: document.id, status: STEP_STATUS.Returned },
    orderBy: [{ acted_at: 'desc' }, { id: 'desc' }],
    select: { acted_by: true },
  });

  if (returned?.acted_by && toId(returned.acted_by) !== filer.id) {
    await notifyMany(
      [toId(returned.acted_by)],
      internalOutcome({ ...document, id: toId(document.id) }, summary),
    );
  }

  return { message: 'Withdrawn — the request is closed.' };
}

/** Only the filing office revises a returned request. */
async function requireReviser(document: StepDocument, actor: Actor | null): Promise<Actor> {
  const allowed = canBeRevisedBy(
    {
      origin: document.origin,
      status: document.status,
      created_by: document.created_by === null ? null : toId(document.created_by),
      requesting_department_id:
        document.requesting_department_id === null
          ? null
          : toId(document.requesting_department_id),
    },
    actor,
  );

  if (!allowed || !actor) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  return actor;
}
