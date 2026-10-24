import { db, id, toId } from '@/lib/db';
import { tracking } from '@/lib/config';
import { addDays, diffInCalendarDays, formatNaive, isFuture, isSameDay, now, startOfDay } from '@/lib/time';
import { logOnDocument, logOnResource } from '@/lib/activity';
import { queueMail } from '@/lib/mail/send';
import { OUTCOME, type BookingOutcome, bookingUpdatedMail } from '@/lib/mail/booking-updated';
import { broadcastStatusUpdated } from '@/lib/realtime';
import { DocumentStatus, FINISHABLE, isTerminal, label as statusLabel } from '@/lib/domain/document-status';
import { statusChangeData, statusOf, unverifiedMandatoryRequirements } from '@/lib/domain/document';
import { notifyOfficeOfProgress } from '@/lib/domain/office-progress';
import { logSystemComment } from '@/lib/domain/comments';
import {
  BOOKING_STATUS,
  type BookingCore,
  type ResourceCore,
  approvedConflicts,
  daysOverdue,
  hydrateBooking,
  isApproved,
  isAwaitingPickup,
  isCancelled,
  isClosed,
  isEquipment,
  isFacility,
  isOnLoan,
  isReleased,
  serviceableUnits,
  units,
  unitsAvailable,
  unitsOnHand,
  unitsOutstanding,
  unitsRequested,
} from '@/lib/domain/lending';

/**
 * The counter's side of a borrowing, shared by the Resources page and the
 * Booking calendar so the two can never apply different rules.
 *
 *   approve  — staff grant a number of units; refused if the stock cannot cover it
 *   release  — the items are handed over (they leave the stockroom)
 *   receive  — some or all come back; broken ones go out of service, missing
 *              ones are written off; the loan closes when nothing is outstanding
 *   finish   — a facility is done early
 *   remind   — an overdue borrower is emailed
 *
 * Each returns null on success or the sentence to show the person instead.
 */
export type Refusal = string | null;

type Actor = { id: number; name: string };

type LoadedBooking = {
  booking: BookingCore;
  resource: ResourceCore | null;
  document: {
    id: number;
    tracking_number: string;
    document_type: string;
    department_id: number | null;
    status: string;
    assigned_to: number | null;
    assigned_at: Date | null;
    accepted_at: Date | null;
    claimed_at: Date | null;
    released_by: number | null;
    citizen_name: string | null;
    citizen_email: string | null;
    notify_citizen: boolean;
    assigneeName: string | null;
    return_notes?: string | null;
  } | null;
  returnNotes: string | null;
};

const RESOURCE_SELECT = {
  id: true,
  name: true,
  kind: true,
  total_quantity: true,
  damaged_quantity: true,
  capacity: true,
} as const;

/** Loads a booking with the resource and request the rules below need. */
export async function loadBooking(bookingId: number): Promise<LoadedBooking | null> {
  const row = await db.bookings.findUnique({
    where: { id: id(bookingId) },
    select: {
      id: true,
      document_id: true,
      resource_id: true,
      quantity: true,
      starts_at: true,
      ends_at: true,
      status: true,
      released_at: true,
      returned_at: true,
      returned_quantity: true,
      damaged_quantity: true,
      lost_quantity: true,
      return_notes: true,
      resource: { select: RESOURCE_SELECT },
      document: {
        select: {
          id: true,
          tracking_number: true,
          document_type: true,
          department_id: true,
          status: true,
          quantity: true,
          assigned_to: true,
          assigned_at: true,
          accepted_at: true,
          claimed_at: true,
          released_by: true,
          citizen_name: true,
          citizen_email: true,
          notify_citizen: true,
          deleted_at: true,
          assignee: { select: { name: true } },
        },
      },
    },
  });

  if (!row) {
    return null;
  }

  // A soft-deleted request holds nothing — Laravel's `whereHas('document')`
  // excluded it, because the default Eloquent scope hid trashed rows.
  const document = row.document && row.document.deleted_at === null ? row.document : null;

  return {
    booking: hydrateBooking({ ...row, document: document ? { quantity: document.quantity } : null }),
    resource: row.resource
      ? { ...row.resource, id: toId(row.resource.id) }
      : null,
    returnNotes: row.return_notes,
    document: document
      ? {
          id: toId(document.id),
          tracking_number: document.tracking_number,
          document_type: document.document_type,
          department_id: document.department_id === null ? null : toId(document.department_id),
          status: document.status,
          assigned_to: document.assigned_to === null ? null : toId(document.assigned_to),
          assigned_at: document.assigned_at,
          accepted_at: document.accepted_at,
          claimed_at: document.claimed_at,
          released_by: document.released_by === null ? null : toId(document.released_by),
          citizen_name: document.citizen_name,
          citizen_email: document.citizen_email,
          notify_citizen: document.notify_citizen,
          assigneeName: document.assignee?.name ?? null,
        }
      : null,
  };
}

/**
 * Why a booking cannot be granted for the window, or null if it can. The
 * booking itself is excluded, so it never competes with its own approval.
 *
 * A facility is taken or it is not; equipment is a pile several borrowers share
 * until it runs out. Both are refused rather than warned, so the office never
 * promises the same chairs twice.
 */
export async function refusal(
  loaded: LoadedBooking,
  starts: Date,
  ends: Date,
  wantedUnits: number | null = null,
): Promise<Refusal> {
  const { booking, resource } = loaded;

  if (!resource) {
    return null;
  }

  if (isFacility(resource)) {
    const clashes = await approvedConflicts(resource.id, starts, ends, booking.id);

    return clashes.length > 0
      ? `${resource.name} is already reserved during that time. Choose another time, or reschedule or cancel the other reservation first.`
      : null;
  }

  const wanted = wantedUnits ?? units(booking);

  if (wanted < 1) {
    return 'Enter how many to lend — a whole number, 1 or more.';
  }

  if (serviceableUnits(resource) < 1) {
    return `Cannot approve — no count has been set for ${resource.name} yet. Set how many the office owns on the Resources page first.`;
  }

  const free = await unitsAvailable(resource, starts, ends, booking.id);

  if (wanted > free) {
    return (
      `Not enough ${resource.name}: this lends ${wanted}, but only ${free} are free for those dates.` +
      ' Lend fewer, reschedule it, or wait for items on loan to come back.'
    );
  }

  return null;
}

/**
 * Why staff cannot approve this booking yet, or null. A reservation is part of
 * the request, so it follows the request's own order: the office supervisor
 * assigns it, the staff member accepts it, and only then is the stock promised.
 */
export function requestNotReady(loaded: LoadedBooking): Refusal {
  const { document } = loaded;

  if (!document) {
    return null;
  }

  const status = statusOf(document);

  if (isTerminal(status)) {
    return `The request is already ${statusLabel(status)}.`;
  }

  if (document.assigned_to === null) {
    return 'Not yet — the office supervisor has to assign this request to a staff member first.';
  }

  if (document.accepted_at === null) {
    return `Not yet — ${document.assigneeName ?? 'the assigned staff member'} has to accept the request first.`;
  }

  return null;
}

/**
 * Grant a booking. For equipment, `grantedUnits` is how many staff are lending
 * — a whole number, defaulting to what the citizen asked for.
 */
export async function approve(
  bookingId: number,
  grantedUnits: number | null = null,
): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  if (isCancelled(loaded.booking)) {
    return 'This booking was cancelled.';
  }

  if (isApproved(loaded.booking)) {
    return 'This booking is already approved.';
  }

  const notReady = requestNotReady(loaded);

  if (notReady) {
    return notReady;
  }

  const { booking, resource } = loaded;
  const granted =
    resource && isEquipment(resource) ? (grantedUnits ?? unitsRequested(booking)) : null;

  const refused = await refusal(loaded, booking.starts_at, booking.ends_at, granted);

  if (refused) {
    return refused;
  }

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { status: BOOKING_STATUS.Approved, quantity: granted, updated_at: now() },
  });

  const what = granted
    ? `${granted.toLocaleString('en-US')} × ${resource?.name ?? 'the items'}`
    : (resource?.name ?? 'the items');

  await log(loaded, `Approved booking for ${what}.`);
  await notifyCitizen({ ...loaded, booking: { ...booking, quantity: granted, status: BOOKING_STATUS.Approved } }, OUTCOME.Approved);

  return null;
}

/** "Oct 25 – Oct 29" (or one day's times for a same-day reservation). */
export function window(booking: Pick<BookingCore, 'starts_at' | 'ends_at'>): string {
  return isSameDay(booking.starts_at, booking.ends_at)
    ? `${formatNaive(booking.starts_at, 'M j, g:i A')} – ${formatNaive(booking.ends_at, 'g:i A')}`
    : `${formatNaive(booking.starts_at, 'M j')} – ${formatNaive(booking.ends_at, 'M j')}`;
}

/** "10 × Monoblock Chairs", or the facility's name. */
function what(loaded: LoadedBooking): string {
  const name = loaded.resource?.name ?? 'the items';

  return loaded.resource && isEquipment(loaded.resource)
    ? `${units(loaded.booking).toLocaleString('en-US')} × ${name}`
    : name;
}

/**
 * Move a booking to a new window. An approved one is re-checked against the
 * stock (or the venue's other reservations) for the new dates; one already
 * handed over cannot move. The request's claiming date follows the pickup, so
 * the calendar and the request never show two different days.
 */
export async function reschedule(
  bookingId: number,
  starts: Date,
  ends: Date,
  moveClaimDate = true,
): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  if (isReleased(loaded.booking)) {
    return `These items were handed over on ${formatNaive(loaded.booking.released_at!, 'M j')}, so the pickup date can no longer move.`;
  }

  if (isApproved(loaded.booking)) {
    const refused = await refusal(loaded, starts, ends);

    if (refused) {
      return `Can't move it to ${formatNaive(starts, 'M j')}: ${refused}`;
    }
  }

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { starts_at: starts, ends_at: ends, updated_at: now() },
  });

  if (moveClaimDate && loaded.document) {
    await db.documents.update({
      where: { id: id(loaded.document.id) },
      data: { claim_date: startOfDay(starts), updated_at: now() },
    });
  }

  const moved = { ...loaded, booking: { ...loaded.booking, starts_at: starts, ends_at: ends } };

  await log(moved, `Rescheduled ${what(moved)} to ${window({ starts_at: starts, ends_at: ends })}.`);
  await notifyCitizen(moved, OUTCOME.Rescheduled);

  return null;
}

/**
 * Staff set or moved a request's claiming date (Review modal, or advancing its
 * stage): carry its booking along. The pickup moves to that day at the same
 * time, and the return date by the same number of days, so the borrowing keeps
 * its length. Null when there is nothing to move.
 */
export async function followClaimDate(documentId: number, claimDate: Date): Promise<Refusal> {
  const row = await db.bookings.findFirst({
    where: { document_id: id(documentId) },
    select: { id: true },
  });

  if (!row) {
    return null;
  }

  const loaded = await loadBooking(toId(row.id));

  // Already collected (or finished): the pickup has happened, so there is
  // nothing left for a claiming date to move.
  if (
    !loaded ||
    isCancelled(loaded.booking) ||
    isClosed(loaded.booking) ||
    isReleased(loaded.booking)
  ) {
    return null;
  }

  const days = diffInCalendarDays(loaded.booking.starts_at, claimDate);

  if (days === 0) {
    return null;
  }

  return reschedule(
    loaded.booking.id,
    addDays(loaded.booking.starts_at, days),
    addDays(loaded.booking.ends_at, days),
    false,
  );
}

/**
 * Change when a borrowing is due back — usually the borrower asking to keep the
 * chairs longer, sometimes back early. The pickup stays; the extra days are
 * checked against the stock (or the venue's other reservations), and an overdue
 * loan given a new date stops being overdue.
 */
export async function extend(bookingId: number, returnBy: Date): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  const { booking, resource } = loaded;

  if (!isApproved(booking) || isClosed(booking)) {
    return 'Only an approved borrowing that is still open can change its return date.';
  }

  const old = booking.ends_at;
  // Keep the time of day from the old return date; only the day moves.
  const ends = new Date(
    Date.UTC(
      returnBy.getUTCFullYear(),
      returnBy.getUTCMonth(),
      returnBy.getUTCDate(),
      old.getUTCHours(),
      old.getUTCMinutes(),
      old.getUTCSeconds(),
    ),
  );

  if (ends.getTime() <= booking.starts_at.getTime()) {
    return `The return date must be after the pickup (${formatNaive(booking.starts_at, 'M j')}).`;
  }

  if (ends.getTime() < startOfDay(now()).getTime()) {
    return 'The return date cannot be in the past.';
  }

  if (ends.getTime() === old.getTime()) {
    return 'That is already the return date.';
  }

  if (ends.getTime() > old.getTime()) {
    // Only what is still out needs the extra days.
    const outstanding = isReleased(booking) ? unitsOutstanding(booking) : units(booking);
    const refused = await refusal(
      loaded,
      booking.starts_at,
      ends,
      resource && isEquipment(resource) ? outstanding : null,
    );

    if (refused) {
      return `Can't keep it until ${formatNaive(ends, 'M j')}: ${refused}`;
    }
  }

  // The claiming date is the pickup, which does not move.
  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { ends_at: ends, overdue_reminded_at: null, updated_at: now() },
  });

  const extended = { ...loaded, booking: { ...booking, ends_at: ends } };

  await log(
    extended,
    `Return date for ${what(extended)} changed from ${formatNaive(old, 'M j')} to ${formatNaive(ends, 'M j')}.`,
  );
  await notifyCitizen(extended, OUTCOME.Extended);

  return null;
}

/** The items leave the stockroom with the borrower. */
export async function release(bookingId: number, by: Actor): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  const { booking, resource, document } = loaded;

  if (!isAwaitingPickup(booking)) {
    return 'Only an approved booking that has not been handed over can be released.';
  }

  if (resource && isEquipment(resource)) {
    // Approval checked the dates; this checks the shelf. An earlier borrower
    // who is late bringing theirs back can leave it short.
    const onHand = await unitsOnHand(resource);

    if (units(booking) > onHand) {
      return (
        `Only ${onHand} ${resource.name} are in the stockroom right now — some are still out or overdue.` +
        ' Record those returns first, or lend fewer.'
      );
    }
  }

  if (document) {
    const status = statusOf(document);

    // Pending → Ongoing → Done: the request must be accepted (Ongoing), with
    // its required files checked — the same check Finish request makes.
    if (!FINISHABLE.includes(status) && status !== DocumentStatus.Completed) {
      return `The request is still ${statusLabel(status)}. It must be accepted (Ongoing) before the items are handed over.`;
    }

    if (status !== DocumentStatus.Completed) {
      const unchecked = await unverifiedMandatoryRequirements(document.id);

      if (unchecked.length > 0) {
        return `Check the required files on the request first: ${unchecked.map((r) => r.label).join(', ')}.`;
      }
    }
  }

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { released_at: now(), released_by: id(by.id), updated_at: now() },
  });

  await log(loaded, `Handed over ${what(loaded)}.`);

  if (document) {
    await markRequestReleased(document, by);
  }

  return null;
}

/**
 * Handing the items over is the request's release: an Ongoing request becomes
 * Done (what the citizen came for has been given), the release is stamped as it
 * is for a document claimed at the counter, and the office is told it is done.
 */
async function markRequestReleased(
  document: NonNullable<LoadedBooking['document']>,
  by: Actor,
): Promise<void> {
  const from = statusOf(document);
  const completing = FINISHABLE.includes(from);

  await db.documents.update({
    where: { id: id(document.id) },
    data: {
      ...(completing ? statusChangeData(DocumentStatus.Completed) : {}),
      // `??=` — a document already claimed keeps its original stamps.
      claimed_at: document.claimed_at ?? now(),
      released_by: document.released_by === null ? id(by.id) : id(document.released_by),
      updated_at: now(),
    },
  });

  if (!completing) {
    return;
  }

  await logOnDocument(
    document.id,
    `Finished: ${statusLabel(from)} → ${statusLabel(DocumentStatus.Completed)} (items handed over)`,
    by.id,
    { from, to: DocumentStatus.Completed },
  );

  await broadcastStatusUpdated(document.id);
  await logSystemComment(document.id, `Done — items handed over by ${by.name}`);
  await notifyOfficeOfProgress(document, by, 'finished');
}

/**
 * Record items coming back. `returned` is everything that came back (including
 * `damaged`, which go out of service); `lost` is written off the office's count.
 * Whatever is left stays on loan.
 */
export async function receive(
  bookingId: number,
  returned: number,
  damaged: number,
  lost: number,
  notes: string | null,
  by: Actor,
): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  const { booking, resource } = loaded;

  if (!isOnLoan(booking)) {
    return 'Only items that were handed over can be returned.';
  }

  const outstanding = unitsOutstanding(booking);

  if (returned + lost < 1) {
    return 'Enter how many came back (or how many are missing).';
  }

  if (returned + lost > outstanding) {
    return `Only ${outstanding} are still out on this loan.`;
  }

  if (damaged > returned) {
    return 'The damaged ones are part of what came back, so there cannot be more damaged than returned.';
  }

  const closes = returned + lost === outstanding;

  await db.$transaction(async (tx) => {
    await tx.bookings.update({
      where: { id: id(bookingId) },
      data: {
        returned_quantity: booking.returned_quantity + returned,
        damaged_quantity: booking.damaged_quantity + damaged,
        lost_quantity: booking.lost_quantity + lost,
        return_notes:
          [loaded.returnNotes, notes].filter((part) => (part ?? '').trim() !== '').join('\n').trim() ||
          null,
        returned_at: closes ? now() : null,
        returned_by: closes ? id(by.id) : null,
        updated_at: now(),
      },
    });

    if (resource && (damaged > 0 || lost > 0)) {
      await tx.resources.update({
        where: { id: id(resource.id) },
        data: {
          damaged_quantity: resource.damaged_quantity + damaged,
          total_quantity: Math.max(0, (resource.total_quantity ?? 0) - lost),
          updated_at: now(),
        },
      });
    }
  });

  const parts = [
    returned - damaged > 0 ? `${returned - damaged} in good condition` : null,
    damaged > 0 ? `${damaged} damaged (now out of service)` : null,
    lost > 0 ? `${lost} missing (written off)` : null,
  ].filter((part): part is string => part !== null);

  const after = {
    ...loaded,
    booking: {
      ...booking,
      returned_quantity: booking.returned_quantity + returned,
      lost_quantity: booking.lost_quantity + lost,
      returned_at: closes ? now() : null,
    },
  };
  const left = unitsOutstanding(after.booking);

  await log(
    after,
    `Returned ${resource?.name ?? 'the items'}: ${parts.join(', ')}.${left > 0 ? ` ${left} still out.` : ''}`,
  );

  if (closes) {
    await notifyCitizen(after, OUTCOME.Returned);
  }

  return null;
}

/** A facility's reservation ends now — the venue is free again. */
export async function finish(bookingId: number, by: Actor): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  const { booking } = loaded;

  if (!isApproved(booking) || isClosed(booking) || isFuture(booking.starts_at)) {
    return 'Only a reservation that is under way can be marked done.';
  }

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { returned_at: now(), returned_by: id(by.id), updated_at: now() },
  });

  await log(loaded, `Marked ${loaded.resource?.name ?? 'the reservation'} done.`);

  return null;
}

/** Cancel a booking. Items already handed over must be returned instead. */
export async function cancel(bookingId: number, notify = true): Promise<Refusal> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return 'That booking no longer exists.';
  }

  if (isOnLoan(loaded.booking)) {
    return 'These items have been handed over. Record their return instead of cancelling.';
  }

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { status: BOOKING_STATUS.Cancelled, updated_at: now() },
  });

  await log(loaded, `Cancelled booking for ${loaded.resource?.name ?? 'the resource'}.`);

  if (notify) {
    await notifyCitizen(loaded, OUTCOME.Cancelled);
  }

  return null;
}

/**
 * Email an overdue borrower. Returns false when there is no way to — no email
 * on file, or citizen emails switched off — so the page can say to call instead.
 */
export async function remind(bookingId: number): Promise<boolean> {
  const loaded = await loadBooking(bookingId);

  if (!loaded) {
    return false;
  }

  const sent = await notifyCitizen(loaded, OUTCOME.Overdue);
  const overdue = daysOverdue(loaded.booking);
  const name = loaded.resource?.name ?? 'the items';

  await db.bookings.update({
    where: { id: id(bookingId) },
    data: { overdue_reminded_at: now(), updated_at: now() },
  });

  await log(
    loaded,
    sent
      ? `Reminded the borrower that ${name} is ${overdue} day(s) overdue.`
      : `${name} is ${overdue} day(s) overdue — no email to remind the borrower.`,
  );

  return sent;
}

/**
 * Email the citizen about their booking, honouring the same gate as status
 * emails: the global switch, the bookings switch, an email on file, and the
 * request's own `notify_citizen` flag.
 */
export async function notifyCitizen(
  loaded: LoadedBooking,
  outcome: BookingOutcome,
): Promise<boolean> {
  if (!tracking.notifyCitizen.enabled || !tracking.notifyCitizen.bookings) {
    return false;
  }

  const { document } = loaded;

  if (!document || !document.citizen_email || !document.notify_citizen) {
    return false;
  }

  queueMail(
    bookingUpdatedMail({
      to: document.citizen_email,
      outcome,
      booking: loaded.booking,
      resource: loaded.resource,
      document,
    }),
  );

  await logOnDocument(document.id, `Emailed BookingUpdated (${outcome}) to citizen`);

  return true;
}

/**
 * On the request (its history) and on the resource (the item's own history on
 * the Resources page).
 */
async function log(loaded: LoadedBooking, line: string): Promise<void> {
  if (loaded.document) {
    await logOnDocument(loaded.document.id, line);
  }

  if (loaded.resource) {
    const trackingNumber = loaded.document?.tracking_number;

    await logOnResource(loaded.resource.id, trackingNumber ? `${line} (${trackingNumber})` : line);
  }
}
