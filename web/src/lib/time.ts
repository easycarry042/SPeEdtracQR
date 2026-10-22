/**
 * Manila-naive time.
 *
 * Laravel ran with `app.timezone = Asia/Manila` and the
 * `convert_system_timestamps_to_manila_time` migration rewrote every stored
 * timestamp to Manila wall-clock. MySQL DATETIME/TIMESTAMP(0) carry no zone, so
 * the bytes in the database are a *naive* Manila clock reading.
 *
 * Prisma hands those bytes back as a `Date` whose **UTC fields** hold that naive
 * reading (`2026-10-21 14:30` → `Date` with `getUTCHours() === 14`). Rather than
 * fight that, we adopt it as the contract: a `Date` in this codebase is always a
 * Manila wall-clock reading stored in UTC fields. Read and write through the
 * helpers here and comparisons, diffs and formatting all line up with what
 * Carbon produced — mix in `new Date()` or `getHours()` and everything shifts by
 * eight hours.
 */

export const MANILA_OFFSET_MINUTES = 8 * 60;

/** Current Manila wall-clock time, in the naive encoding described above. */
export function now(): Date {
  return new Date(Date.now() + MANILA_OFFSET_MINUTES * 60_000);
}

/** Today's Manila date at 00:00. */
export function today(): Date {
  return startOfDay(now());
}

export function startOfDay(date: Date): Date {
  const d = new Date(date.getTime());
  d.setUTCHours(0, 0, 0, 0);
  return d;
}

export function endOfDay(date: Date): Date {
  const d = new Date(date.getTime());
  d.setUTCHours(23, 59, 59, 999);
  return d;
}

export function addDays(date: Date, days: number): Date {
  return new Date(date.getTime() + days * 86_400_000);
}

export function addHours(date: Date, hours: number): Date {
  return new Date(date.getTime() + hours * 3_600_000);
}

export function subDays(date: Date, days: number): Date {
  return addDays(date, -days);
}

/** Whole hours between two readings (positive when `to` is later). */
export function diffInHours(from: Date, to: Date = now()): number {
  return Math.floor((to.getTime() - from.getTime()) / 3_600_000);
}

/** Fractional hours — the SLA bars need the remainder, not a floor. */
export function diffInHoursExact(from: Date, to: Date = now()): number {
  return (to.getTime() - from.getTime()) / 3_600_000;
}

export function diffInMinutes(from: Date, to: Date = now()): number {
  return Math.floor((to.getTime() - from.getTime()) / 60_000);
}

/** Whole days between two readings, matching Carbon's truncating `diffInDays`. */
export function diffInDays(from: Date, to: Date = now()): number {
  return Math.floor((to.getTime() - from.getTime()) / 86_400_000);
}

/** Calendar days between two dates, ignoring the time of day. */
export function diffInCalendarDays(from: Date, to: Date): number {
  return Math.round((startOfDay(to).getTime() - startOfDay(from).getTime()) / 86_400_000);
}

export function isPast(date: Date): boolean {
  return date.getTime() < now().getTime();
}

export function isFuture(date: Date): boolean {
  return date.getTime() > now().getTime();
}

export function isSameDay(a: Date, b: Date): boolean {
  return startOfDay(a).getTime() === startOfDay(b).getTime();
}

export function minDate(a: Date, b: Date): Date {
  return a.getTime() <= b.getTime() ? a : b;
}

export function maxDate(a: Date, b: Date): Date {
  return a.getTime() >= b.getTime() ? a : b;
}

/** `Y-m-d` — the shape MySQL DATE columns and `<input type="date">` both use. */
export function toDateString(date: Date): string {
  return date.toISOString().slice(0, 10);
}

/** `Y-m-d H:i:s`, for logging and raw-SQL bounds. */
export function toDateTimeString(date: Date): string {
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

/**
 * Parse a `Y-m-d` (or `Y-m-d H:i[:s]`) string from a form or query parameter
 * into the naive encoding. Returns null for anything unparseable so callers can
 * fall back rather than silently work from an Invalid Date.
 */
export function parseNaive(value: string | null | undefined): Date | null {
  if (!value) {
    return null;
  }

  const match = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(value.trim());

  if (!match) {
    return null;
  }

  const [, y, mo, d, h = '0', mi = '0', s = '0'] = match;
  const date = new Date(
    Date.UTC(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s)),
  );

  return Number.isNaN(date.getTime()) ? null : date;
}

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/**
 * Formats the naive encoding with UTC getters, so output matches the Manila
 * reading regardless of the server's own timezone. Supports the handful of
 * Carbon/PHP `date()` tokens the Blade views actually used.
 */
export function formatNaive(date: Date, pattern = 'M j, Y'): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  const hours12 = date.getUTCHours() % 12 || 12;

  const tokens: Record<string, string> = {
    Y: String(date.getUTCFullYear()),
    y: pad(date.getUTCFullYear() % 100),
    F: MONTHS[date.getUTCMonth()],
    M: MONTHS[date.getUTCMonth()].slice(0, 3),
    m: pad(date.getUTCMonth() + 1),
    n: String(date.getUTCMonth() + 1),
    d: pad(date.getUTCDate()),
    j: String(date.getUTCDate()),
    l: DAYS[date.getUTCDay()],
    D: DAYS[date.getUTCDay()].slice(0, 3),
    H: pad(date.getUTCHours()),
    G: String(date.getUTCHours()),
    h: pad(hours12),
    g: String(hours12),
    i: pad(date.getUTCMinutes()),
    s: pad(date.getUTCSeconds()),
    A: date.getUTCHours() < 12 ? 'AM' : 'PM',
    a: date.getUTCHours() < 12 ? 'am' : 'pm',
  };

  // Escape with a backslash, as PHP's date() does.
  return pattern.replace(/\\?(.)/g, (whole, char: string) =>
    whole.startsWith('\\') ? char : (tokens[char] ?? char),
  );
}

/** "3 days ago" / "in 2 hours" — Carbon's `diffForHumans`, trimmed to what the UI uses. */
export function forHumans(date: Date, reference: Date = now()): string {
  const seconds = Math.round((reference.getTime() - date.getTime()) / 1000);
  const past = seconds >= 0;
  const abs = Math.abs(seconds);

  const units: Array<[number, string]> = [
    [60, 'second'],
    [3600, 'minute'],
    [86400, 'hour'],
    [604800, 'day'],
    [2592000, 'week'],
    [31536000, 'month'],
    [Infinity, 'year'],
  ];

  const divisors: Record<string, number> = {
    second: 1, minute: 60, hour: 3600, day: 86400, week: 604800, month: 2592000, year: 31536000,
  };

  const unit = units.find(([limit]) => abs < limit)?.[1] ?? 'year';
  const value = Math.max(1, Math.floor(abs / divisors[unit]));
  const plural = value === 1 ? unit : `${unit}s`;

  if (abs < 60 && past) {
    return 'just now';
  }

  return past ? `${value} ${plural} ago` : `in ${value} ${plural}`;
}
