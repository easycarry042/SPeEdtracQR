/**
 * Elapsed time the way a person reads it: "45m", "5h 18m", "20d 2h".
 *
 * The command center used to print raw units — "0d" for a request finished in an
 * hour (days rounded down to nothing), "+482.4h" for one twenty days late — so
 * the smallest and the largest numbers on the page were the hardest to read.
 */

/** A span given in hours. Negative spans read as their size. */
export function fromHours(hours: number | null | undefined): string {
  if (hours === null || hours === undefined || Number.isNaN(hours)) {
    return '—';
  }

  const minutes = Math.round(Math.abs(hours) * 60);

  if (minutes < 60) {
    return `${minutes}m`;
  }

  if (minutes < 24 * 60) {
    const h = Math.trunc(minutes / 60);
    const m = minutes % 60;

    return m === 0 ? `${h}h` : `${h}h ${m}m`;
  }

  const totalHours = Math.round(minutes / 60);
  const d = Math.trunc(totalHours / 24);
  const h = totalHours % 24;

  return h === 0 ? `${d}d` : `${d}d ${h}h`;
}

/** A span given in days (the unit the analytics store averages in). */
export function fromDays(days: number | null | undefined): string {
  return days === null || days === undefined ? '—' : fromHours(days * 24);
}
