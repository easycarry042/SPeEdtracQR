import { describe, expect, it } from 'vitest';
import { content, payload, trackingNumber } from '@/lib/domain/tracking-qr';

/**
 * Port of `tests/Feature/TrackingQrTest.php`, plus golden payloads captured
 * from the Laravel implementation with the project's real APP_KEY.
 *
 * These goldens are the compatibility contract: QR stickers already printed on
 * folders sitting in the office must keep verifying after the rewrite. If one
 * of them fails, the signing input changed and every printed code is dead.
 */
const GOLDEN: Record<string, string> = {
  'SPD-20261021-A7K2M9': 'SPDQR1:KNIEILJSGAZDMMJQGIYS2QJXJMZE2OL64HOLXU4CLRAQ',
  'INT-20260915-ZZ3Q4R': 'SPDQR1:JFHFILJSGAZDMMBZGE2S2WS2GNITIUVO43GXMKUCHDRQ',
  'SPD-20250101-234567': 'SPDQR1:KNIEILJSGAZDKMBRGAYS2MRTGQ2TMN2QPTWDSWJKVKMQ',
};

describe('TrackingQr', () => {
  it('produces the same payloads as the Laravel implementation', () => {
    for (const [code, expected] of Object.entries(GOLDEN)) {
      expect(payload(code), code).toBe(expected);
    }
  });

  it('reads a tracking number back out of its own payload', () => {
    for (const code of Object.keys(GOLDEN)) {
      expect(trackingNumber(payload(code))).toBe(code);
    }
  });

  it('accepts a lowercase scan of a valid payload', () => {
    const code = 'SPD-20261021-A7K2M9';

    expect(trackingNumber(payload(code).toLowerCase())).toBe(code);
  });

  it('rejects a payload whose signature was tampered with', () => {
    const valid = payload('SPD-20261021-A7K2M9');
    const lastChar = valid.at(-1);
    const forged = `${valid.slice(0, -1)}${lastChar === 'A' ? 'B' : 'A'}`;

    expect(trackingNumber(forged)).toBeNull();
  });

  it('rejects a tracking number somebody typed into a QR generator', () => {
    // No signature at all — the whole point of signing the payload.
    expect(trackingNumber('SPD-20261021-A7K2M9')).toBeNull();
    expect(trackingNumber('SPDQR1:SPD-20261021-A7K2M9')).toBeNull();
  });

  it('rejects anything that is not one of our payloads', () => {
    expect(trackingNumber('https://example.com/track/SPD-20261021-A7K2M9')).toBeNull();
    expect(trackingNumber('')).toBeNull();
    expect(trackingNumber('SPDQR1:')).toBeNull();
    // Characters outside the base32 alphabet.
    expect(trackingNumber('SPDQR1:0189!!')).toBeNull();
  });

  it('rejects a payload carrying a malformed tracking number', () => {
    // Correctly signed shape, but not SPD-/INT- + 8 digits + 6 base32 chars.
    expect(trackingNumber(payload('XXX-1-A'))).toBeNull();
  });

  it('encodes a citizen request as the bare payload', () => {
    const code = 'SPD-20261021-A7K2M9';

    expect(content(code)).toBe(payload(code));
  });

  it('encodes an internal request as a tracker link carrying the payload', () => {
    const code = 'INT-20260915-ZZ3Q4R';
    const encoded = content(code);

    // A phone camera must find something to open, and our scanner must still
    // be able to read the signed payload back out of the link.
    //
    // The path has to be the real `internal.track` route. This assertion used
    // to accept /internal/track, which no route serves — the sticker would have
    // opened a 404 on the one device the link exists for.
    expect(encoded).toContain('/internal-requests/track?qr=');
    expect(trackingNumber(decodeURIComponent(encoded.split('qr=')[1]))).toBe(code);
  });
});
