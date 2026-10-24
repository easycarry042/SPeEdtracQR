import { db, id, toId } from '@/lib/db';
import { formatNaive, isSameDay, now } from '@/lib/time';
import { broadcastComment, broadcastThreadChanged } from '@/lib/realtime';

/**
 * One message on a request's conversation.
 *
 * Two threads share this table, separated by `visibility`:
 *   - 'public'   — the citizen ↔ staff conversation, shown on the tracking page.
 *   - 'internal' — the staff-only thread; never leaves an authenticated context.
 *
 * `author_type` says who spoke (staff / citizen / system) and `parent_id` groups
 * a question with its answers (one level of nesting).
 */
export const VISIBILITY = {
  Internal: 'internal',
  Public: 'public',
} as const;

export const AUTHOR = {
  Staff: 'staff',
  Citizen: 'citizen',
  System: 'system',
} as const;

export type CommentRow = {
  id: number;
  document_id: number;
  author_id: number | null;
  author_type: string;
  author_name: string | null;
  body: string;
  visibility: string;
  parent_id: number | null;
  staff_read_at: Date | null;
  citizen_read_at: Date | null;
  attachment_path: string | null;
  attachment_name: string | null;
  created_at: Date | null;
  /** Joined for the byline helpers. */
  authorName: string | null;
  authorDepartmentName: string | null;
};

export const COMMENT_SELECT = {
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
} as const;

type RawComment = {
  id: bigint;
  document_id: bigint;
  author_id: bigint | null;
  author_type: string;
  author_name: string | null;
  body: string;
  visibility: string;
  parent_id: bigint | null;
  staff_read_at: Date | null;
  citizen_read_at: Date | null;
  attachment_path: string | null;
  attachment_name: string | null;
  created_at: Date | null;
  author: { name: string; department: { name: string } | null } | null;
};

export function hydrateComment(row: RawComment): CommentRow {
  return {
    id: toId(row.id),
    document_id: toId(row.document_id),
    author_id: row.author_id === null ? null : toId(row.author_id),
    author_type: row.author_type,
    author_name: row.author_name,
    body: row.body,
    visibility: row.visibility,
    parent_id: row.parent_id === null ? null : toId(row.parent_id),
    staff_read_at: row.staff_read_at,
    citizen_read_at: row.citizen_read_at,
    attachment_path: row.attachment_path,
    attachment_name: row.attachment_name,
    created_at: row.created_at,
    authorName: row.author?.name ?? null,
    authorDepartmentName: row.author?.department?.name ?? null,
  };
}

export function isPublic(comment: { visibility: string }): boolean {
  return comment.visibility === VISIBILITY.Public;
}

export function isInternal(comment: { visibility: string }): boolean {
  return comment.visibility === VISIBILITY.Internal;
}

export function isFromCitizen(comment: { author_type: string }): boolean {
  return comment.author_type === AUTHOR.Citizen;
}

export function isFromSystem(comment: { author_type: string }): boolean {
  return comment.author_type === AUTHOR.System;
}

export function hasAttachment(comment: { attachment_path: string | null }): boolean {
  return comment.attachment_path !== null;
}

/** Display name for the message's author, honouring the `author_type`. */
export function authorLabel(comment: CommentRow, citizenName: string | null = null): string {
  switch (comment.author_type) {
    case AUTHOR.System:
      return 'System';
    case AUTHOR.Citizen:
      return comment.author_name || citizenName || 'Citizen';
    default:
      return comment.authorName ?? 'Staff';
  }
}

/**
 * What the CITIZEN sees as the sender.
 *
 * Staff stay anonymous to the public (legal advice): a reply is signed by the
 * office — the author's department, else the request's handling office (a super
 * admin has no department) — never by the person. 'City Hall' is the last resort
 * so a byline is never blank.
 */
export function publicAuthorLabel(
  comment: CommentRow,
  documentDepartmentName: string | null = null,
): string {
  switch (comment.author_type) {
    case AUTHOR.System:
      return 'Update';
    case AUTHOR.Citizen:
      return 'You';
    default:
      return comment.authorDepartmentName ?? documentDepartmentName ?? 'City Hall';
  }
}

/**
 * Shape for the Look Up desk's Messages panel. Two places build it: the desk
 * ships the whole thread with the row, and the post endpoint returns the one
 * just posted so the panel can append it without reloading the desk underneath.
 */
export type DeskPayload = {
  from: string;
  author: string;
  body: string;
  time: string;
  read: boolean;
  attachment: { name: string; url: string } | null;
};

export function deskPayload(comment: CommentRow, citizenName: string | null = null): DeskPayload {
  return {
    from: comment.author_type,
    author: authorLabel(comment, citizenName),
    body: comment.body,
    attachment: hasAttachment(comment)
      ? {
          name: comment.attachment_name || (comment.attachment_path ?? '').split('/').pop() || 'file',
          url: `/documents/comments/${comment.id}/attachment`,
        }
      : null,
    // Today's traffic reads as "Today 10:40 AM"; anything older is dated, which
    // is how a chat reads.
    time: comment.created_at
      ? isSameDay(comment.created_at, now())
        ? `Today ${formatNaive(comment.created_at, 'g:i A')}`
        : formatNaive(comment.created_at, 'M j, g:i A')
      : '',
    read: comment.citizen_read_at !== null,
  };
}

/* ── Writing ────────────────────────────────────────────────────────────────── */

export type NewComment = {
  documentId: number;
  body: string;
  authorId?: number | null;
  authorType?: string;
  authorName?: string | null;
  visibility?: string;
  parentId?: number | null;
  attachmentPath?: string | null;
  attachmentName?: string | null;
  /** Citizen messages arrive already read by their own author. */
  staffReadAt?: Date | null;
  citizenReadAt?: Date | null;
};

/**
 * Posts a message and fires the broadcasts the Eloquent model did on `created`:
 * the private staff feed always, plus a content-free ping on the request's
 * public channel when the message is citizen-visible.
 */
export async function postComment(input: NewComment): Promise<CommentRow> {
  const row = await db.document_comments.create({
    data: {
      document_id: id(input.documentId),
      author_id: input.authorId === null || input.authorId === undefined ? null : id(input.authorId),
      author_type: input.authorType ?? AUTHOR.Staff,
      author_name: input.authorName ?? null,
      body: input.body,
      visibility: input.visibility ?? VISIBILITY.Internal,
      parent_id: input.parentId === null || input.parentId === undefined ? null : id(input.parentId),
      attachment_path: input.attachmentPath ?? null,
      attachment_name: input.attachmentName ?? null,
      staff_read_at: input.staffReadAt ?? null,
      citizen_read_at: input.citizenReadAt ?? null,
      created_at: now(),
      updated_at: now(),
    },
    select: COMMENT_SELECT,
  });

  const comment = hydrateComment(row);

  broadcastComment(comment.document_id, {
    id: comment.id,
    author: authorLabel(comment),
    author_type: comment.author_type,
    body: comment.body,
    visibility: comment.visibility,
    parent_id: comment.parent_id,
    timestamp: comment.created_at ? formatNaive(comment.created_at, 'M d, Y h:i A') : null,
  });

  // Any citizen-visible message — a staff reply, a system update, the citizen's
  // own — pings the request's public channel so an open Speedy refreshes.
  if (isPublic(comment)) {
    const document = await db.documents.findUnique({
      where: { id: id(comment.document_id) },
      select: { tracking_number: true },
    });

    if (document) {
      broadcastThreadChanged(document.tracking_number, comment.author_type);
    }
  }

  return comment;
}

/**
 * Record a system event (status change, assignment, return) into the unified
 * per-document feed as an internal, author-less note.
 */
export function logSystemComment(documentId: number, body: string): Promise<CommentRow> {
  return postComment({
    documentId,
    body,
    authorId: null,
    authorType: AUTHOR.System,
    visibility: VISIBILITY.Internal,
  });
}

/**
 * Citizen messages staff have not read yet — the unread badge on a ticket.
 *
 * Counts the WHOLE thread, replies included: a citizen's answer under a staff
 * message is a reply, so counting only top-level posts left it permanently
 * unread and its badge permanently lit.
 */
export async function unreadByStaffCount(documentId: number): Promise<number> {
  return db.document_comments.count({
    where: {
      document_id: id(documentId),
      author_type: AUTHOR.Citizen,
      staff_read_at: null,
    },
  });
}

export async function markReadByStaff(documentId: number): Promise<void> {
  await db.document_comments.updateMany({
    where: { document_id: id(documentId), author_type: AUTHOR.Citizen, staff_read_at: null },
    data: { staff_read_at: now() },
  });
}

export async function markReadByCitizen(documentId: number): Promise<void> {
  await db.document_comments.updateMany({
    where: {
      document_id: id(documentId),
      author_type: { not: AUTHOR.Citizen },
      visibility: VISIBILITY.Public,
      citizen_read_at: null,
    },
    data: { citizen_read_at: now() },
  });
}

/**
 * The citizen-visible thread: what the tracking page may render, and the only
 * thing a citizen-facing endpoint is ever allowed to return.
 */
export async function citizenVisibleThread(documentId: number): Promise<CommentRow[]> {
  const rows = await db.document_comments.findMany({
    where: { document_id: id(documentId), visibility: VISIBILITY.Public },
    orderBy: { id: 'asc' },
    select: COMMENT_SELECT,
  });

  return rows.map(hydrateComment);
}
