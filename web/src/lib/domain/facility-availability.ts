import { db, id } from '@/lib/db';
import { BOOKING_STATUS, KIND_FACILITY } from '@/lib/domain/lending';
import { addDays, parseNaive, toDateString } from '@/lib/time';

const MONTH_RE = /^(\d{4})-(0[1-9]|1[0-2])$/;

/**
 * Expands a single naive-Manila booking span into individual `YYYY-MM-DD`
 * calendar dates, clamped to `[windowStartIso, windowEndIso]`.
 */
export function expandBookingDateRange(
  startsAt: Date,
  endsAt: Date,
  windowStartIso: string,
  windowEndIso: string,
): string[] {
  const startIso = toDateString(startsAt);
  let endIso = toDateString(endsAt);

  // A booking ending exactly at midnight (00:00:00) on a later day does not
  // occupy that ending calendar day.
  if (
    endsAt.getTime() > startsAt.getTime() &&
    endsAt.getUTCHours() === 0 &&
    endsAt.getUTCMinutes() === 0 &&
    endsAt.getUTCSeconds() === 0 &&
    endIso > startIso
  ) {
    endIso = toDateString(addDays(endsAt, -1));
  }

  if (endIso < startIso) {
    return [];
  }

  const effectiveStart = startIso > windowStartIso ? startIso : windowStartIso;
  const effectiveEnd = endIso < windowEndIso ? endIso : windowEndIso;

  if (effectiveEnd < effectiveStart) {
    return [];
  }

  let cursor = parseNaive(`${effectiveStart} 00:00:00`);
  const endDate = parseNaive(`${effectiveEnd} 00:00:00`);

  if (!cursor || !endDate) {
    return [];
  }

  const dates: string[] = [];

  while (cursor.getTime() <= endDate.getTime()) {
    dates.push(toDateString(cursor));
    cursor = addDays(cursor, 1);
  }

  return dates;
}

/**
 * Returns sorted unique `YYYY-MM-DD` dates across the 6-week visible grid of
 * `monthIso` (`YYYY-MM`) that already have an active (non-cancelled,
 * non-returned, non-deleted) booking for the given facility. Zero PII is
 * queried or returned.
 */
export async function bookedFacilityDates(
  resourceId: number,
  monthIso: string,
): Promise<string[]> {
  const match = MONTH_RE.exec(monthIso.trim());

  if (!match || !Number.isInteger(resourceId) || resourceId <= 0) {
    return [];
  }

  const resource = await db.resources.findFirst({
    where: {
      id: id(resourceId),
      kind: KIND_FACILITY,
      is_active: true,
    },
    select: { id: true },
  });

  if (!resource) {
    return [];
  }

  const year = Number(match[1]);
  const month = Number(match[2]);
  const lastDayNum = new Date(Date.UTC(year, month, 0)).getUTCDate();

  const firstOfMonth = parseNaive(`${match[1]}-${match[2]}-01 00:00:00`);
  const lastOfMonth = parseNaive(
    `${match[1]}-${match[2]}-${String(lastDayNum).padStart(2, '0')} 23:59:59`,
  );

  if (!firstOfMonth || !lastOfMonth) {
    return [];
  }

  const windowStart = addDays(firstOfMonth, -7);
  const windowEnd = addDays(lastOfMonth, 7);
  const windowStartIso = toDateString(windowStart);
  const windowEndIso = toDateString(windowEnd);

  const rows = await db.bookings.findMany({
    where: {
      resource_id: id(resourceId),
      status: { not: BOOKING_STATUS.Cancelled },
      returned_at: null,
      document: { is: { deleted_at: null } },
      starts_at: { lte: windowEnd },
      ends_at: { gte: windowStart },
    },
    select: {
      starts_at: true,
      ends_at: true,
    },
  });

  const booked = new Set<string>();

  for (const row of rows) {
    for (const day of expandBookingDateRange(row.starts_at, row.ends_at, windowStartIso, windowEndIso)) {
      booked.add(day);
    }
  }

  return Array.from(booked).sort();
}
