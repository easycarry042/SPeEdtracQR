import { db } from '@/lib/db';
import { isEquipment } from '@/lib/domain/lending';
import { formatNaive, isSameDay, now, startOfDay, toDateString } from '@/lib/time';

/**
 * Port of `BookingController@index` — upcoming reservations for the month
 * calendar, with every booking that overlaps another on the same resource
 * flagged, so staff can resolve the clash.
 */
export type CalendarBooking = {
  id: number;
  day: string;
  resource: string;
  isEquipment: boolean;
  status: string;
  window: string;
  /** For the reschedule fields: `YYYY-MM-DDTHH:mm`. */
  startsLocal: string;
  endsLocal: string;
  upcomingLabel: string;
  upcomingWindow: string;
  requester: string;
  trackingNumber: string | null;
  quantity: number | null;
  conflict: boolean;
};

export type CalendarData = {
  byDate: Record<string, CalendarBooking[]>;
  meta: Record<string, { count: number; conflict: boolean }>;
  defaultDate: string;
  today: string;
  upcoming: CalendarBooking[];
};

const local = (date: Date): string => formatNaive(date, 'Y-m-d\\TH:i');

export async function calendar(): Promise<CalendarData> {
  const rows = await db.bookings.findMany({
    where: { status: { not: 'cancelled' }, ends_at: { gte: startOfDay(now()) } },
    orderBy: { starts_at: 'asc' },
    select: {
      id: true,
      resource_id: true,
      starts_at: true,
      ends_at: true,
      status: true,
      resource: { select: { name: true, kind: true } },
      document: { select: { tracking_number: true, citizen_name: true, quantity: true } },
    },
  });

  // Every booking overlapping another non-cancelled one on the same resource
  // (either can be the one to reschedule or cancel).
  const conflicts = new Set<bigint>();

  for (const a of rows) {
    for (const b of rows) {
      if (a.id !== b.id && a.resource_id === b.resource_id && a.starts_at < b.ends_at && a.ends_at > b.starts_at) {
        conflicts.add(a.id);
      }
    }
  }

  const bookings: CalendarBooking[] = rows.map((row) => ({
    id: Number(row.id),
    day: toDateString(row.starts_at),
    resource: row.resource?.name ?? 'Resource',
    isEquipment: row.resource ? isEquipment(row.resource) : false,
    status: row.status,
    window: `${formatNaive(row.starts_at, 'g:i A')} – ${
      isSameDay(row.starts_at, row.ends_at) ? formatNaive(row.ends_at, 'g:i A') : formatNaive(row.ends_at, 'M j, g:i A')
    }`,
    startsLocal: local(row.starts_at),
    endsLocal: local(row.ends_at),
    upcomingLabel: formatNaive(row.starts_at, 'M j'),
    upcomingWindow: `${formatNaive(row.starts_at, 'g:i A')} – ${formatNaive(row.ends_at, 'g:i A')}`,
    requester: row.document?.citizen_name || 'Requester',
    trackingNumber: row.document?.tracking_number ?? null,
    quantity: row.document?.quantity ?? null,
    conflict: conflicts.has(row.id),
  }));

  const byDate: CalendarData['byDate'] = {};

  for (const booking of bookings) {
    (byDate[booking.day] ??= []).push(booking);
  }

  const meta = Object.fromEntries(
    Object.entries(byDate).map(([day, list]) => [day, { count: list.length, conflict: list.some((b) => b.conflict) }]),
  );

  const today = toDateString(now());
  const endOfToday = new Date(startOfDay(now()).getTime() + 86_400_000);

  return {
    byDate,
    meta,
    // Today, or the first day that has a booking.
    defaultDate: byDate[today] ? today : (Object.keys(byDate)[0] ?? today),
    today,
    // "What's next?" — after today, whatever the calendar is showing.
    upcoming: bookings.filter((_, index) => rows[index].starts_at >= endOfToday).slice(0, 12),
  };
}
