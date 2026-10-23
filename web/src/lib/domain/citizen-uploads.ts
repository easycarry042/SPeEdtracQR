import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import { DocumentStatus } from '@/lib/domain/document-status';
import { notify } from '@/lib/domain/notifications';
import { REVIEW_NEEDS_REVISION } from '@/lib/domain/requirement-review';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { ActionError } from '@/lib/domain/status-actions';
import { check as checkUpload } from '@/lib/domain/upload-rules';
import { citizenUploadMail } from '@/lib/mail/citizen-notices';
import { queueMail } from '@/lib/mail/send';
import { routes } from '@/lib/routes';
import { storePrivate } from '@/lib/storage';
import { now } from '@/lib/time';

/**
 * Port of `CitizenDocumentUploadController` — what a citizen can send from the
 * tracking page: supporting files for the office, and a corrected copy of a
 * requirement staff returned for revision.
 *
 * Guarded by the high-entropy tracking number, like the tracking page itself.
 * The confirmation names the OFFICE, never the staff member (Laravel's named
 * the assignee, which the anonymity rule forbids on citizen surfaces).
 */
export const MAX_FILES = 5;

async function openDocument(trackingNumber: string) {
  const document = await db.documents.findFirst({
    where: { tracking_number: trackingNumber.trim().toUpperCase(), origin: { not: ORIGIN_INTERNAL }, deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      citizen_name: true,
      status: true,
      assigned_to: true,
      assignee: { select: { name: true, email: true } },
      department: { select: { name: true } },
    },
  });

  if (!document) {
    throw new ActionError('That request could not be found.');
  }

  return document;
}

/** Supporting files for the office, up to five at a time, with an optional note. */
export async function uploadFiles(
  trackingNumber: string,
  files: File[],
  note: string | null,
): Promise<{ message: string }> {
  const document = await openDocument(trackingNumber);

  if (document.status === DocumentStatus.Completed) {
    throw new ActionError('This ticket is already completed. Uploads are no longer accepted.');
  }

  const chosen = files.filter((file) => file.size > 0);

  if (chosen.length === 0) {
    throw new ActionError('Choose at least one file to send.');
  }

  if (chosen.length > MAX_FILES) {
    throw new ActionError(`Send up to ${MAX_FILES} files at a time.`);
  }

  for (const file of chosen) {
    const problem = checkUpload(file);

    if (problem) {
      throw new ActionError(`${file.name}: ${problem}`);
    }
  }

  const trimmedNote = note?.trim() || null;

  if (trimmedNote && trimmedNote.length > 1000) {
    throw new ActionError('Keep the note under 1,000 characters.');
  }

  const last = await db.document_attachments.aggregate({
    where: { document_id: document.id },
    _max: { sort_order: true },
  });

  let sort = (last._max.sort_order ?? 0) + 1;
  let first: string | null = null;

  for (const file of chosen) {
    const path = await storePrivate(file, 'document-attachments');

    await db.document_attachments.create({
      data: {
        document_id: document.id,
        file_path: path,
        uploaded_by: null,
        sort_order: sort++,
        created_at: now(),
        updated_at: now(),
      },
    });

    first ??= path;
  }

  await db.documents.update({ where: { id: document.id }, data: { attachment_path: first, updated_at: now() } });
  await logOnDocument(toId(document.id), `Citizen uploaded ${chosen.length} file(s)`);

  if (document.assignee?.email) {
    queueMail(
      citizenUploadMail({
        to: document.assignee.email,
        trackingNumber: document.tracking_number,
        documentType: document.document_type,
        citizenName: document.citizen_name,
        assigneeName: document.assignee.name,
        fileCount: chosen.length,
        citizenNote: trimmedNote,
      }),
    );
  }

  const office = document.department?.name ? `the ${document.department.name}` : 'the office';

  return { message: `Your file(s) were sent to ${office}. They will be notified.` };
}

/**
 * Re-upload ONE requirement staff returned for revision; nothing else needs
 * resubmitting. The item goes back to pending, and — because it is a new
 * file — must be opened and checked again.
 */
export async function reuploadRequirement(
  trackingNumber: string,
  requirementId: number,
  file: File | null,
): Promise<{ message: string }> {
  const document = await openDocument(trackingNumber);

  const problem = checkUpload(file, { required: true });

  if (problem) {
    throw new ActionError(problem);
  }

  const requirement = await db.document_requirements.findFirst({
    where: { id: id(requirementId), document_id: document.id },
    select: { id: true, label: true, review_status: true },
  });

  if (!requirement) {
    throw new ActionError('That item is no longer on this request.');
  }

  if (document.status === DocumentStatus.Completed) {
    throw new ActionError('This ticket is already completed. Uploads are no longer accepted.');
  }

  if (requirement.review_status !== REVIEW_NEEDS_REVISION) {
    throw new ActionError('This document is not awaiting revision.');
  }

  const path = await storePrivate(file as File, 'document-requirements');

  await db.document_requirements.update({
    where: { id: requirement.id },
    data: {
      uploaded_file_path: path,
      review_status: 'pending',
      reviewed_at: null,
      reviewed_by: null,
      opened_at: null,
      opened_by: null,
      verified_at: null,
      verified_by: null,
      updated_at: now(),
    },
  });

  await logOnDocument(toId(document.id), `Citizen re-uploaded "${requirement.label}" for re-review`);

  if (document.assigned_to !== null) {
    await notify(toId(document.assigned_to), {
      event: 'revision_resubmitted',
      title: 'Revised document uploaded',
      body: `The citizen re-uploaded "${requirement.label}" for ${document.tracking_number} — ready for re-review.`,
      url: routes.trackShow(document.tracking_number),
      tracking_number: document.tracking_number,
    });

    if (document.assignee?.email) {
      queueMail(
        citizenUploadMail({
          to: document.assignee.email,
          trackingNumber: document.tracking_number,
          documentType: document.document_type,
          citizenName: document.citizen_name,
          assigneeName: document.assignee.name,
          fileCount: 1,
          citizenNote: `Revised document: ${requirement.label}`,
        }),
      );
    }
  }

  return { message: `Your revised "${requirement.label}" was sent for re-review.` };
}
