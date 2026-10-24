import { describe, expect, it } from 'vitest';
import {
  BOOKING_STATUS,
  type BookingCore,
  KIND_EQUIPMENT,
  KIND_FACILITY,
  LENDING,
  daysOverdue,
  isAwaitingPickup,
  isBookingOverdue,
  isClosed,
  isEquipment,
  isFacility,
  isOnLoan,
  lendingLabel,
  lendingState,
  serviceableUnits,
  units,
  unitsHeldDuring,
  unitsOutstanding,
} from '@/lib/domain/lending';
import { addDays, parseNaive } from '@/lib/time';

const AT = parseNaive('2026-10-21 10:00')!;

function booking(overrides: Partial<BookingCore> = {}): BookingCore {
  return {
    id: 1,
    document_id: 1,
    resource_id: 1,
    quantity: 10,
    starts_at: parseNaive('2026-10-20 08:00')!,
    ends_at: parseNaive('2026-10-25 17:00')!,
    status: BOOKING_STATUS.Approved,
    released_at: null,
    returned_at: null,
    returned_quantity: 0,
    damaged_quantity: 0,
    lost_quantity: 0,
    requestedQuantity: 12,
    ...overrides,
  };
}

const equipment = { kind: KIND_EQUIPMENT };
const facility = { kind: KIND_FACILITY };

describe('units', () => {
  it('prefers what staff granted over what the citizen asked for', () => {
    expect(units(booking({ quantity: 10, requestedQuantity: 12 }))).toBe(10);
  });

  it('falls back to the citizen ask while still pending, and for legacy rows', () => {
    expect(units(booking({ quantity: null, requestedQuantity: 12 }))).toBe(12);
    expect(units(booking({ quantity: null, requestedQuantity: null }))).toBe(0);
  });

  it('takes returned and written-off units out of what is outstanding', () => {
    expect(unitsOutstanding(booking({ returned_quantity: 3, lost_quantity: 1 }))).toBe(6);
    // Never negative, however the counts were recorded.
    expect(unitsOutstanding(booking({ returned_quantity: 20 }))).toBe(0);
  });
});

describe('lendingState', () => {
  it('reads a cancelled booking as cancelled whatever else is set', () => {
    const row = booking({ status: BOOKING_STATUS.Cancelled, released_at: AT });

    expect(lendingState(row, AT)).toBe(LENDING.Cancelled);
  });

  it('reads an unapproved booking as pending', () => {
    expect(lendingState(booking({ status: BOOKING_STATUS.Pending }), AT)).toBe(LENDING.Pending);
  });

  it('reads an approved, uncollected booking inside its window as reserved', () => {
    expect(lendingState(booking(), AT)).toBe(LENDING.Reserved);
    expect(isAwaitingPickup(booking())).toBe(true);
  });

  it('reads a handed-over booking as on loan', () => {
    const row = booking({ released_at: parseNaive('2026-10-20 09:00') });

    expect(lendingState(row, AT)).toBe(LENDING.OnLoan);
    expect(isOnLoan(row)).toBe(true);
  });

  it('reads a handed-over booking past its return date as overdue', () => {
    const row = booking({
      released_at: parseNaive('2026-10-20 09:00'),
      ends_at: parseNaive('2026-10-20 17:00')!,
    });

    expect(lendingState(row, AT)).toBe(LENDING.Overdue);
    expect(isBookingOverdue(row, AT)).toBe(true);
  });

  it('reads a closed booking as returned, even if it was overdue on the way', () => {
    const row = booking({
      released_at: parseNaive('2026-10-20 09:00'),
      ends_at: parseNaive('2026-10-20 17:00')!,
      returned_at: AT,
    });

    expect(lendingState(row, AT)).toBe(LENDING.Returned);
    expect(isClosed(row)).toBe(true);
    // A closed loan is no longer out, so it cannot be overdue.
    expect(isBookingOverdue(row, AT)).toBe(false);
  });

  it('reads a promised booking whose window has passed uncollected as missed', () => {
    const row = booking({ ends_at: parseNaive('2026-10-20 17:00')! });

    expect(lendingState(row, AT)).toBe(LENDING.Missed);
  });
});

describe('daysOverdue', () => {
  it('is zero while the loan is not overdue', () => {
    expect(daysOverdue(booking({ released_at: AT }), AT)).toBe(0);
  });

  it('counts 1 on the first day past the return date', () => {
    const row = booking({
      released_at: parseNaive('2026-10-18 09:00'),
      ends_at: parseNaive('2026-10-21 08:00')!,
    });

    // Two hours late still reads as a day — the borrower is late today.
    expect(daysOverdue(row, AT)).toBe(1);
  });

  it('rounds up to whole days', () => {
    const row = booking({
      released_at: parseNaive('2026-10-10 09:00'),
      ends_at: parseNaive('2026-10-18 10:00')!,
    });

    expect(daysOverdue(row, AT)).toBe(3);
  });
});

describe('lendingLabel', () => {
  it('distinguishes equipment pickup wording from a venue booking', () => {
    expect(lendingLabel(booking(), equipment, AT)).toBe('Approved — ready for pickup');
    expect(lendingLabel(booking(), facility, AT)).toBe('Approved');
  });

  it('calls a closed loan returned and a closed venue booking done', () => {
    const row = booking({ released_at: parseNaive('2026-10-20 09:00'), returned_at: AT });

    expect(lendingLabel(row, equipment, AT)).toBe('Returned');
    expect(lendingLabel(row, facility, AT)).toBe('Done');
  });
});

describe('unitsHeldDuring', () => {
  const windowStart = parseNaive('2026-10-23 08:00')!;
  const windowEnd = parseNaive('2026-10-24 17:00')!;

  it('holds nothing when the booking is unapproved or already closed', () => {
    expect(unitsHeldDuring(booking({ status: BOOKING_STATUS.Pending }), windowStart, windowEnd, AT)).toBe(0);
    expect(unitsHeldDuring(booking({ returned_at: AT }), windowStart, windowEnd, AT)).toBe(0);
  });

  it('holds its granted units across an overlapping window while uncollected', () => {
    expect(unitsHeldDuring(booking(), windowStart, windowEnd, AT)).toBe(10);
  });

  it('holds nothing across a window it does not overlap', () => {
    const row = booking({
      starts_at: parseNaive('2026-11-01 08:00')!,
      ends_at: parseNaive('2026-11-02 17:00')!,
    });

    expect(unitsHeldDuring(row, windowStart, windowEnd, AT)).toBe(0);
  });

  it('holds only what is still out once some units have come back', () => {
    const row = booking({
      released_at: parseNaive('2026-10-20 09:00'),
      returned_quantity: 4,
    });

    expect(unitsHeldDuring(row, windowStart, windowEnd, AT)).toBe(6);
  });

  it('keeps an overdue loan holding its units against every later window', () => {
    // Nothing rejoins stock on the return date: overdue chairs are not in the
    // stockroom, so they cannot be promised to the next borrower.
    const row = booking({
      released_at: parseNaive('2026-10-15 09:00'),
      ends_at: parseNaive('2026-10-18 17:00')!,
    });

    const farLater = addDays(windowStart, 60);

    expect(unitsHeldDuring(row, windowStart, windowEnd, AT)).toBe(10);
    expect(unitsHeldDuring(row, farLater, addDays(farLater, 1), AT)).toBe(10);
  });
});

describe('resource stock', () => {
  it('takes out-of-service units off what can be lent at all', () => {
    expect(serviceableUnits({ id: 1, name: 'Chairs', kind: KIND_EQUIPMENT, total_quantity: 50, damaged_quantity: 8, capacity: null })).toBe(42);
  });

  it('never reports negative stock', () => {
    expect(serviceableUnits({ id: 1, name: 'Chairs', kind: KIND_EQUIPMENT, total_quantity: 5, damaged_quantity: 9, capacity: null })).toBe(0);
    expect(serviceableUnits({ id: 1, name: 'Gym', kind: KIND_FACILITY, total_quantity: null, damaged_quantity: 0, capacity: 500 })).toBe(0);
  });

  it('tells equipment from facilities', () => {
    expect(isEquipment(equipment)).toBe(true);
    expect(isFacility(equipment)).toBe(false);
    // Anything that is not equipment is a facility, including unknown kinds.
    expect(isFacility({ kind: 'venue' })).toBe(true);
  });
});
