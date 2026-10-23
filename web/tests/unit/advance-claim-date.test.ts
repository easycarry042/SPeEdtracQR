import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';
import { today, toDateString } from '@/lib/time';

/**
 * Port of the Advance half of `StatusGateTest`'s claiming-date cases.
 *
 * Advance reuses a claiming date already set in the Review modal (Adjust
 * Date) instead of asking again — unless it has passed, when the citizen
 * would be told to come on a day already gone.
 *
 * The booking check (`followClaimDate`) is the seam: it receives the day
 * Advance settled on, and refusing there stops the action before any write.
 */
const findFirst = vi.fn();
const update = vi.fn();
const validate = vi.fn();
const followClaimDate = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { findFirst, update } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/domain/status-gate', () => ({
  GateError: class extends Error {},
  assertExpectedStatus: vi.fn(),
  validate,
}));
vi.mock('@/lib/domain/lending-desk', () => ({ followClaimDate }));
vi.mock('@/lib/realtime', () => ({ broadcastStatusUpdated: vi.fn() }));
vi.mock('@/lib/domain/comments', () => ({
  AUTHOR: {},
  VISIBILITY: {},
  logSystemComment: vi.fn(),
  postComment: vi.fn(),
}));

const { advance } = await import('@/lib/domain/status-actions');

const DAY = 86_400_000;
const inDays = (days: number): Date => new Date(today().getTime() + days * DAY);

const admin = { id: 1, can: () => true, hasRole: () => false } as unknown as Actor;

function request(claimDate: Date | null) {
  return {
    id: 19n,
    status: DocumentStatus.InProgress,
    assigned_to: 1n,
    claim_date: claimDate,
  };
}

/** Runs advance up to the booking check and reports what it chose. */
async function chosen(claimDate: Date | null, picked?: string) {
  findFirst.mockResolvedValue(request(claimDate));
  followClaimDate.mockResolvedValue('stop here');

  await expect(
    advance(19, admin, { expectedStatus: DocumentStatus.InProgress, claimDate: picked }),
  ).rejects.toThrow('stop here');

  return { day: followClaimDate.mock.calls[0][1] as Date, note: validate.mock.calls[0][2] as string };
}

beforeEach(() => {
  vi.clearAllMocks();
});

describe('advance and the claiming date', () => {
  it('keeps a date already set when none is picked', async () => {
    const set = inDays(3);
    const { day, note } = await chosen(set);

    expect(day.getTime()).toBe(set.getTime());
    expect(note).toMatch(/^Claiming date stays /);
  });

  it('takes a newly picked date over the one set', async () => {
    const { day, note } = await chosen(inDays(3), toDateString(inDays(5)));

    expect(toDateString(day)).toBe(toDateString(inDays(5)));
    expect(note).toMatch(/^Claiming date set to /);
  });

  it('asks again when the date set has passed', async () => {
    findFirst.mockResolvedValue(request(inDays(-2)));

    await expect(
      advance(19, admin, { expectedStatus: DocumentStatus.InProgress, claimDate: '' }),
    ).rejects.toThrow(/has passed\. Pick a new one for the citizen\./);
    expect(update).not.toHaveBeenCalled();
  });

  it('asks for one when none is set', async () => {
    findFirst.mockResolvedValue(request(null));

    await expect(
      advance(19, admin, { expectedStatus: DocumentStatus.InProgress }),
    ).rejects.toThrow('Pick the claiming date for the citizen.');
  });

  it('refuses a picked day in the past', async () => {
    findFirst.mockResolvedValue(request(inDays(3)));

    await expect(
      advance(19, admin, { expectedStatus: DocumentStatus.InProgress, claimDate: toDateString(inDays(-1)) }),
    ).rejects.toThrow('The claiming date cannot be in the past.');
  });
});
