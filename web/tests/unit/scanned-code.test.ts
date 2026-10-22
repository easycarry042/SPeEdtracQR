import { describe, expect, it } from 'vitest';
import { isOurs, trackingNumber } from '@/lib/domain/scanned-code';
import * as trackingQr from '@/lib/domain/tracking-qr';
import { detailMatches, canBeVerified } from '@/lib/domain/citizen-thread-access';
import { app } from '@/lib/config';

/** Our own origin, read from config — the host check is relative to APP_URL. */
const ORIGIN = app.url;

/**
 * Port of `ScannedCodeTest` and the matching half of `CitizenThreadAccessTest`.
 *
 * Both are gates, so the negative cases carry the weight: a code this office
 * never printed must never drive an action, and a forwarded tracking link must
 * never let a stranger speak as the requester.
 */
describe('ScannedCode — accepts our own codes', () => {
  it('reads a signed payload', () => {
    const code = 'SPD-20261022-A7K2M9';

    expect(trackingNumber(trackingQr.payload(code))).toBe(code);
  });

  it('reads a bare tracking number, so stickers printed before signing still work', () => {
    expect(trackingNumber('SPD-20261022-A7K2M9')).toBe('SPD-20261022-A7K2M9');
    expect(trackingNumber('INT-20260915-ZZ3Q4R')).toBe('INT-20260915-ZZ3Q4R');
  });

  it('upper-cases a lowercase tracking number', () => {
    expect(trackingNumber('spd-20261022-a7k2m9')).toBe('SPD-20261022-A7K2M9');
  });

  it('tolerates surrounding whitespace from a scanner', () => {
    expect(trackingNumber('  SPD-20261022-A7K2M9  ')).toBe('SPD-20261022-A7K2M9');
  });

  it('reads our own tracking and verification URLs', () => {
    expect(trackingNumber(`${ORIGIN}/track/SPD-20261022-A7K2M9`)).toBe(
      'SPD-20261022-A7K2M9',
    );
    expect(trackingNumber(`${ORIGIN}/verify/SPD-20261022-A7K2M9`)).toBe(
      'SPD-20261022-A7K2M9',
    );
    // Trailing segments (the slip, the status poll) still resolve.
    expect(trackingNumber(`${ORIGIN}/track/SPD-20261022-A7K2M9/slip`)).toBe(
      'SPD-20261022-A7K2M9',
    );
  });

  it('reads an internal QR, which wraps the signed payload in a tracker link', () => {
    const code = 'INT-20260915-ZZ3Q4R';

    // This is exactly what `content()` encodes for an INT- request.
    expect(trackingNumber(trackingQr.content(code))).toBe(code);
  });
});

describe('ScannedCode — refuses everything else', () => {
  it('refuses a code from another site', () => {
    expect(trackingNumber('https://evil.example.com/track/SPD-20261022-A7K2M9')).toBeNull();
  });

  it('refuses a Wi-Fi or payment code', () => {
    expect(trackingNumber('WIFI:S:CityHall;T:WPA;P:password;;')).toBeNull();
    expect(trackingNumber('00020101021138540011com.p2pqr')).toBeNull();
  });

  it('refuses arbitrary text that merely contains something tracking-shaped', () => {
    expect(trackingNumber('my code is SPD-20261022-A7K2M9 ok?')).toBeNull();
    expect(trackingNumber('SPD-20261022-A7K2M9-extra')).toBeNull();
  });

  it('refuses a malformed tracking number', () => {
    // Ambiguous characters are not in the alphabet, and the shape is exact.
    expect(trackingNumber('SPD-2026102-A7K2M9')).toBeNull();
    expect(trackingNumber('SPD-20261022-A7K2M')).toBeNull();
    expect(trackingNumber('XXX-20261022-A7K2M9')).toBeNull();
  });

  it('refuses a signed payload whose signature does not match', () => {
    const forged = `${trackingQr.payload('SPD-20261022-A7K2M9').slice(0, -4)}AAAA`;

    expect(trackingNumber(forged)).toBeNull();
  });

  it('refuses an internal tracker link with no signed payload', () => {
    expect(trackingNumber(`${ORIGIN}/internal-requests/track`)).toBeNull();
    expect(
      trackingNumber(`${ORIGIN}/internal-requests/track?qr=not-a-payload`),
    ).toBeNull();
  });

  it('refuses a path we do not serve', () => {
    expect(trackingNumber(`${ORIGIN}/admin/requests`)).toBeNull();
  });

  it('refuses empty and whitespace', () => {
    expect(trackingNumber('')).toBeNull();
    expect(trackingNumber('   ')).toBeNull();
  });

  it('isOurs agrees with trackingNumber', () => {
    expect(isOurs('SPD-20261022-A7K2M9')).toBe(true);
    expect(isOurs('WIFI:S:CityHall;;')).toBe(false);
  });
});

describe('CitizenThreadAccess.detailMatches', () => {
  const onFile = { citizen_email: 'Juan@Example.com', citizen_contact: '09171234567' };

  it('matches the email regardless of case or padding', () => {
    expect(detailMatches(onFile, 'juan@example.com')).toBe(true);
    expect(detailMatches(onFile, '  JUAN@EXAMPLE.COM  ')).toBe(true);
  });

  it('matches the contact number however it was typed', () => {
    for (const typed of ['09171234567', '0917-123-4567', '0917 123 4567', '+63 917 123 4567', '639171234567']) {
      expect(detailMatches(onFile, typed), typed).toBe(true);
    }
  });

  it('refuses a near miss', () => {
    expect(detailMatches(onFile, 'juan@example.co')).toBe(false);
    expect(detailMatches(onFile, '09171234568')).toBe(false);
  });

  it('refuses an empty detail', () => {
    expect(detailMatches(onFile, '')).toBe(false);
    expect(detailMatches(onFile, '   ')).toBe(false);
  });

  it('matches whichever detail is on file when only one is', () => {
    expect(detailMatches({ citizen_email: 'a@b.com', citizen_contact: null }, 'a@b.com')).toBe(true);
    expect(detailMatches({ citizen_email: null, citizen_contact: '09171234567' }, '09171234567')).toBe(true);
  });

  it('matches nothing when nothing is on file', () => {
    const blank = { citizen_email: null, citizen_contact: null };

    expect(detailMatches(blank, 'anything')).toBe(false);
    expect(detailMatches(blank, '')).toBe(false);
    // So posting is never even offered.
    expect(canBeVerified(blank)).toBe(false);
  });

  it('offers verification when either detail exists', () => {
    expect(canBeVerified({ citizen_email: 'a@b.com', citizen_contact: null })).toBe(true);
    expect(canBeVerified({ citizen_email: null, citizen_contact: '09171234567' })).toBe(true);
  });
});
