import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import type { Actor } from '@/lib/auth/actor';
import { canBeAdvancedBy } from '@/lib/domain/document';
import { ActionError } from '@/lib/domain/status-actions';
import { queueMail } from '@/lib/mail/send';
import { requirementRevisionMail } from '@/lib/mail/citizen-notices';
import { routes } from '@/lib/routes';
import { formatNaive, now } from '@/lib/time';

/**
 * Port of the requirement checks in `DocumentRequirementController` and
 * `RequestReview::requirementPayload`.
 *
 * Each uploaded file is OPENED, then checked. Opening it through the file
 * route with `?open=1` (the modal's View link and thumbnail) stamps
 * `opened_at/opened_by`; a plain load, such as the thumbnail's `<img>`, does
 * not. "Looks good" is refused until the file has been opened, and In Review →
 * Approved is refused until every mandatory requirement is checked
 * (`StatusGate`). Nothing uploaded means the original is checked over the
 * counter, so there is nothing to open.
 */
export const REVIEW_APPROVED = 'approved';
export const REVIEW_NEEDS_REVISION = 'needs_revision';
export const REVIEW_REJECTED = 'rejected';

export const REVIEW_STATUSES = ['pending', REVIEW_APPROVED, REVIEW_NEEDS_REVISION, REVIEW_REJECTED] as const;

export type ReviewRequirement = {
  id: number;
  label: string;
  isMandatory: boolean;
  reviewStatus: string;
  reviewStatusLabel: string;
  reviewComment: string | null;
  hasFile: boolean;
  /** Plain stream: thumbnails load this without counting as an open. */
  url: string | null;
  /** The View link. Opening through it is what counts as having looked. */
  openUrl: string | null;
  opened: boolean;
  openedLabel: string | null;
  verified: boolean;
  verifiedLabel: string | null;
  ext: string;
  isImage: boolean;
};

export const REQUIREMENT_SELECT = {
  id: true,
  label: true,
  is_mandatory: true,
  review_status: true,
  review_comment: true,
  uploaded_file_path: true,
  opened_at: true,
  verified_at: true,
  opener: { select: { name: true } },
  verifier: { select: { name: true } },
} as const;

type RequirementRow = {
  id: bigint;
  label: string;
  is_mandatory: boolean;
  review_status: string;
  review_comment: string | null;
  uploaded_file_path: string | null;
  opened_at: Date | null;
  verified_at: Date | null;
  opener: { name: string } | null;
  verifier: { name: string } | null;
};

const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

export function reviewStatusLabel(status: string): string {
  switch (status) {
    case REVIEW_APPROVED:
      return 'Approved';
    case REVIEW_NEEDS_REVISION:
      return 'Needs revision';
    case REVIEW_REJECTED:
      return 'Rejected';
    default:
      return 'Pending review';
  }
}

/** Whether staff have looked at what the citizen sent. */
export function hasBeenOpened(requirement: {
  uploaded_file_path: string | null;
  opened_at: Date | null;
}): boolean {
  return requirement.uploaded_file_path === null || requirement.opened_at !== null;
}

export function requirementPayload(documentId: number, requirement: RequirementRow): ReviewRequirement {
  const match = /\.([^.\\/]+)$/.exec(requirement.uploaded_file_path ?? '');
  const ext = match ? match[1].toLowerCase() : '';
  const hasFile = requirement.uploaded_file_path !== null;
  const url = hasFile ? routes.documentsRequirementsFile(documentId, toId(requirement.id)) : null;

  return {
    id: toId(requirement.id),
    label: requirement.label,
    isMandatory: requirement.is_mandatory,
    reviewStatus: requirement.review_status,
    reviewStatusLabel: reviewStatusLabel(requirement.review_status),
    reviewComment: requirement.review_comment,
    hasFile,
    url,
    openUrl: url ? `${url}?open=1` : null,
    opened: hasBeenOpened(requirement),
    openedLabel: requirement.opened_at
      ? `Opened by ${requirement.opener?.name ?? 'staff'} · ${formatNaive(requirement.opened_at, 'M j, g:i A')}`
      : null,
    verified: requirement.verified_at !== null,
    verifiedLabel: requirement.verified_at
      ? `Checked by ${requirement.verifier?.name ?? 'staff'} · ${formatNaive(requirement.verified_at, 'M j, g:i A')}`
      : null,
    ext,
    isImage: hasFile && IMAGE_EXTENSIONS.includes(ext),
  };
}

async function loadPair(documentId: number, requirementId: number) {
  const requirement = await db.document_requirements.findFirst({
    where: { id: id(requirementId), document_id: id(documentId), document: { deleted_at: null } },
    select: {
      ...REQUIREMENT_SELECT,
      opened_by: true,
      verified_by: true,
      document: {
        select: {
          tracking_number: true,
          assigned_to: true,
          created_by: true,
          citizen_name: true,
          citizen_email: true,
        },
      },
    },
  });

  if (!requirement) {
    throw new ActionError('That requirement is no longer on this request.');
  }

  return requirement;
}

/**
 * Record that a reviewer opened the file. Only the first open by someone who
 * can check it counts; anyone else (a supervisor glancing at it) leaves no
 * stamp, so "Looks good" stays locked for the person who has to decide.
 */
export async function markOpened(documentId: number, requirementId: number, actor: Actor): Promise<void> {
  const requirement = await loadPair(documentId, requirementId);
  const assignedTo = requirement.document.assigned_to;

  if (
    requirement.opened_at !== null ||
    requirement.uploaded_file_path === null ||
    !canBeAdvancedBy({ assigned_to: assignedTo === null ? null : toId(assignedTo) }, actor)
  ) {
    return;
  }

  // Conditional on still being unopened, so two tabs opening at once stamp once.
  const { count } = await db.document_requirements.updateMany({
    where: { id: requirement.id, opened_at: null },
    data: { opened_at: now(), opened_by: id(actor.id), updated_at: now() },
  });

  if (count > 0) {
    await logOnDocument(documentId, `Opened requirement "${requirement.label}"`, actor.id);
  }
}

/**
 * Looks good / Needs fixing / Reject.
 *
 * Approving also records the verification (who saw the original, when) — they
 * are one decision for the reviewer, and approval of the request is gated on
 * mandatory requirements being verified. Sending it back clears it again, so a
 * withdrawn approval cannot leave the request advanceable. A comment is
 * required when sending back, and the citizen is emailed it.
 */
export async function reviewRequirement(
  documentId: number,
  requirementId: number,
  actor: Actor | null,
  input: { reviewStatus: string; reviewComment?: string | null },
): Promise<{ message: string; requirement: ReviewRequirement }> {
  const requirement = await loadPair(documentId, requirementId);
  const assignedTo = requirement.document.assigned_to;

  if (!actor || !canBeAdvancedBy({ assigned_to: assignedTo === null ? null : toId(assignedTo) }, actor)) {
    throw new ActionError('Only the assigned staff member or an admin can review requirements.');
  }

  const status = input.reviewStatus;

  if (!(REVIEW_STATUSES as readonly string[]).includes(status)) {
    throw new ActionError('Choose how this document checks out.');
  }

  const comment = input.reviewComment?.trim() || null;
  const sentBack = status === REVIEW_NEEDS_REVISION || status === REVIEW_REJECTED;

  if (sentBack && !comment) {
    throw new ActionError('Please explain what needs to be corrected.');
  }

  if (comment && comment.length > 1000) {
    throw new ActionError('Keep the comment under 1,000 characters.');
  }

  const approved = status === REVIEW_APPROVED;

  if (approved && !hasBeenOpened(requirement)) {
    throw new ActionError(`Open "${requirement.label}" first, then mark it checked.`);
  }

  const stamp = now();

  const updated = await db.document_requirements.update({
    where: { id: requirement.id },
    data: {
      review_status: status,
      review_comment: comment,
      reviewed_at: stamp,
      reviewed_by: id(actor.id),
      verified_at: approved ? (requirement.verified_at ?? stamp) : null,
      verified_by: approved ? (requirement.verified_by ?? id(actor.id)) : null,
      updated_at: stamp,
    },
    select: REQUIREMENT_SELECT,
  });

  const label = reviewStatusLabel(status);

  await logOnDocument(documentId, `Reviewed requirement "${requirement.label}": ${label}`, actor.id);

  if (sentBack && requirement.document.citizen_email) {
    queueMail(
      requirementRevisionMail({
        to: requirement.document.citizen_email,
        trackingNumber: requirement.document.tracking_number,
        citizenName: requirement.document.citizen_name,
        requirementLabel: requirement.label,
        reviewStatusLabel: label,
        reviewComment: comment,
        rejected: status === REVIEW_REJECTED,
      }),
    );
    await logOnDocument(documentId, `Emailed the citizen about "${requirement.label}" (${label})`);
  }

  return {
    message: `Marked "${requirement.label}" as ${label}.`,
    requirement: requirementPayload(documentId, updated),
  };
}

/**
 * The file a requirement row points at, if this viewer may see the request.
 * Returns null for anything that should read as "not found".
 */
export async function requirementFileFor(
  documentId: number,
  requirementId: number,
): Promise<{ path: string; createdBy: number | null; assignedTo: number | null } | null> {
  const row = await db.document_requirements.findFirst({
    where: { id: id(requirementId), document_id: id(documentId), document: { deleted_at: null } },
    select: {
      uploaded_file_path: true,
      document: { select: { created_by: true, assigned_to: true } },
    },
  });

  if (!row?.uploaded_file_path) {
    return null;
  }

  return {
    path: row.uploaded_file_path,
    createdBy: row.document.created_by === null ? null : toId(row.document.created_by),
    assignedTo: row.document.assigned_to === null ? null : toId(row.document.assigned_to),
  };
}
