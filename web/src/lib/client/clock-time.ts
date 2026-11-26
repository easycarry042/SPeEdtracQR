export type Meridiem = 'AM' | 'PM';

export interface ParsedTime12 {
  /** 1..12 */
  hour12: number;
  /** 0..59 */
  minute: number;
  meridiem: Meridiem;
}

const TIME_24_RE = /^([01]\d|2[0-3]):([0-5]\d)$/;

/** Parses `HH:MM` (24-hour) into 12-hour clock components, or null if invalid. */
export function parseTime24(value: string | null | undefined): ParsedTime12 | null {
  const trimmed = (value ?? '').trim();
  const match = TIME_24_RE.exec(trimmed);
  if (!match) {
    return null;
  }

  const hour24 = Number(match[1]);
  const minute = Number(match[2]);
  const meridiem: Meridiem = hour24 >= 12 ? 'PM' : 'AM';
  const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;

  return { hour12, minute, meridiem };
}

/** Converts 12-hour clock components back to canonical `HH:MM` (24-hour). */
export function toTime24(hour12: number, minute: number, meridiem: Meridiem): string {
  const clampedHour12 = ((Math.round(hour12) - 1) % 12 + 12) % 12 + 1;
  const clampedMinute = ((Math.round(minute) % 60) + 60) % 60;

  let hour24 = clampedHour12 % 12;
  if (meridiem === 'PM') {
    hour24 += 12;
  }

  return `${String(hour24).padStart(2, '0')}:${String(clampedMinute).padStart(2, '0')}`;
}

/** Formats `HH:MM` (24-hour) as human-readable 12-hour time, e.g. `"9:00 AM"`. */
export function formatTime12(value: string | null | undefined): string {
  const parsed = parseTime24(value);
  if (!parsed) {
    return '';
  }

  return `${parsed.hour12}:${String(parsed.minute).padStart(2, '0')} ${parsed.meridiem}`;
}

/** Converts `HH:MM` to total minutes since midnight (0..1439), or null if invalid. */
export function timeToMinutes(value: string | null | undefined): number | null {
  const trimmed = (value ?? '').trim();
  const match = TIME_24_RE.exec(trimmed);
  if (!match) {
    return null;
  }

  return Number(match[1]) * 60 + Number(match[2]);
}

/** Returns `HH:MM` advanced by `deltaMinutes`, clamped to `23:59`. */
export function addMinutesClamped(value: string, deltaMinutes: number): string {
  const total = timeToMinutes(value);
  if (total === null) {
    return value;
  }

  const next = Math.max(0, Math.min(23 * 60 + 59, total + deltaMinutes));
  const h = Math.floor(next / 60);
  const m = next % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

/**
 * Given a pointer vector `(dx, dy)` relative to the clock dial center
 * (positive x = right, positive y = down), returns clockwise degrees from 12 o'clock `[0, 360)`.
 */
export function pointerToClockDegrees(dx: number, dy: number): number {
  const rad = Math.atan2(dy, dx);
  const degFrom3 = (rad * 180) / Math.PI;
  return (degFrom3 + 90 + 360) % 360;
}

/** Maps clockwise degrees `[0, 360)` from 12 o'clock to hour `1..12`. */
export function degreesToHour12(degrees: number): number {
  const step = Math.round(degrees / 30) % 12;
  return step === 0 ? 12 : step;
}

/** Maps clockwise degrees `[0, 360)` from 12 o'clock to minute `0..59`. */
export function degreesToMinute(degrees: number): number {
  return Math.round(degrees / 6) % 60;
}
