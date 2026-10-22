import { createHmac, timingSafeEqual } from 'node:crypto';
import { app } from '@/lib/config';

/**
 * The payload printed in a request's QR code (receipt, claim slip, folder sticker).
 *
 * It is deliberately NOT a bare URL for citizen requests. A phone's camera or a
 * generic QR app decodes it to meaningless text — there is nothing to open — so
 * the code only works through this application's own scanners. It carries the
 * tracking number plus a short HMAC signature, so a code somebody types into a
 * QR generator is rejected: only codes this office printed are accepted.
 *
 * Format: "SPDQR1:" + base32(tracking number bytes + 8 signature bytes). Base32
 * keeps every character inside the QR alphanumeric set, so the code stays as
 * small and as easy to scan as the URL it replaces.
 *
 * Byte-compatible with the Laravel `App\Support\TrackingQr` it replaces —
 * stickers already printed and folders already in the office must keep
 * verifying, which is why the HMAC key below is the raw `APP_KEY` string
 * (`base64:` prefix included) rather than its decoded bytes: that is the string
 * `config('app.key')` handed to `hash_hmac`.
 */
/** The signed-payload marker every QR this system issues carries. */
export const PREFIX = 'SPDQR1:';
const SIGNATURE_BYTES = 8;
const TRACKING_PATTERN = /^(?:SPD|INT)-\d{8}-[0-9A-Z]{6}$/;
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** The query parameter the internal tracker reads a signed payload from. */
export const URL_PARAMETER = 'qr';

export { PREFIX as QR_PREFIX };

/**
 * QR_SIGNING_KEY when set, otherwise APP_KEY. A dedicated key matters in
 * production: every printed sticker is signed with it, so rotating APP_KEY
 * alone must not invalidate folders already sitting in the office.
 */
function key(): string {
  const value = process.env.QR_SIGNING_KEY || process.env.APP_KEY || '';

  if (value === '') {
    throw new Error('No QR signing key: set QR_SIGNING_KEY or APP_KEY in .env.');
  }

  return value;
}

function signature(trackingNumber: string): Buffer {
  return createHmac('sha256', key())
    .update(`tracking-qr|${trackingNumber}`)
    .digest()
    .subarray(0, SIGNATURE_BYTES);
}

function base32Encode(bytes: Buffer): string {
  let bits = '';

  for (const byte of bytes) {
    bits += byte.toString(2).padStart(8, '0');
  }

  let out = '';

  for (let i = 0; i < bits.length; i += 5) {
    out += BASE32_ALPHABET[Number.parseInt(bits.slice(i, i + 5).padEnd(5, '0'), 2)];
  }

  return out;
}

function base32Decode(text: string): Buffer | null {
  if (text === '' || [...text].some((char) => !BASE32_ALPHABET.includes(char))) {
    return null;
  }

  let bits = '';

  for (const char of text) {
    bits += BASE32_ALPHABET.indexOf(char).toString(2).padStart(5, '0');
  }

  // The last character's spare bits must be zero, as the encoder writes them;
  // otherwise several spellings would decode to the same code.
  const spare = bits.length % 8;

  if (spare >= 5 || (spare > 0 && bits.slice(-spare).includes('1'))) {
    return null;
  }

  const usable = bits.slice(0, bits.length - spare);
  const bytes: number[] = [];

  for (let i = 0; i < usable.length; i += 8) {
    bytes.push(Number.parseInt(usable.slice(i, i + 8), 2));
  }

  return Buffer.from(bytes);
}

export function payload(trackingNumber: string): string {
  const upper = trackingNumber.toUpperCase();

  return PREFIX + base32Encode(Buffer.concat([Buffer.from(upper, 'binary'), signature(upper)]));
}

/**
 * What a request's QR image encodes. Citizen requests: the bare signed payload.
 * Internal requests: the payload inside a link to the public internal tracker,
 * so a phone's own camera opens it — anyone may see who is handling an internal
 * request — and our scanners still read and verify the payload out of the link.
 */
export function content(trackingNumber: string): string {
  const signed = payload(trackingNumber);

  // The path must match the `internal.track` route exactly (it is
  // /internal-requests/track, not /internal/track): this URL is printed onto
  // folder stickers, so a wrong path is a dead QR that no phone camera can open.
  return trackingNumber.toUpperCase().startsWith('INT-')
    ? `${app.url}/internal-requests/track?${URL_PARAMETER}=${encodeURIComponent(signed)}`
    : signed;
}

/**
 * The tracking number inside a scanned payload, or null when it is not one of
 * our QR codes or its signature does not match.
 */
export function trackingNumber(scanned: string): string | null {
  const upper = scanned.trim().toUpperCase();

  if (!upper.startsWith(PREFIX)) {
    return null;
  }

  const bytes = base32Decode(upper.slice(PREFIX.length));

  if (bytes === null || bytes.length <= SIGNATURE_BYTES) {
    return null;
  }

  const tracking = bytes.subarray(0, bytes.length - SIGNATURE_BYTES).toString('binary');
  const provided = bytes.subarray(bytes.length - SIGNATURE_BYTES);

  if (!TRACKING_PATTERN.test(tracking)) {
    return null;
  }

  const expected = signature(tracking);

  return expected.length === provided.length && timingSafeEqual(expected, provided) ? tracking : null;
}
