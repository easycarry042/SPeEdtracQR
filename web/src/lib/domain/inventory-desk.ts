import type { Prisma } from '@prisma/client';
import { db, id, toId } from '@/lib/db';
import { formatNaive, isSameDay, now, subDays } from '@/lib/time';
import { SUBJECT } from '@/lib/activity';
import {
  BOOKING_STATUS,
  KIND_EQUIPMENT,
  LENDING,
  type LendingState,
  approvedConflicts,
  bookingAt,
  daysOverdue,
  hydrateBooking,
  isEquipment,
  lendingState,
  nextBookingAfter,
  serviceableUnits,
  units,
  unitsAvailable,
  unitsOnLoan,
  unitsOutstanding,
  unitsRequested,
  unitsReserved,
} from '@/lib/domain/lending';
import { loadBooking, requestNotReady } from '@/lib/domain/lending-desk';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * The Resources page's read model: the lending desk's queues, the stock
 * figures, and each item's own history.
 *
 * One page for every role — staff and supervisors run the desk, super admins
 * get the same page plus the catalog controls.
 */
export type LoanRow = {
  bookingId: number;
  state: LendingState;
  item: string;
  isEquipment: boolean;
  borrower: string;
  contact: string | null;
  email: string | null;
  tracking: string | null;
  handler: string | null;
  requested: number;
  units: number;
  outstanding: number;
  returned: number;
  damaged: number;
  lost: number;
  notes: string | null;
  window: string;
  pickupOn: string;
  pickupToday: boolean;
  dueOn: string;
  dueToday: boolean;
  daysOverdue: number;
  releasedOn: string | null;
  releasedBy: string | null;
  returnedOn: string | null;
  returnedBy: string | null;
  remindedOn: string | null;
  /** What the approval check would allow for these dates. */
  free: number | null;
  serviceable: number | null;
  /** Approval waits for the request's own order: assigned, then accepted. */
  notReady: string | null;
  clash: boolean;
};

export type Desk = {
  overdue: LoanRow[];
  approve: LoanRow[];
  pickup: LoanRow[];
  onLoan: LoanRow[];
  returned: LoanRow[];
};

/**
 * `satisfies` rather than `as const`: Prisma needs the select to be a known
 * `bookingsSelect` to infer the row type, and without it every query here
 * comes back as a bare scalar row with no relations attached.
 */
const BOOKING_INCLUDE = {
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
  overdue_reminded_at: true,
  resource: {
    select: { id: true, name: true, kind: true, total_quantity: true, damaged_quantity: true, capacity: true },
  },
  document: {
    select: {
      quantity: true,
      tracking_number: true,
      citizen_name: true,
      citizen_contact: true,
      citizen_email: true,
      deleted_at: true,
      assignee: { select: { name: true } },
    },
  },
  releaser: { select: { name: true } },
  receiver: { select: { name: true } },
} satisfies Prisma.bookingsSelect;

type BookingSource = {
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
  return_notes: string | null;
  overdue_reminded_at: Date | null;
  resource: {
    id: bigint;
    name: string;
    kind: string;
    total_quantity: number | null;
    damaged_quantity: number;
    capacity: number | null;
  } | null;
  document: {
    quantity: number | null;
    tracking_number: string;
    citizen_name: string | null;
    citizen_contact: string | null;
    citizen_email: string | null;
    deleted_at: Date | null;
    assignee: { name: string } | null;
  } | null;
  releaser: { name: string } | null;
  receiver: { name: string } | null;
};

async function loanRow(source: BookingSource, at: Date): Promise<LoanRow> {
  const booking = hydrateBooking(source);
  const resource = source.resource ? { ...source.resource, id: toId(source.resource.id) } : null;
  const document = source.document;
  const equipment = resource !== null && isEquipment(resource);
  const sameDay = isSameDay(booking.starts_at, booking.ends_at);
  const pending = booking.status === BOOKING_STATUS.Pending;

  const [free, clash, notReady] = await Promise.all([
    pending && equipment && resource
      ? unitsAvailable(resource, booking.starts_at, booking.ends_at, booking.id)
      : Promise.resolve(null),
    pending && resource && !equipment
      ? approvedConflicts(resource.id, booking.starts_at, booking.ends_at, booking.id).then(
          (rows) => rows.length > 0,
        )
      : Promise.resolve(false),
    pending
      ? loadBooking(booking.id).then((loaded) => (loaded ? requestNotReady(loaded) : null))
      : Promise.resolve(null),
  ]);

  return {
    bookingId: booking.id,
    state: lendingState(booking, at),
    item: resource?.name ?? 'Resource',
    isEquipment: equipment,
    borrower: document?.citizen_name || 'Requester',
    contact: document?.citizen_contact ?? null,
    email: document?.citizen_email ?? null,
    tracking: document?.tracking_number ?? null,
    handler: document?.assignee?.name ?? null,
    requested: unitsRequested(booking),
    units: units(booking),
    outstanding: unitsOutstanding(booking),
    returned: booking.returned_quantity,
    damaged: booking.damaged_quantity,
    lost: booking.lost_quantity,
    notes: source.return_notes,
    window: sameDay
      ? `${formatNaive(booking.starts_at, 'M j, g:i A')} – ${formatNaive(booking.ends_at, 'g:i A')}`
      : `${formatNaive(booking.starts_at, 'M j')} – ${formatNaive(booking.ends_at, 'M j, Y')}`,
    pickupOn: formatNaive(booking.starts_at, 'D, M j'),
    pickupToday: isSameDay(booking.starts_at, at),
    dueOn: formatNaive(booking.ends_at, 'D, M j'),
    dueToday: isSameDay(booking.ends_at, at),
    daysOverdue: daysOverdue(booking, at),
    releasedOn: booking.released_at ? formatNaive(booking.released_at, 'M j, g:i A') : null,
    releasedBy: source.releaser?.name ?? null,
    returnedOn: booking.returned_at ? formatNaive(booking.returned_at, 'M j, g:i A') : null,
    returnedBy: source.receiver?.name ?? null,
    remindedOn: source.overdue_reminded_at
      ? formatNaive(source.overdue_reminded_at, 'M j, g:i A')
      : null,
    free,
    serviceable: equipment && resource ? serviceableUnits(resource) : null,
    notReady,
    clash,
  };
}

export async function desk(at: Date = now()): Promise<Desk> {
  const [toApprove, open, returned] = await Promise.all([
    db.bookings.findMany({
      where: {
        status: BOOKING_STATUS.Pending,
        ends_at: { gt: at },
        document: { is: { deleted_at: null, status: { not: DocumentStatus.Denied } } },
      },
      orderBy: { starts_at: 'asc' },
      select: BOOKING_INCLUDE,
    }),

    db.bookings.findMany({
      where: {
        status: BOOKING_STATUS.Approved,
        returned_at: null,
        document: { is: { deleted_at: null } },
        resource: { is: { kind: KIND_EQUIPMENT } },
      },
      orderBy: { ends_at: 'asc' },
      select: BOOKING_INCLUDE,
    }),

    db.bookings.findMany({
      where: {
        status: BOOKING_STATUS.Approved,
        returned_at: { gte: subDays(at, 30) },
        released_at: { not: null },
        document: { is: { deleted_at: null } },
        resource: { is: { kind: KIND_EQUIPMENT } },
      },
      orderBy: { returned_at: 'desc' },
      take: 50,
      select: BOOKING_INCLUDE,
    }),
  ]);

  const [approveRows, openRows, returnedRows] = await Promise.all([
    Promise.all(toApprove.map((row) => loanRow(row, at))),
    Promise.all(open.map((row) => loanRow(row, at))),
    Promise.all(returned.map((row) => loanRow(row, at))),
  ]);

  const inState = (...states: LendingState[]) => (row: LoanRow) => states.includes(row.state);

  return {
    overdue: openRows.filter(inState(LENDING.Overdue)),
    approve: approveRows,
    // Promised but not collected, and the ones whose window already passed.
    pickup: openRows.filter(inState(LENDING.Reserved, LENDING.Missed)),
    onLoan: openRows.filter(inState(LENDING.OnLoan)),
    returned: returnedRows,
  };
}

export type FacilityRow = {
  id: number;
  name: string;
  location: string | null;
  capacity: number | null;
  isActive: boolean;
  /** 'free' | 'busy' — what the counter needs to answer "is the gym free now?" */
  tone: 'free' | 'busy';
  busyWith: string | null;
  busyUntil: string | null;
  nextAt: string | null;
};

export type EquipmentRow = {
  id: number;
  name: string;
  location: string | null;
  isActive: boolean;
  owned: number;
  damaged: number;
  onLoan: number;
  reserved: number;
  available: number;
};

export async function stock(at: Date = now()): Promise<{
  facilities: FacilityRow[];
  equipment: EquipmentRow[];
}> {
  const resources = await db.resources.findMany({
    orderBy: [{ sort_order: 'asc' }, { name: 'asc' }],
    select: {
      id: true,
      name: true,
      kind: true,
      total_quantity: true,
      damaged_quantity: true,
      capacity: true,
      location: true,
      is_active: true,
    },
  });

  const facilities: FacilityRow[] = [];
  const equipment: EquipmentRow[] = [];

  for (const row of resources) {
    const resource = { ...row, id: toId(row.id) };

    if (isEquipment(resource)) {
      const [out, promised] = await Promise.all([
        unitsOnLoan(resource.id),
        unitsReserved(resource.id),
      ]);

      const serviceable = serviceableUnits(resource);

      equipment.push({
        id: resource.id,
        name: resource.name,
        location: resource.location,
        isActive: resource.is_active,
        owned: resource.total_quantity ?? 0,
        damaged: resource.damaged_quantity,
        onLoan: out,
        reserved: promised,
        // owned − out of service − on loan − promised.
        available: Math.max(0, serviceable - out - promised),
      });

      continue;
    }

    const [current, next] = await Promise.all([
      bookingAt(resource.id, at),
      nextBookingAfter(resource.id, at),
    ]);

    let busyWith: string | null = null;

    if (current) {
      const document = await db.documents.findUnique({
        where: { id: id(current.document_id) },
        select: { citizen_name: true, tracking_number: true },
      });

      busyWith = document?.citizen_name || document?.tracking_number || 'a reservation';
    }

    facilities.push({
      id: resource.id,
      name: resource.name,
      location: resource.location,
      capacity: resource.capacity,
      isActive: resource.is_active,
      tone: current ? 'busy' : 'free',
      busyWith,
      busyUntil: current ? formatNaive(current.ends_at, 'g:i A') : null,
      nextAt: next ? formatNaive(next.starts_at, 'D, M j · g:i A') : null,
    });
  }

  return { facilities, equipment };
}

export type Tiles = {
  facilitiesFree: number;
  facilitiesTotal: number;
  unitsAvailable: number;
  unitsReserved: number;
  unitsOnLoan: number;
  unitsDamaged: number;
  overdue: number;
  toApprove: number;
  pickupsToday: number;
};

export function tiles(
  facilities: FacilityRow[],
  equipment: EquipmentRow[],
  deskQueues: Desk,
): Tiles {
  return {
    facilitiesFree: facilities.filter((row) => row.tone === 'free').length,
    facilitiesTotal: facilities.length,
    unitsAvailable: equipment.reduce((sum, row) => sum + row.available, 0),
    unitsReserved: equipment.reduce((sum, row) => sum + row.reserved, 0),
    unitsOnLoan: equipment.reduce((sum, row) => sum + row.onLoan, 0),
    unitsDamaged: equipment.reduce((sum, row) => sum + row.damaged, 0),
    overdue: deskQueues.overdue.length,
    // Only what can actually be approved now — the badge and this tile must
    // not count rows the desk will refuse.
    toApprove: deskQueues.approve.filter((row) => row.notReady === null).length,
    pickupsToday: deskQueues.pickup.filter((row) => row.pickupToday).length,
  };
}

/** Each item's recent changes, newest first. One query for the whole page. */
export async function history(
  resourceIds: number[],
): Promise<Map<number, Array<{ line: string; who: string | null; when: string }>>> {
  const grouped = new Map<number, Array<{ line: string; who: string | null; when: string }>>();

  if (resourceIds.length === 0) {
    return grouped;
  }

  const rows = await db.activity_log.findMany({
    where: { subject_type: SUBJECT.Resource, subject_id: { in: resourceIds.map(id) } },
    orderBy: { created_at: 'desc' },
    take: 400,
    select: { subject_id: true, description: true, created_at: true, causer_id: true },
  });

  const causerIds = [...new Set(rows.map((row) => row.causer_id).filter((v): v is bigint => v !== null))];
  const causers =
    causerIds.length === 0
      ? []
      : await db.users.findMany({ where: { id: { in: causerIds } }, select: { id: true, name: true } });
  const names = new Map(causers.map((user) => [toId(user.id), user.name]));

  for (const row of rows) {
    if (row.subject_id === null) {
      continue;
    }

    const key = toId(row.subject_id);
    const existing = grouped.get(key) ?? [];

    // Eight per item is what the panel shows.
    if (existing.length >= 8) {
      continue;
    }

    existing.push({
      line: row.description,
      who: row.causer_id === null ? null : (names.get(toId(row.causer_id)) ?? null),
      when: row.created_at ? formatNaive(row.created_at, 'M j, g:i A') : '',
    });

    grouped.set(key, existing);
  }

  return grouped;
}
