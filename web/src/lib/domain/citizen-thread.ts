import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import { AUTHOR, VISIBILITY, postComment, publicAuthorLabel, hydrateComment, COMMENT_SELECT } from '@/lib/domain/comments';
import { departmentTriagers } from '@/lib/domain/triagers';
import { notify, notifyMany } from '@/lib/domain/notifications';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { citizenMessageMail } from '@/lib/mail/citizen-notices';
import { queueMail } from '@/lib/mail/send';
import { routes } from '@/lib/routes';
import { storePrivate } from '@/lib/storage';
import { formatNaive, now } from '@/lib/time';

/**
 * Port of `App\Support\CitizenThread` — a request's conversation as the
 * citizen sees it: the citizen-visible messages only, oldest first, every
 * staff reply named by OFFICE (staff are anonymous to the public).
 *
 * Every function assumes the caller has already confirmed the citizen
 * (`citizen-thread-access`): reading the thread needs that, not only writing.
 * Internal staff notes are never loaded here, so they cannot leak.
 */
export type ThreadMessage = {
  id: number;
  /** citizen | staff | system — how the bubble is drawn. */
  from: string;
  name: string;
  body: string;
  at: string | null;
  attachment: { name: string; url: string } | null;
};

export type TrackableDocument = {
  id: bigint;
  tracking_number: string;
  document_type: string;
  status: string;
  citizen_name: string | null;
  citizen_email: string | null;
  citizen_contact: string | null;
  department_id: bigint | null;
  assigned_to: bigint | null;
  department: { name: string } | null;
  assignee: { name: string; email: string } | null;
};

/**
 * The same rule every public page uses: a real, non-internal request. Internal
 * office-to-office requests have no citizen and are "not found" to the public.
 */
export async function findTrackable(trackingNumber: string): Promise<TrackableDocument | null> {
  return db.documents.findFirst({
    where: {
      tracking_number: trackingNumber.trim().toUpperCase(),
      origin: { not: ORIGIN_INTERNAL },
      deleted_at: null,
    },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      citizen_name: true,
      citizen_email: true,
      citizen_contact: true,
      department_id: true,
      assigned_to: true,
      department: { select: { name: true } },
      assignee: { select: { name: true, email: true } },
    },
  });
}

/** The thread, flattened: replies sit in time order, the way a chat reads. */
export async function messages(document: TrackableDocument): Promise<ThreadMessage[]> {
  const rows = await db.document_comments.findMany({
    where: { document_id: document.id, visibility: VISIBILITY.Public },
    orderBy: [{ created_at: 'asc' }, { id: 'asc' }],
    select: COMMENT_SELECT,
  });

  return rows.map(hydrateComment).map((comment) => ({
    id: comment.id,
    from: comment.author_type,
    name: publicAuthorLabel(comment, document.department?.name ?? null),
    body: comment.body,
    at: comment.created_at ? formatNaive(comment.created_at, 'M j, g:i A') : null,
    attachment: comment.attachment_path
      ? { name: comment.attachment_name || 'Attachment', url: routes.trackMessagesAttachment(comment.id) }
      : null,
  }));
}

const fromOffice = (documentId: bigint) => ({
  document_id: documentId,
  visibility: VISIBILITY.Public,
  author_type: { not: AUTHOR.Citizen },
  citizen_read_at: null,
});

/** Office messages the citizen has not seen yet. */
export function unreadCount(documentId: bigint): Promise<number> {
  return db.document_comments.count({ where: fromOffice(documentId) });
}

/** The citizen has the thread open: the office's messages are read. */
export async function markRead(documentId: bigint): Promise<void> {
  await db.document_comments.updateMany({ where: fromOffice(documentId), data: { citizen_read_at: now() } });
}

/**
 * Whether `parentId` is a top-level message in THIS request's public thread.
 * An internal note's id must not thread a citizen message under it, nor
 * confirm that the note exists.
 */
export async function isReplyTarget(document: TrackableDocument, parentId: number): Promise<boolean> {
  const row = await db.document_comments.findFirst({
    where: { id: id(parentId), document_id: document.id, visibility: VISIBILITY.Public, parent_id: null },
    select: { id: true },
  });

  return row !== null;
}

/** The citizen writes to the office; the office is told. */
export async function post(
  document: TrackableDocument,
  body: string,
  attachment: File | null = null,
  parentId: number | null = null,
): Promise<void> {
  const attachmentPath = attachment
    ? await storePrivate(attachment, `message-attachments/${document.tracking_number}`)
    : null;

  await postComment({
    documentId: toId(document.id),
    body,
    authorId: null,
    authorType: AUTHOR.Citizen,
    authorName: document.citizen_name,
    visibility: VISIBILITY.Public,
    parentId,
    // The citizen has obviously read their own message; staff have not.
    citizenReadAt: now(),
    attachmentPath,
    attachmentName: attachment?.name ?? null,
  });

  await logOnDocument(toId(document.id), 'Citizen posted a message on the request');

  await notifyStaff(document, body);
}

/**
 * Tell the office a citizen is waiting: the assignee gets the bell and an
 * email; with nobody assigned yet, the office's triagers get the bell.
 */
async function notifyStaff(document: TrackableDocument, body: string): Promise<void> {
  const data = {
    event: 'citizen_message',
    title: 'New message from the citizen',
    body: body.length > 120 ? `${body.slice(0, 117)}...` : body,
    url: routes.trackShow(document.tracking_number),
    tracking_number: document.tracking_number,
  };

  if (document.assigned_to !== null && document.assignee) {
    await notify(toId(document.assigned_to), data);

    if (document.assignee.email) {
      queueMail(
        citizenMessageMail({
          to: document.assignee.email,
          recipientName: document.assignee.name,
          trackingNumber: document.tracking_number,
          citizenName: document.citizen_name,
          body,
        }),
      );
    }

    return;
  }

  const triagers = await departmentTriagers(document.department_id === null ? null : toId(document.department_id));

  await notifyMany(
    triagers.map((triager) => triager.id),
    data,
  );
}

/** A message attachment the citizen may download, or null for "not found". */
export async function citizenAttachment(commentId: number): Promise<{
  path: string;
  name: string;
  trackingNumber: string;
} | null> {
  const row = await db.document_comments.findFirst({
    where: {
      id: id(commentId),
      visibility: VISIBILITY.Public,
      attachment_path: { not: null },
      document: { origin: { not: ORIGIN_INTERNAL }, deleted_at: null },
    },
    select: { attachment_path: true, attachment_name: true, document: { select: { tracking_number: true } } },
  });

  if (!row?.attachment_path) {
    return null;
  }

  return {
    path: row.attachment_path,
    name: row.attachment_name || row.attachment_path.split('/').pop() || 'attachment',
    trackingNumber: row.document.tracking_number,
  };
}
