import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import type { Actor } from '@/lib/auth/actor';
import {
  canAccessDocument,
  ORIGIN_INTERNAL,
  toDocumentAccessSubject,
  type DocumentAccessSubject,
} from '@/lib/domain/scope';
import { ActionError } from '@/lib/domain/status-actions';
import { deletePrivate } from '@/lib/storage';
import { now } from '@/lib/time';

const ATTACHMENT_DOCUMENT_SELECT = {
  created_by: true,
  assigned_to: true,
  origin: true,
  department_id: true,
  requesting_department_id: true,
  accepted_at: true,
  document_type: true,
  request_steps: { select: { department_id: true } },
} as const;

async function resolveHandlerIds(doc: {
  origin: string;
  accepted_at: Date | null;
  document_type: string;
}): Promise<number[]> {
  if (doc.origin === ORIGIN_INTERNAL || doc.accepted_at !== null || !doc.document_type) {
    return [];
  }

  const handlers =
    (await db.request_type_handlers?.findMany({
      where: { requestType: { name: doc.document_type } },
      select: { user_id: true },
    })) ?? [];

  return handlers.map((entry) => toId(entry.user_id));
}

/**
 * Port of `AttachmentController`'s show/destroy rules. The file itself stays
 * on the private disk; this decides who may reach it.
 */
export async function attachmentFor(attachmentId: number): Promise<{
  filePath: string;
  document: DocumentAccessSubject;
} | null> {
  const row = await db.document_attachments.findFirst({
    where: { id: id(attachmentId), document: { deleted_at: null } },
    select: {
      file_path: true,
      document: { select: ATTACHMENT_DOCUMENT_SELECT },
    },
  });

  if (!row) {
    return null;
  }

  const handlerIds = await resolveHandlerIds(row.document);

  return {
    filePath: row.file_path,
    document: toDocumentAccessSubject(row.document, handlerIds),
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
      document: {
        select: {
          id: true,
          attachment_path: true,
          ...ATTACHMENT_DOCUMENT_SELECT,
        },
      },
    },
  });

  if (!row) {
    throw new ActionError('That attachment is already gone.');
  }

  const handlerIds = await resolveHandlerIds(row.document);
  const document = toDocumentAccessSubject(row.document, handlerIds);

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
