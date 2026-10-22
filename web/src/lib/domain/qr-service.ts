import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { randomInt } from 'node:crypto';
import QRCode from 'qrcode';
import { db } from '@/lib/db';
import * as trackingQr from '@/lib/domain/tracking-qr';
import { now, formatNaive } from '@/lib/time';

/**
 * Port of `App\Services\QrCodeService`.
 *
 * The PHP version drew the module matrix by hand because the bacon PNG backend
 * needs imagick, which the servers don't ship. Node has no such constraint — the
 * `qrcode` package renders the PNG directly — but the two settings that matter
 * in the field are kept identical: a 4-module quiet zone (ISO/IEC 18004, below
 * which phone cameras fail to lock onto a printed sticker) and error correction
 * level Q (~25%), so a folder sticker still decodes once it is scuffed from
 * handling. The payload is short, so there is capacity to spare.
 */

/**
 * Unambiguous Crockford-style alphabet: no 0/1/I/L/O/U to avoid transcription
 * mistakes when a citizen types the code by hand.
 * 30 chars ^ 6 positions ≈ 729M combos/day — resists enumeration.
 */
const TRACKING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

const TRACKING_SUFFIX_LENGTH = 6;

/** Where the Laravel `public` disk lives, so both stacks read the same files. */
const PUBLIC_DISK = path.join(process.cwd(), '..', 'storage', 'app', 'public');

/**
 * Generate a unique tracking number. External citizen tickets use the default
 * 'SPD' prefix; internal dept-to-dept requests pass 'INT' so the two are
 * distinguishable at a glance (SPD-YYYYMMDD-XXXXXX vs INT-YYYYMMDD-XXXXXX).
 */
export async function generateTrackingNumber(prefix = 'SPD'): Promise<string> {
  const stamp = formatNaive(now(), 'Ymd');
  const upper = prefix.toUpperCase();

  // Loop rather than trusting entropy: the uniqueness guarantee is the DB's.
  for (;;) {
    let suffix = '';
    for (let i = 0; i < TRACKING_SUFFIX_LENGTH; i += 1) {
      suffix += TRACKING_ALPHABET[randomInt(0, TRACKING_ALPHABET.length)];
    }

    const trackingNumber = `${upper}-${stamp}-${suffix}`;
    const taken = await db.documents.findFirst({
      where: { tracking_number: trackingNumber },
      select: { id: true },
    });

    if (!taken) {
      return trackingNumber;
    }
  }
}

export type QrResult = {
  success: boolean;
  relativePath: string | null;
  publicUrl: string | null;
  error: string | null;
};

/**
 * Render and store the request's QR.
 *
 * It encodes a signed TrackingQr payload, not a bare tracking number, so only
 * this application's scanners can use it. An internal (INT-) request's QR wraps
 * that payload in a link to the public internal tracker, so any phone camera can
 * open it — the client's request — while the signature still decides whether it
 * is one of ours.
 */
export async function generateAndStore(trackingNumber: string): Promise<QrResult> {
  const relativePath = `qrcodes/${trackingNumber}.png`;

  try {
    const png = await QRCode.toBuffer(trackingQr.content(trackingNumber), {
      type: 'png',
      errorCorrectionLevel: 'Q',
      margin: 4,
      width: 500,
    });

    const absolute = path.join(PUBLIC_DISK, relativePath);
    await mkdir(path.dirname(absolute), { recursive: true });
    await writeFile(absolute, png);

    return {
      success: true,
      relativePath,
      publicUrl: `/storage/${relativePath}`,
      error: null,
    };
  } catch (error) {
    // A missing QR must not lose the request: the caller files the document
    // anyway and the sticker can be regenerated (`qr:regenerate`).
    const message = error instanceof Error ? error.message : String(error);
    console.error('[qr] generation failed', { trackingNumber, message });

    return { success: false, relativePath: null, publicUrl: null, error: message };
  }
}
