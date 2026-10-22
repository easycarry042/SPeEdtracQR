import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Port of the tracking-number guarantees in `QrCodeServiceTest`.
 *
 * The alphabet is the security-relevant part: 30 unambiguous characters over 6
 * positions is ~729M combinations per day, which is what makes a tracking number
 * unguessable — the public tracking page's only real defence. Dropping the
 * ambiguous glyphs also stops a citizen reading "0" as "O" off a printed slip.
 */
const findFirst = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { findFirst } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number) => BigInt(value),
}));

const { generateTrackingNumber } = await import('@/lib/domain/qr-service');

/** The unambiguous Crockford-style set: no 0/1/I/L/O/U. */
const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

describe('generateTrackingNumber', () => {
  beforeEach(() => {
    findFirst.mockReset();
    findFirst.mockResolvedValue(null);
  });

  it('produces the documented SPD-YYYYMMDD-XXXXXX shape', async () => {
    const tracking = await generateTrackingNumber();

    expect(tracking).toMatch(/^SPD-\d{8}-[0-9A-Z]{6}$/);
  });

  it('takes the INT prefix for internal requests, so the two are distinguishable at a glance', async () => {
    expect(await generateTrackingNumber('INT')).toMatch(/^INT-\d{8}-[0-9A-Z]{6}$/);
  });

  it('upper-cases a lowercase prefix rather than issuing a mixed-case number', async () => {
    expect(await generateTrackingNumber('int')).toMatch(/^INT-/);
  });

  it('only ever uses unambiguous characters in the suffix', async () => {
    // Enough draws that a stray 0/1/I/L/O/U would almost certainly surface.
    for (let i = 0; i < 200; i += 1) {
      const suffix = (await generateTrackingNumber()).split('-')[2];

      for (const char of suffix) {
        expect(ALPHABET, `"${char}" is ambiguous in print`).toContain(char);
      }
    }
  });

  it('re-draws when the number is already taken, so uniqueness is the database’s guarantee', async () => {
    findFirst
      .mockResolvedValueOnce({ id: 1n })
      .mockResolvedValueOnce({ id: 2n })
      .mockResolvedValueOnce(null);

    const tracking = await generateTrackingNumber();

    expect(tracking).toMatch(/^SPD-\d{8}-[0-9A-Z]{6}$/);
    expect(findFirst).toHaveBeenCalledTimes(3);
  });

  it('stamps today’s date in Manila, not the server’s own timezone', async () => {
    const tracking = await generateTrackingNumber();
    const { now, formatNaive } = await import('@/lib/time');

    expect(tracking.split('-')[1]).toBe(formatNaive(now(), 'Ymd'));
  });

  it('does not repeat itself across many draws', async () => {
    const seen = new Set<string>();

    for (let i = 0; i < 300; i += 1) {
      seen.add(await generateTrackingNumber());
    }

    // Same day, so the date segment is shared; the suffixes must differ.
    expect(seen.size).toBeGreaterThan(290);
  });
});
