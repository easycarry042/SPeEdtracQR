/**
 * Port of the office-level rules on `App\Models\Department`.
 *
 * Internal requests are measured against the office's OWN turnaround, not the
 * citizen stage clocks — a request is overdue when the office now holding it has
 * had it longer than this.
 */
export const DEFAULT_INTERNAL_STEP_HOURS = 72;

/**
 * An office's internal turnaround in hours.
 *
 * `0` and `null` both mean "not configured" and fall back to the default —
 * matching PHP's `?:`, where a zero would otherwise read as "due immediately"
 * and mark every request at that office overdue.
 */
export function internalStepHours(
  department: { internal_step_hours?: number | null } | null | undefined,
): number {
  const configured = department?.internal_step_hours;

  return configured ? configured : DEFAULT_INTERNAL_STEP_HOURS;
}
