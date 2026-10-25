import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';
import { storePrivate } from '@/lib/storage';
import { logSystemComment } from '@/lib/domain/comments';
import { ActionError } from '@/lib/domain/status-actions';
import { DocumentStatus } from '@/lib/domain/document-status';
import { statusChangeData } from '@/lib/domain/document';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { STEP_STATUS } from '@/lib/domain/request-step';
import * as qr from '@/lib/domain/qr-service';
import { checkPaperScan } from '@/lib/domain/upload-rules';
import { isRecipientAt } from '@/lib/domain/internal-request-form';
import { tellTheHop } from '@/lib/domain/internal-events';
import { ensureStampedCopy, NO_PLACEMENT, type StampPlacement } from '@/lib/domain/qr-stamp';
import type { Actor } from '@/lib/auth/actor';

/**
 * Filing an internal office-to-office request.
 *
 * Port of `InternalRequestController@store`. Filing is the requesting office's
 * own sign-off, so the request goes straight to the office the filer chose:
 * that hop is current from the start, and further offices are added by whoever
 * handles it there rather than presumed here.
 *
 * The signed paper IS the request, so the scan is required: filing without it
 * leaves the receiving office nothing to act on.
 */
export type FilingInput = {
  firstDepartmentId: number;
  purpose: string;
  /** Paper that will be carried to the first office, or a soft copy. */
  hasHardCopy: boolean;
  /** Who receives a soft copy at the first office (required when there is no paper). */
  recipientId: number | null;
  paperScan: File | null;
  /** Where the filer dragged the QR on the scan; null fields mean bottom-right. */
  qrPlacement?: StampPlacement;
};

const PURPOSE_LIMIT = 255;

/**
 * Internal requests are filed on behalf of an office, so the filer must belong
 * to an active one — an org-wide account has no office to file from.
 */
async function requireDepartment(actor: Actor | null): Promise<{ id: number; name: string }> {
  if (!actor || !actor.can('create internal requests')) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  if (actor.departmentId === null) {
    throw new ActionError(
      'Your account has no active department, so you cannot file internal requests. Ask a system administrator to assign one.',
      403,
    );
  }

  const office = await db.departments.findFirst({
    where: { id: id(actor.departmentId), is_active: true },
    select: { id: true, name: true },
  });

  if (!office) {
    throw new ActionError(
      'Your account has no active department, so you cannot file internal requests. Ask a system administrator to assign one.',
      403,
    );
  }

  return { id: toId(office.id), name: office.name };
}

export async function fileInternalRequest(
  actor: Actor | null,
  input: FilingInput,
): Promise<{ message: string; url: string }> {
  const office = await requireDepartment(actor);
  const filer = actor as Actor;

  const purpose = input.purpose.trim();

  if (purpose === '') {
    throw new ActionError('Say what is being requested.');
  }

  if (purpose.length > PURPOSE_LIMIT) {
    throw new ActionError(`Keep the purpose to ${PURPOSE_LIMIT} characters.`);
  }

  const first = await db.departments.findFirst({
    where: { id: id(input.firstDepartmentId), is_active: true },
    select: { id: true, name: true, code: true },
  });

  if (!first) {
    throw new ActionError('Choose an active office to route this request to first.');
  }

  const recipientId = input.hasHardCopy ? null : input.recipientId;

  if (!input.hasHardCopy) {
    if (recipientId === null) {
      throw new ActionError(
        'Without a hard copy, choose who in the first office receives this request.',
      );
    }

    if (!(await isRecipientAt(recipientId, toId(first.id)))) {
      throw new ActionError('Choose someone who works in the first office.');
    }
  }

  const scanProblem = checkPaperScan(input.paperScan);

  if (scanProblem !== null) {
    throw new ActionError(scanProblem);
  }

  // INT- so internal requests read distinctly from citizen tickets (SPD-).
  const tracking = await qr.generateTrackingNumber('INT');

  const document = await db.documents.create({
    data: {
      tracking_number: tracking,
      document_type: 'Internal Request',
      purpose,
      ...statusChangeData(DocumentStatus.Pending),
      source: 'walk_in',
      origin: ORIGIN_INTERNAL,
      has_hard_copy: input.hasHardCopy,
      requesting_department_id: id(office.id),
      created_by: id(filer.id),
      created_at: now(),
      updated_at: now(),
    },
    select: { id: true },
  });

  const documentId = toId(document.id);

  try {
    const step = await db.request_steps.create({
      data: {
        document_id: document.id,
        // step_order 0 was the filing office's own endorsement hop, which older
        // requests may still carry; new ones start at the office chosen.
        step_order: 1,
        department_id: first.id,
        recipient_id: recipientId === null ? null : id(recipientId),
        action: 'Review and action',
        status: STEP_STATUS.Current,
        started_at: now(),
        created_at: now(),
        updated_at: now(),
      },
      select: { id: true, department_id: true, recipient_id: true, action: true },
    });

    // The filing office physically holds the paper it just created, so the trail
    // starts with it. The receiving office still scans the folder to take custody
    // before acting. A soft copy has no paper, so no trail.
    if (input.hasHardCopy) {
      await db.document_custody_events.create({
        data: {
          document_id: document.id,
          user_id: id(filer.id),
          capture_method: 'manual',
          override_reason: 'Filing office holds the paper at submission.',
          created_at: now(),
          updated_at: now(),
        },
      });
    }

    const generated = await qr.generateAndStore(tracking);

    if (generated.success && generated.relativePath) {
      await db.documents.update({
        where: { id: document.id },
        data: { qr_code_path: generated.relativePath, updated_at: now() },
      });
    }

    if (input.paperScan) {
      const path = await storePrivate(input.paperScan, 'document-attachments');

      await db.document_attachments.create({
        data: {
          document_id: document.id,
          file_path: path,
          uploaded_by: id(filer.id),
          sort_order: 0,
          created_at: now(),
          updated_at: now(),
        },
      });

      // Archive a digital copy with the QR stamped on, beside the untouched
      // original. Never fatal: the request stands without it.
      await ensureStampedCopy(documentId, input.qrPlacement ?? NO_PLACEMENT, filer.id);
    }

    const recipientName = recipientId === null ? null : await nameOf(recipientId);

    await logSystemComment(
      documentId,
      `Internal request filed by ${filer.name} (${office.name}), routed first to ${first.name} (${first.code})${
        input.hasHardCopy ? '' : ` — soft copy only, received by ${recipientName ?? 'the office'}`
      }.`,
    );

    // Ping the first hop: its supervisors, and for a soft copy the person named
    // to receive it. A paper request is on its way to them; a soft copy is
    // already there.
    await tellTheHop(
      { id: documentId, tracking_number: tracking, document_type: 'Internal Request', purpose },
      {
        department_id: toId(step.department_id),
        recipient_id: step.recipient_id === null ? null : toId(step.recipient_id),
        action: step.action,
      },
      filer.id,
    );

    return {
      message: `Filed as ${tracking} — routed to ${first.name}.`,
      url: `/requests/${documentId}/created`,
    };
  } catch (error) {
    // If anything fails downstream, clean up partial database rows
    await db.document_attachments.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.document_custody_events.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.request_steps.deleteMany({ where: { document_id: document.id } }).catch(() => {});
    await db.documents.delete({ where: { id: document.id } }).catch(() => {});

    throw error;
  }
}

async function nameOf(userId: number): Promise<string | null> {
  const user = await db.users.findFirst({ where: { id: id(userId) }, select: { name: true } });

  return user?.name ?? null;
}
