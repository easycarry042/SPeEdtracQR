import { usersWithPermission, type Recipient } from '@/lib/auth/rbac';
import { notifyMany, type NotificationData } from '@/lib/domain/notifications';
import { routes } from '@/lib/routes';

/**
 * Who hears about an internal request, and what they are told.
 *
 * Port of the internal half of `App\Notifications\DocumentEvent` plus its
 * recipient resolvers. Written against permissions rather than roles because a
 * named soft-copy recipient may be plain staff.
 */
type DocumentLike = {
  id: number;
  tracking_number: string;
  document_type: string;
  purpose?: string | null;
};

type StepLike = { department_id: number; recipient_id: number | null };

/** What the request is about, for a notification body. */
function subject(document: DocumentLike): string {
  return document.purpose ?? document.document_type;
}

/** Everyone at an office who may act on internal requests, minus the actor. */
export async function departmentSupervisors(
  departmentId: number,
  excludeUserId: number | null = null,
): Promise<Recipient[]> {
  const people = await usersWithPermission('act on internal requests', departmentId);

  return excludeUserId === null ? people : people.filter((person) => person.id !== excludeUserId);
}

/**
 * Who hears that an internal request reached an office: its supervisors, plus
 * the person named to receive a soft copy there. Minus the actor.
 */
export async function hopRecipients(
  step: StepLike,
  excludeUserId: number | null = null,
): Promise<number[]> {
  const supervisors = await departmentSupervisors(step.department_id, excludeUserId);
  const ids = new Set(supervisors.map((person) => person.id));

  // The named recipient hears about it even as plain staff — the request is
  // theirs to act on, so leaving them out would mean nobody told them.
  if (step.recipient_id !== null && step.recipient_id !== excludeUserId) {
    ids.add(step.recipient_id);
  }

  return [...ids];
}

export function internalHopArrived(document: DocumentLike, action: string): NotificationData {
  return {
    event: 'internal_hop',
    title: 'Internal request awaiting your office',
    body: `${subject(document)} — ${action}.`,
    url: routes.requestsShow(document.id),
    tracking_number: document.tracking_number,
  };
}

/** Someone at the next office picked it up — tell whoever filed it. */
export function internalReceived(
  document: DocumentLike,
  who: string,
  office: string,
): NotificationData {
  return {
    event: 'internal_received',
    title: 'Internal request received',
    body: `${subject(document)} — received by ${who} at ${office}.`,
    url: routes.requestsShow(document.id),
    tracking_number: document.tracking_number,
  };
}

/** The chain moved (approved/denied/returned/completed) — tell the filing office. */
export function internalOutcome(document: DocumentLike, summary: string): NotificationData {
  return {
    event: 'internal_outcome',
    title: 'Internal request update',
    body: `${subject(document)} — ${summary}`,
    url: routes.requestsShow(document.id),
    tracking_number: document.tracking_number,
  };
}

/** Bell a hop's people that the request is now theirs. */
export async function tellTheHop(
  document: DocumentLike,
  step: StepLike & { action: string },
  actorId: number,
): Promise<void> {
  const recipients = await hopRecipients(step, actorId);

  if (recipients.length > 0) {
    await notifyMany(recipients, internalHopArrived(document, step.action));
  }
}
