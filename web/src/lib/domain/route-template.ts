/**
 * Route templates: the endorsement chains prefilled onto an internal request.
 *
 * Port of `App\Models\RouteTemplate` and `RouteTemplateStep`. A step may carry
 * an amount condition, which is how one template branches — two steps share a
 * step number with opposite conditions, and only the one matching the request's
 * peso figure ends up in the chain (RA 12009: small value procurement below the
 * bidding threshold, public bidding at or above it).
 *
 * Kept apart from `admin-catalog` so the setup form can label the conditions in
 * the browser without pulling the database client in with them.
 */

/** Step applies only when the request carries a peso amount. */
export const CONDITION_HAS_AMOUNT = 'has_amount';

/** Below the bidding threshold — the Small Value Procurement path. */
export const CONDITION_BELOW_THRESHOLD = 'below_threshold';

/** At or above the threshold — the public bidding path. */
export const CONDITION_AT_LEAST_THRESHOLD = 'at_least_threshold';

export const STEP_CONDITIONS = [
  CONDITION_HAS_AMOUNT,
  CONDITION_BELOW_THRESHOLD,
  CONDITION_AT_LEAST_THRESHOLD,
] as const;

export type StepCondition = (typeof STEP_CONDITIONS)[number];

/** Condition value → the words the setup form puts on it. */
export const STEP_CONDITION_LABELS: Record<StepCondition, string> = {
  [CONDITION_HAS_AMOUNT]: 'Only if an amount is attached',
  [CONDITION_BELOW_THRESHOLD]: 'Amount below ₱2M (Small Value Procurement)',
  [CONDITION_AT_LEAST_THRESHOLD]: 'Amount ₱2M and above (Public Bidding)',
};

/** RA 12009's competitive bidding line, in pesos. */
export const BIDDING_THRESHOLD = 2_000_000;

/** Whether a hop belongs in the chain for a request of this amount. */
export function appliesToAmount(condition: string | null, amount: number | null): boolean {
  switch (condition) {
    case CONDITION_HAS_AMOUNT:
      return amount !== null;
    case CONDITION_BELOW_THRESHOLD:
      return amount !== null && amount < BIDDING_THRESHOLD;
    case CONDITION_AT_LEAST_THRESHOLD:
      return amount !== null && amount >= BIDDING_THRESHOLD;
    default:
      // No condition, or one this build does not know: an unconditional hop.
      // Dropping an unrecognised condition would silently shorten a chain.
      return true;
  }
}

/**
 * The concrete chain for an amount: every unconditional step, plus the
 * amount-dependent ones that apply, in step order.
 */
export function stepsForAmount<T extends { stepOrder: number; condition: string | null }>(
  steps: T[],
  amount: number | null,
): T[] {
  return steps
    .filter((step) => appliesToAmount(step.condition, amount))
    .sort((left, right) => left.stepOrder - right.stepOrder);
}
