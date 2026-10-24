import { beforeEach, describe, expect, it, vi } from 'vitest';
import { addDays, now, startOfDay } from '@/lib/time';

/**
 * Port of `BookingCalendarTest`: overlapping bookings on the same resource are
 * flagged (both of them), a different resource at the same time is not, and
 * the calendar's controls need `manage bookings`.
 */
const findMany = vi.fn();
const currentActor = vi.fn();
const approve = vi.fn();
const reschedule = vi.fn();
const cancel = vi.fn();

vi.mock('@/lib/db', () => ({ db: { bookings: { findMany } }, toId: Number, id: BigInt }));
vi.mock('@/lib/auth/actor', () => ({ currentActor }));
vi.mock('@/lib/domain/lending-desk', () => ({ approve, reschedule, cancel }));
vi.mock('next/cache', () => ({ revalidatePath: vi.fn() }));

const { calendar } = await import('@/lib/domain/booking-calendar');
const actions = await import('@/app/actions/bookings');

const at = (days: number, hour: number): Date => new Date(addDays(startOfDay(now()), days).getTime() + hour * 3_600_000);

function booking(id: number, resourceId: number, starts: Date, ends: Date) {
  return {
    id: BigInt(id),
    resource_id: BigInt(resourceId),
    starts_at: starts,
    ends_at: ends,
    status: 'pending',
    resource: { name: `Court ${resourceId}`, kind: 'facility' },
    document: { tracking_number: `SPD-20261023-ABC23${id}`, citizen_name: 'Juan', quantity: null },
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  approve.mockResolvedValue(null);
  reschedule.mockResolvedValue(null);
  cancel.mockResolvedValue(null);
});

describe('calendar', () => {
  it('flags both bookings of an overlap on one resource, and nothing across resources', async () => {
    findMany.mockResolvedValue([
      booking(1, 1, at(2, 9), at(2, 12)),
      booking(2, 1, at(2, 11), at(2, 13)),
      booking(3, 2, at(2, 9), at(2, 12)),
    ]);

    const data = await calendar();
    const day = Object.values(data.byDate)[0];

    expect(day.map((b) => [b.id, b.conflict])).toEqual([
      [1, true],
      [2, true],
      [3, false],
    ]);
    expect(Object.values(data.meta)[0]).toEqual({ count: 3, conflict: true });
  });

  it('opens on the first booked day when today has none, and lists only later days as upcoming', async () => {
    findMany.mockResolvedValue([booking(1, 1, at(3, 9), at(3, 10))]);

    const data = await calendar();

    expect(data.defaultDate).toBe(Object.keys(data.byDate)[0]);
    expect(data.upcoming).toHaveLength(1);
  });

  it('reads upcoming, non-cancelled bookings only', async () => {
    findMany.mockResolvedValue([]);

    await calendar();

    expect(findMany.mock.calls[0][0].where).toMatchObject({ status: { not: 'cancelled' } });
  });
});

describe('actions', () => {
  const can = (permission: string) => ({ can: (p: string) => p === permission });

  it('refuses anyone without manage bookings', async () => {
    currentActor.mockResolvedValue(can('manage inventory'));

    expect(await actions.cancelBooking(1)).toEqual({ ok: false, message: 'This action is unauthorized.' });
    expect(cancel).not.toHaveBeenCalled();
  });

  it('approves with a whole number of units, or refuses a fraction', async () => {
    currentActor.mockResolvedValue(can('manage bookings'));

    expect((await actions.approveBooking(1, 2.5)).ok).toBe(false);
    expect(await actions.approveBooking(1, 3)).toEqual({ ok: true, message: 'Booking approved.' });
    expect(approve).toHaveBeenCalledWith(1, 3);
  });

  it('refuses a reschedule that ends before it starts', async () => {
    currentActor.mockResolvedValue(can('manage bookings'));

    const result = await actions.rescheduleBooking(1, '2026-11-02T10:00', '2026-11-02T09:00');

    expect(result).toEqual({ ok: false, message: 'The end time must be after the start time.' });
    expect(reschedule).not.toHaveBeenCalled();
  });

  it('passes a lending refusal through', async () => {
    currentActor.mockResolvedValue(can('manage bookings'));
    reschedule.mockResolvedValue('Only 3 chairs are free on those dates.');

    expect(await actions.rescheduleBooking(1, '2026-11-02T09:00', '2026-11-03T09:00')).toEqual({
      ok: false,
      message: 'Only 3 chairs are free on those dates.',
    });
  });
});
