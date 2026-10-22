import { db, id, toId } from '@/lib/db';
import { logOnDocument } from '@/lib/activity';
import { routes } from '@/lib/routes';
import { ActionError } from '@/lib/domain/status-actions';
import { notifyMany } from '@/lib/domain/notifications';
import { departmentTriagers } from '@/lib/domain/triagers';
import type { Actor } from '@/lib/auth/actor';

/**
 * Flagging a request for whoever owns it — a nudge, not a reassignment.
 *
 * Port of `DocumentNudgeController`. The super admin never claims or reroutes
 * the work from the command center or the Requests page; they only bell the
 * person who should act on it.
 */
const NOTE_LIMIT = 280;

/**
 * A super admin may nudge about anything; a supervisor only about their own
 * office's work. Without the office check, `/reports`' nudge button would let
 * one office prod another's staff.
 */
export function canNudge(
  actor: Actor | null,
  document: { department_id: number | null },
): boolean {
  if (!actor) {
    return false;
  }

  if (actor.can('manage system')) {
    return true;
  }

  return actor.departmentId !== null && document.department_id === actor.departmentId;
}

export async function nudge(
  documentId: number,
  actor: Actor | null,
  note: string | null = null,
): Promise<{ message: string }> {
  if (!actor) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const document = await db.documents.findFirst({
    where: { id: id(documentId), deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      department_id: true,
      assigned_to: true,
      assignee: { select: { id: true, name: true } },
      department: { select: { name: true } },
    },
  });

  if (!document) {
    throw new ActionError('That request no longer exists.');
  }

  const departmentId = document.department_id === null ? null : toId(document.department_id);

  if (!canNudge(actor, { department_id: departmentId })) {
    throw new ActionError('This action is unauthorized.', 403);
  }

  const trimmed = (note ?? '').trim();

  if (trimmed.length > NOTE_LIMIT) {
    throw new ActionError(`Keep the note to ${NOTE_LIMIT} characters.`);
  }

  /**
   * The assignee if there is one, otherwise the supervisors of the office
   * holding it. It used to be every supervisor in the city, so one reminder
   * about a GSO request rang ten offices' bells.
   */
  const recipients = document.assignee
    ? [toId(document.assignee.id)]
    : (await departmentTriagers(departmentId, actor.id)).map((triager) => triager.id);

  if (recipients.length === 0) {
    return { message: 'No one is available to notify for this document yet.' };
  }

  const label = document.document_type || 'A document';

  await notifyMany(recipients, {
    event: 'nudge',
    title: 'Flagged for your attention',
    body:
      `${label} (${document.tracking_number}) is overdue. ${actor.name} asked you to follow up.` +
      (trimmed ? ` Note: ${trimmed}` : ''),
    url: routes.trackShow(document.tracking_number),
    tracking_number: document.tracking_number,
  });

  await logOnDocument(
    toId(document.id),
    'Nudged the responsible staff about an overdue document',
    actor.id,
    trimmed ? { note: trimmed } : null,
  );

  const who =
    document.assignee?.name ??
    (document.department ? `the ${document.department.name} supervisors` : 'the supervisors');

  return { message: `Notified ${who} about ${document.tracking_number}.` };
}
