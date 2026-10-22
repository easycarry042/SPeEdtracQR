'use server';

import { revalidatePath } from 'next/cache';
import { db, id, toId } from '@/lib/db';
import { currentActor } from '@/lib/auth/actor';
import { tracking } from '@/lib/config';
import { AUTHOR, VISIBILITY, markReadByStaff, postComment } from '@/lib/domain/comments';
import { canPostOn } from '@/lib/domain/request-review';
import { queueMail } from '@/lib/mail/send';
import { staffMessageMail } from '@/lib/mail/staff-message';
import { logOnDocument } from '@/lib/activity';

export type MessageResult = { ok: boolean; message: string };

/**
 * A staff reply on a request's citizen-facing thread.
 *
 * The citizen is emailed a notice with a link back to their tracking page —
 * never the message body, because a mailbox is not the gated surface. The
 * reply itself is read behind the contact check.
 *
 * The byline the citizen sees is the OFFICE, never this person's name: staff
 * stay anonymous on every public surface.
 */
export async function postMessage(documentId: number, body: string): Promise<MessageResult> {
  const actor = await currentActor();

  if (!actor) {
    return { ok: false, message: 'Please sign in again.' };
  }

  const trimmed = body.trim();

  if (trimmed === '') {
    return { ok: false, message: 'Write a message first.' };
  }

  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      assigned_to: true,
      citizen_name: true,
      citizen_email: true,
      notify_citizen: true,
    },
  });

  if (!document) {
    return { ok: false, message: 'That request no longer exists.' };
  }

  if (!canPostOn(document, actor)) {
    return { ok: false, message: 'Only the assigned staff member or a supervisor can reply here.' };
  }

  await postComment({
    documentId,
    body: trimmed,
    authorId: actor.id,
    authorType: AUTHOR.Staff,
    visibility: VISIBILITY.Public,
  });

  // Replying is also reading: the citizen's messages are marked read so the
  // ticket's unread badge clears.
  await markReadByStaff(documentId);

  if (tracking.notifyCitizen.enabled && document.notify_citizen && document.citizen_email) {
    queueMail(
      staffMessageMail({
        to: document.citizen_email,
        trackingNumber: document.tracking_number,
        citizenName: document.citizen_name,
      }),
    );

    await logOnDocument(toId(document.id), 'Emailed StaffMessage to citizen', actor.id);
  }

  revalidatePath('/', 'layout');

  return { ok: true, message: 'Sent.' };
}

/** Opening a request marks the citizen's messages read and clears its badge. */
export async function markMessagesRead(documentId: number): Promise<MessageResult> {
  const actor = await currentActor();

  if (!actor) {
    return { ok: false, message: 'Please sign in again.' };
  }

  await markReadByStaff(documentId);
  revalidatePath('/', 'layout');

  return { ok: true, message: 'Marked read.' };
}
