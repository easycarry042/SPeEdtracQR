import { db, id, toId } from '@/lib/db';
import { formatNaive, toDateString } from '@/lib/time';
import { routes } from '@/lib/routes';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus, fromLoose, label as statusLabel, railStage } from '@/lib/domain/document-status';
import { canBeAdvancedBy, holdOverdueDays, isHoldOverdue, statusOf } from '@/lib/domain/document';
import { AUTHOR, VISIBILITY, deskPayload, hydrateComment, type DeskPayload } from '@/lib/domain/comments';
import {
  LENDING,
  approvedConflicts,
  daysOverdue,
  hydrateBooking,
  isEquipment,
  lendingLabel,
  lendingState,
  serviceableUnits,
  units,
  unitsAvailable,
  unitsOutstanding,
  unitsRequested,
} from '@/lib/domain/lending';
import { requestNotReady, window as lendingWindow } from '@/lib/domain/lending-desk';
import { SUBJECT } from '@/lib/activity';
import { citizenFacing } from '@/lib/domain/scope';

/**
 * Shapes a document into the flat payload the Review modal needs (supervisor
 * and staff share the same modal).
 *
 * The status log and the citizen conversation used to be assembled separately
 * by the Look Up desk, which is why the two modals could do different things to
 * the same request. The desk is gone and its panels moved into this modal, so
 * everything it needs is built here — use `collection()` for a queue, never
 * `forModal()` in a loop, or the activity log costs a query per row.
 */
export type ReviewAttachment = { url: string; ext: string; isImage: boolean };

export type ReviewRequirement = {
  id: number;
  label: string;
  isMandatory: boolean;
  reviewStatus: string;
  hasFile: boolean;
  url: string | null;
  ext: string;
  isImage: boolean;
};

export type ReviewBooking = {
  id: number;
  item: string;
  isEquipment: boolean;
  state: string;
  stateLabel: string;
  tone: 'red' | 'amber' | 'muted' | 'green';
  requested: number;
  units: number;
  outstanding: number;
  window: string;
  pickup: string;
  returnBy: string;
  daysOverdue: number;
  free: number | null;
  serviceable: number | null;
  clash: boolean;
  notReady: string | null;
  canManage: boolean;
  approveUrl: string;
  deskUrl: string;
};

export type ReviewRow = {
  id: number;
  trackingNumber: string;
  qrUrl: string | null;
  slipUrl: string;
  documentType: string;
  /** The handling office's code — the "THED ID" column. */
  thedId: string | null;
  department: string | null;
  assignedStaff: string | null;
  citizenName: string | null;
  citizenEmail: string | null;
  citizenContact: string | null;
  purpose: string | null;
  description: string | null;
  status: string;
  statusLabel: string;
  needsTriage: boolean;
  canAdvance: boolean;
  hasWorkEvidence: boolean;
  assignedTo: number | null;
  submittedAt: string | null;
  submittedOn: string | null;
  requestDate: string | null;
  requestedClaimDate: string | null;
  claimDate: string | null;
  requestedClaimOn: string | null;
  claimOn: string | null;
  unreadMessages: number;
  statusBeforeHold: string | null;
  railStage: number;
  blockedBy: string | null;
  holdReason: string | null;
  holdUntil: string | null;
  holdOverdueDays: number | null;
  remarks: string | null;
  booking: ReviewBooking | null;
  attachments: ReviewAttachment[];
  requirements: ReviewRequirement[];
  dateShort: string | null;
  logs: Array<{ event: string; time: string }>;
  messages: DeskPayload[];
  canMessage: boolean;
};

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function extensionOf(path: string | null): string {
  const match = /\.([^.\\/]+)$/.exec(path ?? '');

  return match ? match[1].toLowerCase() : '';
}

function isImageExtension(extension: string): boolean {
  return IMAGE_EXTENSIONS.includes(extension);
}

/**
 * Staff who can be assigned a request. A supervisor sees only their own
 * office's staff; org-wide admins see everyone.
 */
export async function assignableStaff(
  departmentId: number | null = null,
): Promise<Array<{ id: number; name: string; department_id: number | null }>> {
  const links = await db.model_has_roles.findMany({
    where: { model_type: 'App\\Models\\User', roles: { name: 'staff' } },
    select: { model_id: true },
  });

  if (links.length === 0) {
    return [];
  }

  const users = await db.users.findMany({
    where: {
      id: { in: links.map((link) => link.model_id) },
      deleted_at: null,
      is_active: true,
      ...(departmentId === null ? {} : { department_id: id(departmentId) }),
    },
    orderBy: { name: 'asc' },
    select: { id: true, name: true, department_id: true },
  });

  return users.map((user) => ({
    id: toId(user.id),
    name: user.name,
    department_id: user.department_id === null ? null : toId(user.department_id),
  }));
}

/** Assigned ticket awaiting staff Accept / Decline / Revision. */
export function needsTriage(document: {
  assigned_to: bigint | number | null;
  accepted_at: Date | null;
  status: string;
}): boolean {
  if (document.assigned_to === null) {
    return false;
  }

  return (
    document.accepted_at === null &&
    ([DocumentStatus.Pending, DocumentStatus.InProgress] as string[]).includes(document.status)
  );
}

/**
 * Status changes per document, newest first, keyed by document id. Only entries
 * that actually moved the status are kept — the activity log also records edits
 * to unrelated columns, which read as noise in a stage log.
 */
export async function statusLogs(
  documentIds: number[],
): Promise<Map<number, Array<{ event: string; time: string }>>> {
  const logs = new Map<number, Array<{ event: string; time: string }>>();

  if (documentIds.length === 0) {
    return logs;
  }

  const rows = await db.activity_log.findMany({
    where: { subject_type: SUBJECT.Document, subject_id: { in: documentIds.map(id) } },
    orderBy: { created_at: 'desc' },
    select: { subject_id: true, properties: true, created_at: true },
  });

  for (const row of rows) {
    const to = (row.properties as { attributes?: { status?: string } } | null)?.attributes?.status;

    if (!to || row.subject_id === null) {
      continue;
    }

    const key = toId(row.subject_id);
    const entry = {
      event: `Updated to ${statusLabel(fromLoose(to))}`,
      time: row.created_at ? formatNaive(row.created_at, 'M j, g:i A') : '',
    };

    logs.set(key, [...(logs.get(key) ?? []), entry]);
  }

  return logs;
}

/** Everything `forModal` reads off a document row. */
export const REVIEW_SELECT = {
  id: true,
  tracking_number: true,
  qr_code_path: true,
  document_type: true,
  citizen_name: true,
  citizen_email: true,
  citizen_contact: true,
  purpose: true,
  description: true,
  status: true,
  status_before_hold: true,
  blocked_by: true,
  hold_reason: true,
  hold_until: true,
  held_at: true,
  remarks: true,
  assigned_to: true,
  accepted_at: true,
  created_at: true,
  needed_by: true,
  claim_date: true,
  department: { select: { name: true, code: true } },
  assignee: { select: { name: true } },
  document_attachments: { select: { id: true, file_path: true }, orderBy: { sort_order: 'asc' } },
  document_requirements: {
    select: {
      id: true,
      label: true,
      is_mandatory: true,
      review_status: true,
      uploaded_file_path: true,
    },
    orderBy: { id: 'asc' },
  },
} as const;

type ReviewSource = {
  id: bigint;
  tracking_number: string;
  qr_code_path: string | null;
  document_type: string;
  citizen_name: string | null;
  citizen_email: string | null;
  citizen_contact: string | null;
  purpose: string | null;
  description: string | null;
  status: string;
  status_before_hold: string | null;
  blocked_by: string | null;
  hold_reason: string | null;
  hold_until: Date | null;
  held_at: Date | null;
  remarks: string | null;
  assigned_to: bigint | null;
  accepted_at: Date | null;
  created_at: Date | null;
  needed_by: Date | null;
  claim_date: Date | null;
  department: { name: string; code: string } | null;
  assignee: { name: string } | null;
  document_attachments: Array<{ id: bigint; file_path: string }>;
  document_requirements: Array<{
    id: bigint;
    label: string;
    is_mandatory: boolean;
    review_status: string;
    uploaded_file_path: string | null;
  }>;
};

/**
 * A whole queue, shaped for the Review modal.
 *
 * The status log, the conversation and the bookings are each fetched for every
 * row in ONE query — per-row it is a query per request on a page that lists
 * dozens of them.
 */
export async function collection(
  documents: ReviewSource[],
  actor: Actor | null,
): Promise<ReviewRow[]> {
  const documentIds = documents.map((document) => toId(document.id));

  if (documentIds.length === 0) {
    return [];
  }

  const keys = documentIds.map(id);

  const [logs, comments, bookings, workEvidence] = await Promise.all([
    statusLogs(documentIds),

    // The Messages panel renders the citizen-facing thread, so it travels with
    // the row. Internal staff notes are deliberately excluded — that thread is
    // not this panel's.
    db.document_comments.findMany({
      where: { document_id: { in: keys }, visibility: VISIBILITY.Public },
      orderBy: { id: 'asc' },
      select: {
        id: true,
        document_id: true,
        author_id: true,
        author_type: true,
        author_name: true,
        body: true,
        visibility: true,
        parent_id: true,
        staff_read_at: true,
        citizen_read_at: true,
        attachment_path: true,
        attachment_name: true,
        created_at: true,
        author: { select: { name: true, department: { select: { name: true } } } },
      },
    }),

    db.bookings.findMany({
      where: { document_id: { in: keys } },
      select: {
        id: true,
        document_id: true,
        resource_id: true,
        quantity: true,
        starts_at: true,
        ends_at: true,
        status: true,
        released_at: true,
        returned_at: true,
        returned_quantity: true,
        damaged_quantity: true,
        lost_quantity: true,
        document: { select: { quantity: true } },
        resource: {
          select: { id: true, name: true, kind: true, total_quantity: true, damaged_quantity: true, capacity: true },
        },
      },
    }),

    // Stage-gate context for →In Review: a staff note counts as evidence.
    db.document_comments.findMany({
      where: { document_id: { in: keys }, author_type: AUTHOR.Staff, parent_id: null },
      select: { document_id: true },
      distinct: ['document_id'],
    }),
  ]);

  const byDocument = new Map<number, typeof comments>();

  for (const comment of comments) {
    const key = toId(comment.document_id);
    byDocument.set(key, [...(byDocument.get(key) ?? []), comment]);
  }

  const bookingByDocument = new Map(bookings.map((booking) => [toId(booking.document_id), booking]));
  const hasStaffNote = new Set(workEvidence.map((row) => toId(row.document_id)));

  return Promise.all(
    documents.map(async (document) => {
      const key = toId(document.id);
      const thread = byDocument.get(key) ?? [];
      const booking = bookingByDocument.get(key);

      return {
        ...(await forModal(document, actor, booking)),
        dateShort: document.created_at ? formatNaive(document.created_at, 'm/d/y') : null,
        logs: logs.get(key) ?? [],
        messages: thread.map((comment) =>
          deskPayload(hydrateComment(comment), document.citizen_name),
        ),
        // Drives whether the panel offers a composer or reads as an archive: a
        // colleague may see the thread without owning it.
        canMessage: canPostOn(document, actor),
        unreadMessages: thread.filter(
          (comment) => comment.author_type === AUTHOR.Citizen && comment.staff_read_at === null,
        ).length,
        hasWorkEvidence: document.document_attachments.length > 0 || hasStaffNote.has(key),
      };
    }),
  );
}

/** Who may post on a request's citizen thread. */
export function canPostOn(
  document: { assigned_to: bigint | null },
  actor: Actor | null,
): boolean {
  if (!actor) {
    return false;
  }

  return (
    actor.can('manage system') ||
    actor.can('assign documents') ||
    (actor.can('advance documents') &&
      document.assigned_to !== null &&
      toId(document.assigned_to) === actor.id)
  );
}

type BookingSource = {
  id: bigint;
  document_id: bigint;
  resource_id: bigint;
  quantity: number | null;
  starts_at: Date;
  ends_at: Date;
  status: string;
  released_at: Date | null;
  returned_at: Date | null;
  returned_quantity: number;
  damaged_quantity: number;
  lost_quantity: number;
  document: { quantity: number | null } | null;
  resource: {
    id: bigint;
    name: string;
    kind: string;
    total_quantity: number | null;
    damaged_quantity: number;
    capacity: number | null;
  } | null;
};

/**
 * A request's reservation for the Review modal — what, how many, when, where it
 * stands — and whether this viewer can approve it from there. Hand-over and
 * returns stay on the Resources page's lending desk.
 */
export async function bookingPayload(
  source: BookingSource | undefined,
  actor: Actor | null,
): Promise<ReviewBooking | null> {
  if (!source?.resource) {
    return null;
  }

  const booking = hydrateBooking(source);
  const resource = { ...source.resource, id: toId(source.resource.id) };
  const state = lendingState(booking);
  const equipment = isEquipment(resource);
  const pending = state === LENDING.Pending;

  const [free, clash, notReady] = await Promise.all([
    pending && equipment
      ? unitsAvailable(resource, booking.starts_at, booking.ends_at, booking.id)
      : Promise.resolve(null),
    pending && !equipment
      ? approvedConflicts(resource.id, booking.starts_at, booking.ends_at, booking.id).then(
          (rows) => rows.length > 0,
        )
      : Promise.resolve(false),
    pending
      ? (async () => {
          const { loadBooking } = await import('@/lib/domain/lending-desk');
          const loaded = await loadBooking(booking.id);

          return loaded ? requestNotReady(loaded) : null;
        })()
      : Promise.resolve(null),
  ]);

  return {
    id: booking.id,
    item: resource.name,
    isEquipment: equipment,
    state,
    stateLabel: lendingLabel(booking, resource),
    tone:
      state === LENDING.Overdue || state === LENDING.Cancelled
        ? 'red'
        : state === LENDING.Pending || state === LENDING.Missed
          ? 'amber'
          : state === LENDING.Returned
            ? 'muted'
            : 'green',
    requested: unitsRequested(booking),
    units: units(booking),
    outstanding: unitsOutstanding(booking),
    window: lendingWindow(booking),
    pickup: formatNaive(booking.starts_at, 'D, M j · g:i A'),
    returnBy: formatNaive(booking.ends_at, 'D, M j · g:i A'),
    daysOverdue: daysOverdue(booking),
    free,
    serviceable: equipment ? serviceableUnits(resource) : null,
    clash,
    notReady,
    canManage: actor?.can('manage inventory') ?? false,
    approveUrl: routes.inventoryLoansApprove(booking.id),
    deskUrl: routes.inventoryIndex(),
  };
}

export async function forModal(
  document: ReviewSource,
  actor: Actor | null,
  booking?: BookingSource,
): Promise<Omit<ReviewRow, 'dateShort' | 'logs' | 'messages' | 'canMessage'>> {
  const stage = statusOf(document);
  const assignedTo = document.assigned_to === null ? null : toId(document.assigned_to);

  const holdState = {
    status: document.status,
    hold_until: document.hold_until,
    held_at: document.held_at,
  };

  return {
    id: toId(document.id),
    trackingNumber: document.tracking_number,
    // The request's generated QR, and its print-ready claim slip — shown from
    // the tracking-number tile in the staff modals.
    qrUrl: document.qr_code_path ? routes.storage(document.qr_code_path) : null,
    slipUrl: routes.trackSlip(document.tracking_number),
    documentType: document.document_type,
    thedId: document.department?.code ?? null,
    department: document.department?.name ?? null,
    assignedStaff: document.assignee?.name ?? null,
    citizenName: document.citizen_name,
    citizenEmail: document.citizen_email,
    citizenContact: document.citizen_contact,
    purpose: document.purpose,
    description: document.description,
    status: document.status,
    statusLabel: statusLabel(stage),
    needsTriage: needsTriage(document),
    // Whether the viewing user can actually drive this document's status
    // forward — lets the cockpit disable the controls instead of rendering an
    // action the server will then refuse.
    canAdvance: canBeAdvancedBy({ assigned_to: assignedTo }, actor),
    hasWorkEvidence: document.document_attachments.length > 0,
    assignedTo,
    submittedAt: document.created_at ? formatNaive(document.created_at, 'M j, Y g:i A') : null,
    submittedOn: document.created_at ? toDateString(document.created_at) : null,
    requestDate: document.created_at ? formatNaive(document.created_at, 'F j, Y') : null,
    // Two different dates, deliberately. `needed_by` is what the citizen asked
    // for on the form; `claim_date` is what staff have actually committed to.
    requestedClaimDate: document.needed_by ? formatNaive(document.needed_by, 'F j, Y') : null,
    claimDate: document.claim_date ? formatNaive(document.claim_date, 'F j, Y') : null,
    requestedClaimOn: document.needed_by ? toDateString(document.needed_by) : null,
    claimOn: document.claim_date ? toDateString(document.claim_date) : null,
    unreadMessages: 0,
    statusBeforeHold: document.status_before_hold,
    railStage: railStage(stage, document.status_before_hold ? fromLoose(document.status_before_hold) : null),
    blockedBy: document.blocked_by,
    holdReason: document.hold_reason,
    holdUntil: document.hold_until ? formatNaive(document.hold_until, 'M j, Y') : null,
    // A hold pauses the SLA clock, so this count is the only thing in the
    // cockpit that says a parked request has overrun its own deadline.
    holdOverdueDays: isHoldOverdue(holdState) ? holdOverdueDays(holdState) : null,
    remarks: document.remarks,
    booking: await bookingPayload(booking, actor),
    attachments: document.document_attachments.map((attachment) => {
      const ext = extensionOf(attachment.file_path);

      return {
        url: routes.attachmentsShow(toId(attachment.id)),
        ext,
        isImage: isImageExtension(ext),
      };
    }),
    // Requirement uploads live on their own table, not document_attachments, so
    // they need listing separately or the reviewer sees no evidence at all.
    requirements: document.document_requirements.map((requirement) => {
      const ext = extensionOf(requirement.uploaded_file_path);
      const hasFile = requirement.uploaded_file_path !== null;

      return {
        id: toId(requirement.id),
        label: requirement.label,
        isMandatory: requirement.is_mandatory,
        reviewStatus: requirement.review_status,
        hasFile,
        url: hasFile
          ? routes.documentsRequirementsFile(toId(document.id), toId(requirement.id))
          : null,
        ext,
        isImage: hasFile && isImageExtension(ext),
      };
    }),
  };
}

export { citizenFacing };
