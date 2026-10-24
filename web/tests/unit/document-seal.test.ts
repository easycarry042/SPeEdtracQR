import { createHmac } from 'node:crypto';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { signature, verify } from '@/lib/domain/document-seal';

/**
 * The seal must match what Laravel printed. `DocumentSeal::signature()` hashes
 * `created_at->timestamp`, and Laravel reads the stored wall-clock in
 * Asia/Manila, so the epoch is the REAL instant — 8 hours before the naive
 * reading this app keeps in memory. Getting it wrong fails every seal already
 * on issued paper ("Verification failed" on a genuine document).
 */
const KEY = 'base64:test-key-for-the-seal';
let previous: string | undefined;

beforeAll(() => {
  previous = process.env.APP_KEY;
  process.env.APP_KEY = KEY;
});

afterAll(() => {
  process.env.APP_KEY = previous;
});

// Stored as "2026-09-26 19:39:34" Manila; naive Dates carry that as UTC.
const document = {
  tracking_number: 'SPD-20260926-CCJXX9',
  document_type: 'Business Permit',
  created_at: new Date('2026-09-26T19:39:34Z'),
};

describe('document seal', () => {
  it('signs the real Manila epoch, as PHP did', () => {
    const realEpoch = Date.UTC(2026, 8, 26, 11, 39, 34) / 1000;
    const expected = createHmac('sha256', KEY)
      .update(`SPD-20260926-CCJXX9|Business Permit|${realEpoch}`)
      .digest('hex')
      .slice(0, 20);

    expect(signature(document)).toBe(expected);
  });

  it('verifies its own signature and refuses an altered one', () => {
    const sig = signature(document);

    expect(verify(document, sig)).toBe(true);
    expect(verify({ ...document, document_type: 'Cedula' }, sig)).toBe(false);
    expect(verify(document, null)).toBe(false);
  });
});
