import * as trackingQr from '@/lib/domain/tracking-qr';
import { app } from '@/lib/config';

/**
 * Server-side authority on what counts as one of OUR QR codes.
 *
 * Port of `App\Support\ScannedCode`. A scan is accepted only when the decoded
 * payload is a signed request QR (what every new code carries), a bare tracking
 * number we issue, or one of our own tracking/verification URLs on this host.
 * The last two keep stickers printed before signed QRs were introduced working.
 *
 * Everything else — a Wi-Fi code, a payment code, a link to another site, or
 * arbitrary text that happens to contain something tracking-number-shaped — is
 * rejected, so a code this office never printed can never drive an action.
 *
 * Mirrors `window.SpeedQr.extractTracking` in the client helper; the browser
 * copy is a convenience, this one is the gate.
 */

/** PREFIX-YYYYMMDD-XXXXXX: SPD = citizen request, INT = internal request. */
const TRACKING_PATTERN = /^(?:SPD|INT)-\d{8}-[0-9A-Z]{6}$/i;

/** Our own scannable paths and the tracking number each carries. */
const PATH_PATTERN = /^\/(?:track|verify)\/((?:SPD|INT)-\d{8}-[0-9A-Z]{6})(?:\/.*)?$/i;

/** Where an internal request's QR link points. */
const INTERNAL_TRACKER_PATH = '/internal-requests/track';

export const FOREIGN_CODE_MESSAGE =
  'That code was not issued by SPeED TraQR. Scan the QR printed on the claim slip or folder.';

function appUrl(): URL | null {
  try {
    return new URL(app.url);
  } catch {
    return null;
  }
}

function isOurHost(host: string): boolean {
  const base = appUrl();

  // With APP_URL unset or misconfigured — common in local setups reached over
  // 127.0.0.1 or a LAN IP — a host-less payload is still accepted below; only
  // an explicit foreign host is refused.
  return base === null ? true : host.toLowerCase() === base.host.toLowerCase();
}

/** Strip any app sub-directory prefix so /speedtraqr/track/X still matches. */
function normalisePath(path: string): string {
  const base = appUrl()?.pathname ?? '/';
  let normalised = path;

  if (base !== '/' && normalised.startsWith(base)) {
    normalised = normalised.slice(base.replace(/\/$/, '').length);
  }

  return `/${normalised.replace(/^\/+/, '')}`;
}

/**
 * The tracking number a scanned payload refers to, or null when the payload is
 * not a code this system issued.
 */
export function trackingNumber(payload: string): string | null {
  const trimmed = payload.trim();

  if (trimmed === '') {
    return null;
  }

  // A signed payload: the signature alone decides.
  if (trimmed.toUpperCase().startsWith(trackingQr.PREFIX)) {
    return trackingQr.trackingNumber(trimmed);
  }

  if (TRACKING_PATTERN.test(trimmed)) {
    return trimmed.toUpperCase();
  }

  // Otherwise it has to be one of our URLs — on this host, on a path we serve.
  let parsed: URL;

  try {
    // A relative path is resolved against our own origin; an absolute URL keeps
    // its own host, which `isOurHost` then checks.
    parsed = new URL(trimmed, app.url);
  } catch {
    return null;
  }

  if (!['http:', 'https:'].includes(parsed.protocol) || !isOurHost(parsed.host)) {
    return null;
  }

  const path = normalisePath(parsed.pathname);

  // An internal request's QR: a link to our internal tracker carrying the
  // signed payload. The signature decides.
  if (path === INTERNAL_TRACKER_PATH) {
    const signed = parsed.searchParams.get(trackingQr.URL_PARAMETER);

    return signed ? trackingQr.trackingNumber(signed) : null;
  }

  const match = PATH_PATTERN.exec(path);

  return match ? match[1].toUpperCase() : null;
}

export function isOurs(payload: string): boolean {
  return trackingNumber(payload) !== null;
}
