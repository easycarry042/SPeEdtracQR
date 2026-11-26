import { beforeEach, describe, expect, it, vi } from 'vitest';
import { parseNaive } from '@/lib/time';

const findResource = vi.fn();
const findBookings = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    resources: { findFirst: findResource },
    bookings: { findMany: findBookings },
  },
  id: BigInt,
}));

const { expandBookingDateRange, bookedFacilityDates } = await import(
  '@/lib/domain/facility-availability'
);

beforeEach(() => {
  vi.clearAllMocks();
});

describe('expandBookingDateRange', () => {
  it('expands a single-day booking into one date', () => {
    const starts = parseNaive('2026-11-15 09:00:00')!;
    const ends = parseNaive('2026-11-15 17:00:00')!;

    expect(expandBookingDateRange(starts, ends, '2026-11-01', '2026-11-30')).toEqual([
      '2026-11-15',
    ]);
  });

  it('expands multi-day bookings and clamps them to the requested grid window', () => {
    const starts = parseNaive('2026-10-30 08:00:00')!;
    const ends = parseNaive('2026-11-03 17:00:00')!;

    expect(expandBookingDateRange(starts, ends, '2026-11-01', '2026-11-30')).toEqual([
      '2026-11-01',
      '2026-11-02',
      '2026-11-03',
    ]);
  });

  it('excludes the ending calendar day when ends_at is midnight 00:00:00 of a later day', () => {
    const starts = parseNaive('2026-11-10 08:00:00')!;
    const ends = parseNaive('2026-11-12 00:00:00')!;

    expect(expandBookingDateRange(starts, ends, '2026-11-01', '2026-11-30')).toEqual([
      '2026-11-10',
      '2026-11-11',
    ]);
  });
});

describe('bookedFacilityDates', () => {
  it('returns empty array for non-existent, inactive, or non-facility resources', async () => {
    findResource.mockResolvedValue(null);

    expect(await bookedFacilityDates(99, '2026-11')).toEqual([]);
    expect(findBookings).not.toHaveBeenCalled();
  });

  it('returns sorted deduplicated dates for active pending/approved bookings and excludes cancelled/returned', async () => {
    findResource.mockResolvedValue({ id: BigInt(1) });
    findBookings.mockResolvedValue([
      {
        starts_at: parseNaive('2026-11-18 09:00:00')!,
        ends_at: parseNaive('2026-11-18 12:00:00')!,
      },
      {
        starts_at: parseNaive('2026-11-18 14:00:00')!,
        ends_at: parseNaive('2026-11-19 17:00:00')!,
      },
      {
        starts_at: parseNaive('2026-11-05 08:00:00')!,
        ends_at: parseNaive('2026-11-05 17:00:00')!,
      },
    ]);

    const dates = await bookedFacilityDates(1, '2026-11');

    expect(dates).toEqual(['2026-11-05', '2026-11-18', '2026-11-19']);
    expect(findResource.mock.calls[0][0].where).toMatchObject({
      kind: 'facility',
      is_active: true,
    });
    expect(findBookings.mock.calls[0][0].where).toMatchObject({
      status: { not: 'cancelled' },
      returned_at: null,
      document: { is: { deleted_at: null } },
    });
  });

  it('returns empty array for malformed month inputs', async () => {
    expect(await bookedFacilityDates(1, '2026-13')).toEqual([]);
    expect(findResource).not.toHaveBeenCalled();
  });
});
