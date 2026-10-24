import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { logOnDocument } from '@/lib/activity';
import { logSystemComment } from '@/lib/domain/comments';
import { notify } from '@/lib/domain/notifications';
import { ActionError } from '@/lib/domain/status-actions';
import { trackingNumber as decodeScan, FOREIGN_CODE_MESSAGE } from '@/lib/domain/scanned-code';
import {
  appendEndorsementStep,
  canActOnCurrentStep,
  canReceiveCurrentStep,
  currentRequestStep,
  currentStepHasCustody,
  isInternal,
  isSoftCopy,
  statusOf,
} from '@/lib/domain/document';
import { isTerminal } from '@/lib/domain/document-status';
import { internalReceived } from '@/lib/domain/internal-events';
import { canAccessDocument } from '@/lib/domain/scope';
import type { Actor } from '@/lib/auth/actor';

/**
 * The physical custody trail. Scanning a folder's QR (or recording it by hand)
 * says the signed-in staff member now holds the paper. It never changes the
 * document's status — but for an internal request it does grow the endorsement
 * chain, logging the office that just received the folder.
 *
 * Port of `DocumentCustodyController`. The QR rule is the load-bearing part: a
 * scan must decode to THIS document's tracking number, and only codes this
 * system issued count, so an endorsement can never be recorded against a folder
 * that is not in the room.
 */
export const CAPTURE = {
  /** The camera read the QR on the paper. */
  Scan: 'scan',
  /** Typed in because the QR could not be read — needs a reason, and is audited. */
  Manual: 'manual',
  /** The QR read out of a saved picture — soft copies only. */
  Image: 'image',
  /** The QR snipped off the screen — soft copies only. */
  Screen: 'screen',
} as const;

export type CaptureMethod = (typeof CAPTURE)[keyof typeof CAPTURE];

const SCAN_METHODS: string[] = [CAPTURE.Scan, CAPTURE.Image, CAPTURE.Screen];
/** A picture of a QR proves someone had the file, not that the folder arrived. */
const PAPERLESS_METHODS: string[] = [CAPTURE.Image, CAPTURE.Screen];

const CUSTODY_SELECT = {
  id: true,
  tracking_number: true,
  document_type: true,
  purpose: true,
  status: true,
  origin: true,
  has_hard_copy: true,
  created_by: true,
  department_id: true,
  requesting_department_id: true,
  assigned_to: true,
} as const;

type CustodyDocument = {
  id: bigint;
  tracking_number: string;
  document_type: string;
  purpose: string | null;
  status: string;
  origin: string;
  has_hard_copy: boolean;
  created_by: bigint | null;
  department_id: bigint | null;
  requesting_department_id: bigint | null;
  assigned_to: bigint | null;
};

function howRead(method: CaptureMethod, overrideReason: string | null): string {
  switch (method) {
    case CAPTURE.Scan:
      return 'scanned';
    case CAPTURE.Image:
      return 'QR image';
    case CAPTURE.Screen:
      return 'QR on screen';
    default:
      return `manual: ${overrideReason ?? ''}`;
  }
}

/** How a recorded receipt reads back in the trail. */
export function captureLabel(method: string): string {
  switch (method) {
    case CAPTURE.Manual:
      return 'recorded manually';
    case CAPTURE.Image:
      return 'QR image';
    case CAPTURE.Screen:
      return 'QR on screen';
    default:
      return 'QR scanned';
  }
}

/** Who holds it now, if anyone. */
export async function currentCustody(documentId: number): Promise<{
  userId: number;
  userName: string | null;
  method: string;
  at: Date | null;
} | null> {
  const event = await db.document_custody_events.findFirst({
    where: { document_id: id(documentId) },
    orderBy: [{ created_at: 'desc' }, { id: 'desc' }],
    select: {
      user_id: true,
      capture_method: true,
      created_at: true,
      user: { select: { name: true } },
    },
  });

  return event === null
    ? null
    : {
        userId: toId(event.user_id),
        userName: event.user?.name ?? null,
        method: event.capture_method,
        at: event.created_at,
      };
}

export type CustodyEntry = {
  id: number;
  userName: string;
  officeName: string | null;
  method: string;
  methodLabel: string;
  overrideReason: string | null;
  at: string | null;
};

/** The hand-off trail, newest first — "who had this paper?" without the audit log. */
export async function custodyTrail(documentId: number, take = 15): Promise<CustodyEntry[]> {
  const rows = await db.document_custody_events.findMany({
    where: { document_id: id(documentId) },
    orderBy: [{ created_at: 'desc' }, { id: 'desc' }],
    take,
    select: {
      id: true,
      capture_method: true,
      override_reason: true,
      created_at: true,
      user: { select: { name: true, department: { select: { name: true } } } },
    },
  });

  return rows.map((row) => ({
    id: toId(row.id),
    userName: row.user?.name ?? 'Unknown staff',
    officeName: row.user?.department?.name ?? null,
    method: row.capture_method,
    methodLabel: captureLabel(row.capture_method),
    overrideReason: row.override_reason,
    at: row.created_at ? row.created_at.toISOString() : null,
  }));
}

/**
 * Write one custody event and everything that follows from it: the activity
 * line, the request's own feed, the endorsement chain, and the filer's
 * "received" bell. Returns the confirmation to show.
 */
export async function record(
  document: CustodyDocument,
  actor: Actor,
  method: CaptureMethod,
  overrideReason: string | null = null,
  note: string | null = null,
): Promise<string> {
  const documentId = toId(document.id);
  const scanned = SCAN_METHODS.includes(method);
  const soft = isSoftCopy(document);
  const internal = isInternal(document);

  // Whether this is the scan that RECEIVES the request at an office (the first
  // since it got there) — that, and only that, is what the filer hears about.
  const stepBefore = internal ? await currentRequestStep(documentId) : null;
  const heldBefore = stepBefore !== null && (await currentStepHasCustody(documentId, stepBefore));

  await db.document_custody_events.create({
    data: {
      document_id: document.id,
      user_id: id(actor.id),
      capture_method: method,
      override_reason: scanned ? null : overrideReason,
      note,
      created_at: now(),
      updated_at: now(),
    },
  });

  const how = howRead(method, overrideReason);

  await logOnDocument(
    documentId,
    soft
      ? `Received the request (${how})`
      : scanned
        ? 'Took physical custody of the folder (scanned)'
        : `Recorded custody manually (QR unreadable): ${overrideReason ?? ''}`,
    actor.id,
    { capture_method: method, ...(scanned ? {} : { override_reason: overrideReason }) },
  );

  await logSystemComment(
    documentId,
    soft
      ? `Received by ${actor.name} (${how})`
      : `Physical custody: folder now with ${actor.name} (${how})`,
  );

  const appendedOffice = await logDepartmentOntoChain(document, actor, stepBefore);

  await tellTheFilerItWasReceived(
    document,
    actor,
    appendedOffice !== null || (stepBefore !== null && !heldBefore),
  );

  if (soft) {
    return 'Received — you can now approve, return or deny this request.';
  }

  if (appendedOffice !== null) {
    return `Custody recorded — ${appendedOffice} added to the endorsement chain.`;
  }

  if (internal && !canActOnCurrentStep(document, stepBefore, actor)) {
    return 'Received — the folder is logged as at your office. Your supervisor approves, returns or denies it.';
  }

  return 'Custody recorded — the folder is now with you.';
}

/**
 * Growing the chain from scans: whichever office takes custody of an internal
 * request is logged as the hop now holding it. Nothing is appended when that
 * office already holds it, when the request is finished, or for citizen tickets
 * (which have no endorsement chain).
 */
async function logDepartmentOntoChain(
  document: CustodyDocument,
  actor: Actor,
  current: Awaited<ReturnType<typeof currentRequestStep>>,
): Promise<string | null> {
  if (!isInternal(document) || actor.departmentId === null) {
    return null;
  }

  if (isTerminal(statusOf(document))) {
    return null;
  }

  if (current && current.department_id === actor.departmentId) {
    return null;
  }

  await appendEndorsementStep(toId(document.id), actor.departmentId);

  const office = await db.departments.findFirst({
    where: { id: id(actor.departmentId) },
    select: { name: true },
  });

  const name = office?.name ?? 'Another office';

  await logOnDocument(
    toId(document.id),
    `Endorsement chain: ${name} received the request`,
    actor.id,
  );

  return name;
}

/**
 * The office that filed an internal request hears when the next office picks it
 * up. Only on the scan that receives it — not on every later hand-off inside
 * the office — and never to the person who scanned it themselves.
 */
async function tellTheFilerItWasReceived(
  document: CustodyDocument,
  actor: Actor,
  receivedNow: boolean,
): Promise<void> {
  if (!receivedNow || !isInternal(document) || document.created_by === null) {
    return;
  }

  const filerId = toId(document.created_by);

  if (filerId === actor.id) {
    return;
  }

  const step = await currentRequestStep(toId(document.id));
  const office = step?.departmentName ?? actor.departmentName ?? 'the next office';

  await notify(
    filerId,
    internalReceived(
      { ...document, id: toId(document.id) },
      actor.name,
      office,
    ),
  );
}

async function loadOrFail(documentId: number): Promise<CustodyDocument> {
  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: CUSTODY_SELECT,
  });

  if (!document) {
    throw new ActionError('That document no longer exists.');
  }

  return document;
}

/**
 * "Take custody" from a document's own Receive card.
 *
 * Anyone who may see the document may hold its folder — including counter staff
 * receiving a walk-in folder. A soft copy is narrower: it is received by the
 * person it was sent to or their supervisor, because for paper a stranger
 * scanning it means the folder moved, while for a soft copy it would just let
 * someone pull the request onto their own desk.
 */
export async function takeCustody(
  documentId: number,
  actor: Actor | null,
  input: {
    captureMethod: CaptureMethod;
    scannedValue?: string | null;
    overrideReason?: string | null;
    note?: string | null;
  },
): Promise<{ message: string }> {
  if (!actor) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const document = await loadOrFail(documentId);
  const documentId_ = toId(document.id);

  if (
    !canAccessDocument(
      {
        assigned_to: document.assigned_to === null ? null : toId(document.assigned_to),
        created_by: document.created_by === null ? null : toId(document.created_by),
      },
      actor,
    ) &&
    !actor.can('scan documents')
  ) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const held = await currentCustody(documentId_);

  if (held && held.userId === actor.id) {
    return { message: 'You already hold this folder.' };
  }

  const step = await currentRequestStep(documentId_);

  if (isSoftCopy(document) && !canActOnCurrentStep(document, step, actor)) {
    throw new ActionError(
      "This request was sent to someone else. Only they, or their office's supervisor, can receive it.",
    );
  }

  const method = input.captureMethod;

  if (PAPERLESS_METHODS.includes(method) && !isSoftCopy(document)) {
    throw new ActionError(
      'This request has a paper folder. Scan the QR on the folder with the camera.',
    );
  }

  const scanned = SCAN_METHODS.includes(method);

  if (scanned) {
    const value = (input.scannedValue ?? '').trim();

    if (value === '') {
      throw new ActionError("Scan the folder's QR code to take custody.");
    }

    assertScanMatches(value, document.tracking_number);
  } else if ((input.overrideReason ?? '').trim() === '') {
    throw new ActionError('A reason is required to record custody without scanning.');
  }

  const message = await record(
    document,
    actor,
    method,
    scanned ? null : (input.overrideReason ?? '').trim(),
    input.note?.trim() || null,
  );

  return { message };
}

/**
 * A scan must decode to THIS document's tracking number, and the code must be
 * one WE issued. A foreign QR that merely mentions a tracking number is refused
 * — the browser applies the same rule, this is the authority.
 */
function assertScanMatches(value: string, tracking: string): void {
  const scanned = decodeScan(value);

  if (scanned !== tracking.toUpperCase()) {
    throw new ActionError(
      scanned
        ? `That QR belongs to a different folder (${scanned}). Scan this document's folder.`
        : FOREIGN_CODE_MESSAGE,
    );
  }
}

export type ScanOutcome = { url: string; received: boolean; message?: string };

/**
 * One scan receives: open what the header's Scan QR Code (or Look Up) just
 * read, and for an internal request nobody at the holding office has received
 * yet, receive it with that same scan when the scanner is someone who may.
 * Without this, receiving took a second scan on the request's own card.
 */
export async function receiveByScan(
  actor: Actor | null,
  input: { scannedValue: string; captureMethod: 'scan' | 'image' },
): Promise<ScanOutcome> {
  if (!actor) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const tracking = decodeScan(input.scannedValue);

  if (tracking === null) {
    throw new ActionError(FOREIGN_CODE_MESSAGE);
  }

  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, deleted_at: null },
    select: CUSTODY_SELECT,
  });

  if (!document || !isInternal(document)) {
    return { url: `/track/${encodeURIComponent(tracking)}`, received: false };
  }

  const documentId = toId(document.id);
  const url = `/requests/${documentId}`;
  const step = await currentRequestStep(documentId);

  if (
    !canReceiveCurrentStep(document, step, actor) ||
    (await currentStepHasCustody(documentId, step))
  ) {
    return { url, received: false };
  }

  // A paper request is received by camera only.
  if (input.captureMethod === 'image' && !isSoftCopy(document)) {
    return { url, received: false };
  }

  const message = await record(document, actor, input.captureMethod);

  return { url, received: true, message };
}
