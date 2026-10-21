import { describe, expect, it } from 'vitest';
import {
  MANILA_OFFSET_MINUTES,
  addDays,
  addHours,
  diffInCalendarDays,
  diffInDays,
  diffInHours,
  diffInHoursExact,
  endOfDay,
  forHumans,
  formatNaive,
  isSameDay,
  now,
  parseNaive,
  startOfDay,
  toDateString,
  toDateTimeString,
} from '@/lib/time';

/**
 * The database stores naive Manila wall-clock timestamps, and Prisma hands them
 * back as Dates whose UTC fields hold that reading. These tests pin that
 * contract down: if formatting ever starts using local getters, an SLA
 * calculation silently shifts by eight hours and nothing else would catch it.
 */
describe('the naive Manila encoding', () => {
  it('reads a stored timestamp back at the same wall-clock time', () => {
    // What MySQL holds for "2026-10-21 14:30:00" in the Manila-converted schema.
    const stored = parseNaive('2026-10-21 14:30:00')!;

    expect(formatNaive(stored, 'Y-m-d H:i')).toBe('2026-10-21 14:30');
    expect(formatNaive(stored, 'M j, Y g:i A')).toBe('Oct 21, 2026 2:30 PM');
  });

  it('puts `now()` eight hours ahead of UTC', () => {
    expect(MANILA_OFFSET_MINUTES).toBe(480);

    const drift = now().getTime() - Date.now() - MANILA_OFFSET_MINUTES * 60_000;

    // Allow a few ms for the two clock reads.
    expect(Math.abs(drift)).toBeLessThan(1_000);
  });

  it('is immune to the server running in another timezone', () => {
    // UTC getters are used throughout, so a host in UTC-5 or UTC+13 formats the
    // same stored reading identically.
    const stored = parseNaive('2026-01-01 00:30:00')!;

    expect(formatNaive(stored, 'Y-m-d H:i')).toBe('2026-01-01 00:30');
    expect(toDateString(stored)).toBe('2026-01-01');
    expect(toDateTimeString(stored)).toBe('2026-01-01 00:30:00');
  });
});

describe('parseNaive', () => {
  it('accepts the shapes forms and query strings produce', () => {
    expect(toDateTimeString(parseNaive('2026-10-21')!)).toBe('2026-10-21 00:00:00');
    expect(toDateTimeString(parseNaive('2026-10-21 14:30')!)).toBe('2026-10-21 14:30:00');
    expect(toDateTimeString(parseNaive('2026-10-21T14:30:45')!)).toBe('2026-10-21 14:30:45');
    expect(toDateTimeString(parseNaive('  2026-10-21  ')!)).toBe('2026-10-21 00:00:00');
  });

  it('returns null rather than an Invalid Date, so callers can fall back', () => {
    expect(parseNaive(null)).toBeNull();
    expect(parseNaive(undefined)).toBeNull();
    expect(parseNaive('')).toBeNull();
    expect(parseNaive('not a date')).toBeNull();
    expect(parseNaive('21/10/2026')).toBeNull();
  });
});

describe('day boundaries', () => {
  const stamp = parseNaive('2026-10-21 14:30:45')!;

  it('snaps to the start and end of the Manila day', () => {
    expect(toDateTimeString(startOfDay(stamp))).toBe('2026-10-21 00:00:00');
    expect(formatNaive(endOfDay(stamp), 'Y-m-d H:i:s')).toBe('2026-10-21 23:59:59');
  });

  it('does not mutate its argument', () => {
    startOfDay(stamp);
    endOfDay(stamp);

    expect(toDateTimeString(stamp)).toBe('2026-10-21 14:30:45');
  });

  it('compares calendar days, ignoring the time', () => {
    expect(isSameDay(stamp, parseNaive('2026-10-21 00:00')!)).toBe(true);
    expect(isSameDay(stamp, parseNaive('2026-10-22 00:00')!)).toBe(false);
  });
});

describe('arithmetic', () => {
  it('adds days and hours', () => {
    const stamp = parseNaive('2026-10-21 14:30')!;

    expect(toDateTimeString(addDays(stamp, 4))).toBe('2026-10-25 14:30:00');
    expect(toDateTimeString(addDays(stamp, -1))).toBe('2026-10-20 14:30:00');
    expect(toDateTimeString(addHours(stamp, 10))).toBe('2026-10-22 00:30:00');
  });

  it('truncates hour and day diffs the way Carbon did', () => {
    const from = parseNaive('2026-10-21 08:00')!;

    // 25 hours → 25, and 25.5 → 25, not 26. The SLA sweep compares `> sla`, so
    // rounding up would breach an hour early.
    expect(diffInHours(from, parseNaive('2026-10-22 09:00')!)).toBe(25);
    expect(diffInHours(from, parseNaive('2026-10-22 09:30')!)).toBe(25);
    expect(diffInHoursExact(from, parseNaive('2026-10-22 09:30')!)).toBe(25.5);

    expect(diffInDays(from, parseNaive('2026-10-24 07:00')!)).toBe(2);
    expect(diffInDays(from, parseNaive('2026-10-24 09:00')!)).toBe(3);
  });

  it('counts calendar days between dates regardless of time of day', () => {
    // What `followClaimDate` shifts a booking by: 23:00 to 01:00 next day is
    // one calendar day, not zero.
    expect(diffInCalendarDays(parseNaive('2026-10-21 23:00')!, parseNaive('2026-10-22 01:00')!)).toBe(1);
    expect(diffInCalendarDays(parseNaive('2026-10-21 00:00')!, parseNaive('2026-10-21 23:59')!)).toBe(0);
    expect(diffInCalendarDays(parseNaive('2026-10-25 08:00')!, parseNaive('2026-10-21 08:00')!)).toBe(-4);
  });
});

describe('formatNaive', () => {
  const stamp = parseNaive('2026-01-05 09:07:03')!;

  it('supports the date() tokens the views used', () => {
    expect(formatNaive(stamp, 'D, M j, Y · g:i A')).toBe('Mon, Jan 5, 2026 · 9:07 AM');
    expect(formatNaive(stamp, 'l jS')).toBe('Monday 5S'); // S is not supported, passes through
    expect(formatNaive(stamp, 'Y-m-d')).toBe('2026-01-05');
    expect(formatNaive(stamp, 'F Y')).toBe('January 2026');
    expect(formatNaive(stamp, 'H:i:s')).toBe('09:07:03');
    expect(formatNaive(stamp, 'g:ia')).toBe('9:07am');
  });

  it('renders midnight and noon as 12, not 0', () => {
    expect(formatNaive(parseNaive('2026-01-05 00:30')!, 'g:i A')).toBe('12:30 AM');
    expect(formatNaive(parseNaive('2026-01-05 12:30')!, 'g:i A')).toBe('12:30 PM');
  });

  it('escapes literal characters with a backslash, as PHP date() does', () => {
    expect(formatNaive(stamp, '\\Y=Y')).toBe('Y=2026');
  });

  it('defaults to the common short date', () => {
    expect(formatNaive(stamp)).toBe('Jan 5, 2026');
  });
});

describe('forHumans', () => {
  const reference = parseNaive('2026-10-21 12:00')!;

  it('reads recent activity as "just now"', () => {
    expect(forHumans(parseNaive('2026-10-21 11:59:30')!, reference)).toBe('just now');
  });

  it('counts back in the largest sensible unit', () => {
    expect(forHumans(parseNaive('2026-10-21 11:45')!, reference)).toBe('15 minutes ago');
    expect(forHumans(parseNaive('2026-10-21 09:00')!, reference)).toBe('3 hours ago');
    expect(forHumans(parseNaive('2026-10-20 12:00')!, reference)).toBe('1 day ago');
    expect(forHumans(parseNaive('2026-10-18 12:00')!, reference)).toBe('3 days ago');
  });

  it('reads future times as "in …"', () => {
    expect(forHumans(parseNaive('2026-10-21 14:00')!, reference)).toBe('in 2 hours');
    expect(forHumans(parseNaive('2026-10-23 12:00')!, reference)).toBe('in 2 days');
  });
});
