import { describe, expect, it } from 'vitest';
import {
  BIDDING_THRESHOLD,
  CONDITION_AT_LEAST_THRESHOLD,
  CONDITION_BELOW_THRESHOLD,
  CONDITION_HAS_AMOUNT,
  appliesToAmount,
  stepsForAmount,
} from '@/lib/domain/route-template';

/**
 * Port of `RouteTemplateTest::steps_resolve_by_amount_around_the_bidding_threshold`
 * and the `appliesToAmount` half of `RouteTemplateStep`.
 *
 * The condition values are the contract between this form and every chain
 * already in the database — the seeded procurement template stores
 * `has_amount`, `below_threshold` and `at_least_threshold`. The first port of
 * the catalog service invented three different strings, which would have
 * refused the seeded templates on edit and written conditions nothing reads.
 */
const chain = [
  { stepOrder: 1, action: 'Approve request', condition: null },
  { stepOrder: 2, action: 'Certify fund availability', condition: CONDITION_HAS_AMOUNT },
  { stepOrder: 3, action: 'Small Value Procurement', condition: CONDITION_BELOW_THRESHOLD },
  { stepOrder: 3, action: 'Public Bidding', condition: CONDITION_AT_LEAST_THRESHOLD },
];

const actions = (amount: number | null): string[] =>
  stepsForAmount(chain, amount).map((step) => step.action);

describe('appliesToAmount', () => {
  it('keeps an unconditional hop whatever the amount', () => {
    expect(appliesToAmount(null, null)).toBe(true);
    expect(appliesToAmount(null, 5_000_000)).toBe(true);
  });

  it('holds the budget hop until an amount is attached', () => {
    expect(appliesToAmount(CONDITION_HAS_AMOUNT, null)).toBe(false);
    expect(appliesToAmount(CONDITION_HAS_AMOUNT, 1)).toBe(true);
  });

  it('splits at the bidding threshold, with the line itself going to bidding', () => {
    expect(appliesToAmount(CONDITION_BELOW_THRESHOLD, BIDDING_THRESHOLD - 1)).toBe(true);
    expect(appliesToAmount(CONDITION_BELOW_THRESHOLD, BIDDING_THRESHOLD)).toBe(false);

    expect(appliesToAmount(CONDITION_AT_LEAST_THRESHOLD, BIDDING_THRESHOLD)).toBe(true);
    expect(appliesToAmount(CONDITION_AT_LEAST_THRESHOLD, BIDDING_THRESHOLD - 1)).toBe(false);
  });

  it('treats a condition it does not know as unconditional', () => {
    // Dropping it instead would silently shorten a chain that is already
    // routing real paper.
    expect(appliesToAmount('something_newer', null)).toBe(true);
  });
});

describe('stepsForAmount', () => {
  it('resolves to the approval hop alone when no amount is attached', () => {
    expect(actions(null)).toEqual(['Approve request']);
  });

  it('takes the small-value path below ₱2M', () => {
    expect(actions(1_500_000)).toEqual([
      'Approve request',
      'Certify fund availability',
      'Small Value Procurement',
    ]);
  });

  it('takes the public bidding path at ₱2M and above', () => {
    expect(actions(BIDDING_THRESHOLD)).toEqual([
      'Approve request',
      'Certify fund availability',
      'Public Bidding',
    ]);
    expect(actions(9_000_000)).toEqual([
      'Approve request',
      'Certify fund availability',
      'Public Bidding',
    ]);
  });

  it('returns the chain in step order however the steps were stored', () => {
    const shuffled = [
      { stepOrder: 3, action: 'Delivery & inspection', condition: null },
      { stepOrder: 1, action: 'Approve request', condition: null },
      { stepOrder: 2, action: 'Procurement', condition: null },
    ];

    expect(stepsForAmount(shuffled, null).map((step) => step.action)).toEqual([
      'Approve request',
      'Procurement',
      'Delivery & inspection',
    ]);
  });
});
