import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import type { Actor } from '@/lib/auth/actor';
import { canAccessDocument } from '@/lib/domain/scope';
import { ActionError } from '@/lib/domain/status-actions';
import { deletePrivate } from '@/lib/storage';
import { now } from '@/lib/time';

/**
 * Port of `AttachmentController`'s show/destroy rules. The file itself stays
 * on the private disk; this decides who may reach it.
 */
export async function attachmentFor(attachmentId: number): Promise<{
  filePath: string;
  document: { created_by: number | null; assigned_to: number | null; origin: string };
} | null> {
  const row = await db.document_attachments.findFirst({
    where: { id: id(attachmentId), document: { deleted_at: null } },
    select: {
      file_path: true,
      document: { select: { created_by: true, assigned_to: true, origin: true } },
    },
  });

  if (!row) {
    return null;
  }

  return {
    filePath: row.file_path,
    document: {
      created_by: row.document.created_by === null ? null : toId(row.document.created_by),
      assigned_to: row.document.assigned_to === null ? null : toId(row.document.assigned_to),
      origin: row.document.origin,
    },
  };
}

/**
 * Delete a wrongly placed attachment: whoever may see the request and either
 * scans or files documents. If it was the request's cover file, the cover
 * moves to the next attachment in order.
 */
export async function removeAttachment(
  attachmentId: number,
  actor: Actor | null,
): Promise<{ message: string }> {
  const row = await db.document_attachments.findFirst({
    where: { id: id(attachmentId), document: { deleted_at: null } },
    select: {
      id: true,
      file_path: true,
      document: { select: { id: true, created_by: true, assigned_to: true, attachment_path: true } },
    },
  });

  if (!row) {
    throw new ActionError('That attachment is already gone.');
  }

  const document = {
    created_by: row.document.created_by === null ? null : toId(row.document.created_by),
    assigned_to: row.document.assigned_to === null ? null : toId(row.document.assigned_to),
  };

  if (
    !actor ||
    !canAccessDocument(document, actor) ||
    !(actor.can('scan documents') || actor.can('create documents'))
  ) {
    throw new ActionError('You cannot remove files from this request.', 403);
  }

  await deletePrivate(row.file_path);

  await db.$transaction(async (tx) => {
    if (row.document.attachment_path === row.file_path) {
      const next = await tx.document_attachments.findFirst({
        where: { document_id: row.document.id, NOT: { id: row.id } },
        orderBy: { sort_order: 'asc' },
        select: { file_path: true },
      });

      await tx.documents.update({
        where: { id: row.document.id },
        data: { attachment_path: next?.file_path ?? null, updated_at: now() },
      });
    }

    await tx.document_attachments.delete({ where: { id: row.id } });
  });

  await logOnDocument(toId(row.document.id), 'Removed an attachment', actor.id);

  return { message: 'Attachment removed.' };
}
