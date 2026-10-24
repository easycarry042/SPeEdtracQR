import { db, id } from '@/lib/db';
import { DocumentStatus, fromLoose, label as statusLabel } from '@/lib/domain/document-status';
import { unverifiedMandatoryRequirements } from '@/lib/domain/document';
import { isApproved } from '@/lib/domain/lending';

/**
 * Stage gates: the requirements a document must meet before it may enter a
 * given status stage. The stepper renders `checks()` as a live checklist and the
 * server enforces `validate()` — one definition, so the UI and the server can
 * never disagree.
 *
 * Gates are HARD: a failed requirement blocks the transition server-side.
 */
export type GateCheck = {
  key: string;
  label: string;
  passed: boolean;
};

/** Thrown by `validate()`; carries the same shape Laravel's 422 response did. */
export class GateError extends Error {
  public readonly status = 422;

  constructor(public readonly messages: string[]) {
    super(messages.join(' '));
    this.name = 'GateError';
  }

  /** Matches Laravel's `{ errors: { status: [...] } }` validation body. */
  toJSON(): { message: string; errors: { status: string[] } } {
    return { message: this.messages[0] ?? 'Validation failed.', errors: { status: this.messages } };
  }
}

/**
 * Requirement checklist for entering `to`.
 *
 * A note submitted WITH the transition counts as work evidence for →In Review —
 * the caller persists it as a staff comment, so the record stays consistent
 * with what the gate accepted.
 */
export async function checks(
  document: { id: number },
  to: DocumentStatus,
  note: string | null = null,
): Promise<GateCheck[]> {
  switch (to) {
    case DocumentStatus.InProgress: {
      const row = await db.documents.findUniqueOrThrow({
        where: { id: id(document.id) },
        select: { assigned_to: true, accepted_at: true },
      });

      return [
        {
          key: 'assigned',
          label: 'A staff member is assigned',
          passed: row.assigned_to !== null,
        },
        {
          key: 'accepted',
          label: 'The assignee accepted the request',
          passed: row.accepted_at !== null,
        },
      ];
    }

    case DocumentStatus.InReview: {
      const hasNote = (note ?? '').trim() !== '';

      // Short-circuit: a note submitted with the move is itself the evidence.
      if (hasNote) {
        return [{ key: 'work_evidence', label: 'An attachment or work note on file', passed: true }];
      }

      const [attachment, staffComment] = await Promise.all([
        db.document_attachments.findFirst({
          where: { document_id: id(document.id) },
          select: { id: true },
        }),
        db.document_comments.findFirst({
          where: { document_id: id(document.id), author_type: 'staff', parent_id: null },
          select: { id: true },
        }),
      ]);

      return [
        {
          key: 'work_evidence',
          label: 'An attachment or work note on file',
          passed: attachment !== null || staffComment !== null,
        },
      ];
    }

    // Before a request is finished (Ongoing → Done), staff must have checked
    // every mandatory supporting requirement against the originals. Requests
    // without requirements pass automatically. (Approved, retired, kept the
    // same checks for any caller still asking about it.)
    case DocumentStatus.Completed:
    case DocumentStatus.Approved: {
      const [unverified, booking] = await Promise.all([
        unverifiedMandatoryRequirements(document.id),
        db.bookings.findFirst({
          where: { document_id: id(document.id) },
          select: { status: true },
        }),
      ]);

      return [
        {
          key: 'requirements_verified',
          // Names the files left, so the reviewer knows which rows to open.
          label:
            unverified.length === 0
              ? 'All required documents checked'
              : `All required documents checked (still to check: ${unverified.map((r) => r.label).join(', ')})`,
          passed: unverified.length === 0,
        },
        // A borrowing or reservation is approved as a whole: the request cannot
        // read "Done" to the citizen while its items or venue are still
        // unconfirmed (or were cancelled).
        {
          key: 'reservation_approved',
          label: 'The reservation is approved on the Resources page (or Booking calendar)',
          passed: booking === null || isApproved(booking),
        },
      ];
    }

    default:
      return [];
  }
}

/** Stages whose transition must carry a note (the "why" for the audit trail). */
export function noteRequired(to: DocumentStatus): boolean {
  return to === DocumentStatus.Approved;
}

/** Throws a `GateError` listing every unmet requirement. */
export async function validate(
  document: { id: number },
  to: DocumentStatus,
  note: string | null = null,
): Promise<void> {
  const failed = (await checks(document, to, note)).filter((check) => !check.passed);
  const messages = failed.map((check) => `Requirement not met: ${check.label}.`);

  if (noteRequired(to) && (note ?? '').trim() === '') {
    messages.push(`A review note is required to move a document to ${statusLabel(to)}.`);
  }

  if (messages.length > 0) {
    throw new GateError(messages);
  }
}

/**
 * Optimistic concurrency guard: the form/JSON caller sends the stage it was
 * looking at; if the document has moved since, refuse with a clear message
 * instead of double-jumping.
 */
export function assertExpectedStatus(
  document: { status: string },
  expected: string | null,
): void {
  if (expected === null || expected === document.status) {
    return;
  }

  throw new GateError([
    `This document was just updated by someone else — it is now "${statusLabel(
      fromLoose(document.status),
    )}". Review the new stage before making changes.`,
  ]);
}
