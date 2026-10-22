import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';

/**
 * A reservation of a resource for a window, tied to a request (document).
 *
 * For equipment it is also a loan, which runs: pending → approved (units
 * promised, `quantity` set by staff) → handed over (`released_at`) → returned
 * (`returned_at`, once every unit is back or written off). A loan is only closed
 * when someone records the return; past its return date it is overdue and keeps
 * its units out of stock until then.
 */
export const BOOKING_STATUS = {
  Pending: 'pending',
  Approved: 'approved',
  Cancelled: 'cancelled',
} as const;

/** Where a loan stands at the counter (`lendingState()`). */
export const LENDING = {
  Pending: 'pending',
  Reserved: 'reserved',
  Missed: 'missed',
  OnLoan: 'on_loan',
  Overdue: 'overdue',
  Returned: 'returned',
  Cancelled: 'cancelled',
} as const;

export type LendingState = (typeof LENDING)[keyof typeof LENDING];

/** One of a thing — the gymnasium. Two approved reservations may not overlap. */
export const KIND_FACILITY = 'facility';

/** A countable pile — chairs, tents. Overlapping borrowings share the stock. */
export const KIND_EQUIPMENT = 'equipment';

export type BookingCore = {
  id: number;
  document_id: number;
  resource_id: number;
  quantity: number | null;
  starts_at: Date;
  ends_at: Date;
  status: string;
  released_at: Date | null;
  returned_at: Date | null;
  returned_quantity: number;
  damaged_quantity: number;
  lost_quantity: number;
  /** The citizen's ask, from `documents.quantity` — only `units()` falls back to it. */
  requestedQuantity?: number | null;
};

export type ResourceCore = {
  id: number;
  name: string;
  kind: string;
  total_quantity: number | null;
  damaged_quantity: number;
  capacity: number | null;
};

export function isEquipment(resource: { kind: string }): boolean {
  return resource.kind === KIND_EQUIPMENT;
}

export function isFacility(resource: { kind: string }): boolean {
  return !isEquipment(resource);
}

export function isCancelled(booking: { status: string }): boolean {
  return booking.status === BOOKING_STATUS.Cancelled;
}

export function isApproved(booking: { status: string }): boolean {
  return booking.status === BOOKING_STATUS.Approved;
}

/**
 * Units this booking is for: what staff granted, or the citizen's ask while it
 * is still pending (and for bookings made before staff set one).
 */
export function units(booking: Pick<BookingCore, 'quantity' | 'requestedQuantity'>): number {
  return booking.quantity ?? booking.requestedQuantity ?? 0;
}

/** The citizen's ask, as filed on the request form. */
export function unitsRequested(booking: Pick<BookingCore, 'requestedQuantity'>): number {
  return booking.requestedQuantity ?? 0;
}

/** Units handed over and not yet back (or written off). */
export function unitsOutstanding(booking: BookingCore): number {
  return Math.max(0, units(booking) - booking.returned_quantity - booking.lost_quantity);
}

export function isReleased(booking: { released_at: Date | null }): boolean {
  return booking.released_at !== null;
}

/** Closed: everything is back or written off (a facility: marked done). */
export function isClosed(booking: { returned_at: Date | null }): boolean {
  return booking.returned_at !== null;
}

export function isOnLoan(booking: BookingCore): boolean {
  return isApproved(booking) && isReleased(booking) && !isClosed(booking);
}

/** Out past its return date and nobody has marked it back. */
export function isBookingOverdue(booking: BookingCore, at: Date = now()): boolean {
  return isOnLoan(booking) && booking.ends_at.getTime() < at.getTime();
}

/** Whole days past the return date — 1 on the first day after it. */
export function daysOverdue(booking: BookingCore, at: Date = now()): number {
  if (!isBookingOverdue(booking, at)) {
    return 0;
  }

  const hours = (at.getTime() - booking.ends_at.getTime()) / 3_600_000;

  return Math.max(1, Math.ceil(hours / 24));
}

export function isAwaitingPickup(booking: BookingCore): boolean {
  return isApproved(booking) && !isReleased(booking) && !isClosed(booking);
}

export function lendingState(booking: BookingCore, at: Date = now()): LendingState {
  if (isCancelled(booking)) {
    return LENDING.Cancelled;
  }

  if (!isApproved(booking)) {
    return LENDING.Pending;
  }

  if (isClosed(booking)) {
    return LENDING.Returned;
  }

  if (isBookingOverdue(booking, at)) {
    return LENDING.Overdue;
  }

  if (isReleased(booking)) {
    return LENDING.OnLoan;
  }

  // Promised, never collected, and its window is over: it holds nothing any
  // more, but someone should cancel or hand it over.
  if (booking.ends_at.getTime() <= at.getTime()) {
    return LENDING.Missed;
  }

  return LENDING.Reserved;
}

/** Staff wording for `lendingState()`. */
export function lendingLabel(
  booking: BookingCore,
  resource: { kind: string } | null,
  at: Date = now(),
): string {
  const equipment = resource !== null && isEquipment(resource);

  switch (lendingState(booking, at)) {
    case LENDING.Pending:
      return 'Waiting for approval';
    case LENDING.Reserved:
      return equipment ? 'Approved — ready for pickup' : 'Approved';
    case LENDING.Missed:
      return 'Not picked up';
    case LENDING.OnLoan:
      return 'Handed over';
    case LENDING.Overdue:
      return 'Overdue';
    case LENDING.Returned:
      return equipment ? 'Returned' : 'Done';
    default:
      return 'Cancelled';
  }
}

/**
 * Units this booking keeps from being lent to anyone else during a window.
 *
 * Promised but not collected: its own dates. Handed over: from the hand-over
 * until the return date — and once that date passes, until someone records the
 * return, however long that takes. Overdue chairs are not in the stockroom, so
 * they cannot be promised to the next borrower.
 */
export function unitsHeldDuring(
  booking: BookingCore,
  starts: Date,
  ends: Date,
  at: Date = now(),
): number {
  if (!isApproved(booking) || isClosed(booking)) {
    return 0;
  }

  if (isReleased(booking) && booking.released_at) {
    const from =
      booking.released_at.getTime() < booking.starts_at.getTime()
        ? booking.released_at
        : booking.starts_at;
    const openEnded = booking.ends_at.getTime() <= at.getTime();

    return from.getTime() < ends.getTime() &&
      (openEnded || booking.ends_at.getTime() > starts.getTime())
      ? unitsOutstanding(booking)
      : 0;
  }

  return booking.starts_at.getTime() < ends.getTime() &&
    booking.ends_at.getTime() > starts.getTime()
    ? units(booking)
    : 0;
}

/* ── Inventory ──────────────────────────────────────────────────────────────── */

const BOOKING_SELECT = {
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
  document: { select: { quantity: true } },
} as const;

type BookingWithDocument = {
  id: bigint;
  document_id: bigint;
  resource_id: bigint;
  quantity: number | null;
  starts_at: Date;
  ends_at: Date;
  status: string;
  released_at: Date | null;
  returned_at: Date | null;
  returned_quantity: number;
  damaged_quantity: number;
  lost_quantity: number;
  document: { quantity: number | null } | null;
};

export function hydrateBooking(row: BookingWithDocument): BookingCore {
  return {
    id: toId(row.id),
    document_id: toId(row.document_id),
    resource_id: toId(row.resource_id),
    quantity: row.quantity,
    starts_at: row.starts_at,
    ends_at: row.ends_at,
    status: row.status,
    released_at: row.released_at,
    returned_at: row.returned_at,
    returned_quantity: row.returned_quantity,
    damaged_quantity: row.damaged_quantity,
    lost_quantity: row.lost_quantity,
    requestedQuantity: row.document?.quantity ?? null,
  };
}

/**
 * Approved and not yet closed — still promised, out, or overdue. A deleted
 * request holds nothing, so its document must exist and not be soft-deleted.
 */
const openBookings = {
  status: BOOKING_STATUS.Approved,
  returned_at: null,
  document: { is: { deleted_at: null } },
} as const;

/**
 * Units that can be lent at all: what the office owns, less what is broken. The
 * ceiling no booking can exceed no matter when it falls.
 */
export function serviceableUnits(resource: ResourceCore): number {
  return Math.max(0, (resource.total_quantity ?? 0) - resource.damaged_quantity);
}

/**
 * Units already promised to, or out with, approved borrowings during the window.
 * Only approved bookings count — a pending request has not been granted and must
 * not reserve stock against itself — and an overdue loan counts against every
 * window until someone records its return.
 */
export async function unitsCommitted(
  resourceId: number,
  starts: Date,
  ends: Date,
  ignoreBookingId: number | null = null,
): Promise<number> {
  const at = now();

  const rows = await db.bookings.findMany({
    where: {
      resource_id: id(resourceId),
      ...openBookings,
      OR: [
        { released_at: { not: null } },
        { starts_at: { lt: ends }, ends_at: { gt: starts } },
      ],
      ...(ignoreBookingId === null ? {} : { id: { not: id(ignoreBookingId) } }),
    },
    select: BOOKING_SELECT,
  });

  return rows.reduce(
    (total, row) => total + unitsHeldDuring(hydrateBooking(row), starts, ends, at),
    0,
  );
}

/** Units free to lend across the window. */
export async function unitsAvailable(
  resource: ResourceCore,
  starts: Date,
  ends: Date,
  ignoreBookingId: number | null = null,
): Promise<number> {
  const committed = await unitsCommitted(resource.id, starts, ends, ignoreBookingId);

  return Math.max(0, serviceableUnits(resource) - committed);
}

/** Units handed over and not back yet, overdue ones included. */
export async function unitsOnLoan(resourceId: number): Promise<number> {
  const rows = await db.bookings.findMany({
    where: { resource_id: id(resourceId), ...openBookings, released_at: { not: null } },
    select: BOOKING_SELECT,
  });

  return rows.reduce((total, row) => total + unitsOutstanding(hydrateBooking(row)), 0);
}

/**
 * Units promised to approved borrowings still to be collected, whatever their
 * date. A missed pickup (window over, never collected) holds nothing.
 */
export async function unitsReserved(resourceId: number): Promise<number> {
  const rows = await db.bookings.findMany({
    where: {
      resource_id: id(resourceId),
      ...openBookings,
      released_at: null,
      ends_at: { gt: now() },
    },
    select: BOOKING_SELECT,
  });

  return rows.reduce((total, row) => total + units(hydrateBooking(row)), 0);
}

/** In the stockroom right now: serviceable, less what is out on loan. */
export async function unitsOnHand(resource: ResourceCore): Promise<number> {
  return Math.max(0, serviceableUnits(resource) - (await unitsOnLoan(resource.id)));
}

/**
 * Non-cancelled bookings on this resource that overlap the given window.
 * Overlap: `existing.starts_at < ends AND existing.ends_at > starts`.
 */
export async function conflicts(
  resourceId: number,
  starts: Date,
  ends: Date,
  ignoreBookingId: number | null = null,
): Promise<BookingCore[]> {
  const rows = await db.bookings.findMany({
    where: {
      resource_id: id(resourceId),
      status: { not: BOOKING_STATUS.Cancelled },
      starts_at: { lt: ends },
      ends_at: { gt: starts },
      // A reservation marked done early no longer holds the rest of its slot.
      OR: [{ returned_at: null }, { returned_at: { gt: starts } }],
      ...(ignoreBookingId === null ? {} : { id: { not: id(ignoreBookingId) } }),
    },
    select: BOOKING_SELECT,
  });

  return rows.map(hydrateBooking);
}

/**
 * Approved bookings overlapping the window — the set that blocks approving or
 * rescheduling another booking (two approved bookings can't overlap).
 */
export async function approvedConflicts(
  resourceId: number,
  starts: Date,
  ends: Date,
  ignoreBookingId: number | null = null,
): Promise<BookingCore[]> {
  const rows = await db.bookings.findMany({
    where: {
      resource_id: id(resourceId),
      status: BOOKING_STATUS.Approved,
      starts_at: { lt: ends },
      ends_at: { gt: starts },
      OR: [{ returned_at: null }, { returned_at: { gt: starts } }],
      ...(ignoreBookingId === null ? {} : { id: { not: id(ignoreBookingId) } }),
    },
    select: BOOKING_SELECT,
  });

  return rows.map(hydrateBooking);
}

/**
 * The approved booking holding a facility at this moment, if any — what the
 * counter needs to answer "is the gym free right now?".
 */
export async function bookingAt(resourceId: number, at: Date): Promise<BookingCore | null> {
  const row = await db.bookings.findFirst({
    where: {
      resource_id: id(resourceId),
      status: BOOKING_STATUS.Approved,
      // Marked done early: the venue is free again.
      returned_at: null,
      starts_at: { lte: at },
      ends_at: { gt: at },
    },
    select: BOOKING_SELECT,
  });

  return row ? hydrateBooking(row) : null;
}

/** The next approved booking starting after the given moment. */
export async function nextBookingAfter(
  resourceId: number,
  at: Date,
): Promise<BookingCore | null> {
  const row = await db.bookings.findFirst({
    where: {
      resource_id: id(resourceId),
      status: BOOKING_STATUS.Approved,
      returned_at: null,
      starts_at: { gt: at },
    },
    orderBy: { starts_at: 'asc' },
    select: BOOKING_SELECT,
  });

  return row ? hydrateBooking(row) : null;
}
