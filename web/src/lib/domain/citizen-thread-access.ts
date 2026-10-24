import { cookies } from 'next/headers';
import { timingSafeEqual } from 'node:crypto';
import { SignJWT, jwtVerify } from 'jose';

/**
 * Who may WRITE in a request's citizen-visible thread.
 *
 * Port of `App\Support\CitizenThreadAccess`. Reading the tracking page needs
 * only the tracking number, as before. Posting is different: a forwarded or
 * shoulder-surfed link must not let a stranger speak as the requester, because
 * staff act on what that thread says. So the citizen confirms one detail already
 * on the request — the email or the contact number — and that confirmation is
 * remembered for the session, per request.
 *
 * Comparison is deliberately forgiving about formatting (spaces, dashes, +63 vs
 * 0 prefixes, letter case) and strict about the value, and it never reveals
 * which of the two details matched, nor whether one is on file at all.
 *
 * Laravel kept the confirmation in the server session. There isn't one here, so
 * it rides in a signed cookie listing the confirmed tracking numbers. Signing is
 * the whole mechanism: an unsigned cookie would let anyone grant themselves
 * write access by typing a tracking number into it.
 */
const COOKIE_NAME = 'speedtraqr_thread';

/** Matches Laravel's session lifetime, so a confirmation lasts the visit. */
const LIFETIME_SECONDS = 60 * 60 * 8;

function secret(): Uint8Array {
  const value = process.env.SESSION_SECRET;

  if (!value || value.length < 32) {
    throw new Error('SESSION_SECRET must be set to at least 32 characters.');
  }

  return new TextEncoder().encode(value);
}

/**
 * Every request this browser has confirmed — what Speedy's unread badge watches
 * on any page.
 *
 * Empty for a visitor who never confirmed one, which is nearly everyone, so no
 * query runs for them.
 */
export async function verifiedTrackingNumbers(): Promise<string[]> {
  const token = (await cookies()).get(COOKIE_NAME)?.value;

  if (!token) {
    return [];
  }

  try {
    const { payload } = await jwtVerify(token, secret());
    const list = payload.tracking;

    return Array.isArray(list) ? list.filter((value): value is string => typeof value === 'string') : [];
  } catch {
    // Expired or tampered-with: treat as nothing confirmed.
    return [];
  }
}

export async function isVerified(trackingNumber: string): Promise<boolean> {
  return (await verifiedTrackingNumbers()).includes(trackingNumber.toUpperCase());
}

export async function markVerified(trackingNumber: string): Promise<void> {
  const confirmed = new Set(await verifiedTrackingNumbers());
  confirmed.add(trackingNumber.toUpperCase());

  const token = await new SignJWT({ tracking: [...confirmed] })
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(`${LIFETIME_SECONDS}s`)
    .sign(secret());

  (await cookies()).set(COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: LIFETIME_SECONDS,
  });
}

/** Whether posting can be offered at all — with nothing on file, nothing can match. */
export function canBeVerified(document: {
  citizen_email: string | null;
  citizen_contact: string | null;
}): boolean {
  return Boolean(document.citizen_email) || Boolean(document.citizen_contact);
}

/** Constant-time string compare, so a detail can't be recovered a character at a time. */
function equals(a: string, b: string): boolean {
  const left = Buffer.from(a);
  const right = Buffer.from(b);

  return left.length === right.length && timingSafeEqual(left, right);
}

/**
 * Digits only, with a Philippine mobile reduced to its national form, so
 * "+63 917 123 4567", "0917-123-4567" and "09171234567" all match.
 */
function normalisePhone(number: string): string {
  const digits = number.replace(/\D+/g, '');

  return digits.startsWith('63') && digits.length === 12 ? `0${digits.slice(2)}` : digits;
}

/** Does the supplied detail match the email or contact number on this request? */
export function detailMatches(
  document: { citizen_email: string | null; citizen_contact: string | null },
  detail: string,
): boolean {
  const offered = detail.trim();

  if (offered === '') {
    return false;
  }

  if (
    document.citizen_email &&
    equals(document.citizen_email.trim().toLowerCase(), offered.toLowerCase())
  ) {
    return true;
  }

  if (document.citizen_contact) {
    const onFile = normalisePhone(document.citizen_contact);
    const given = normalisePhone(offered);

    if (onFile !== '' && given !== '' && equals(onFile, given)) {
      return true;
    }
  }

  return false;
}

export const THREAD_COOKIE_NAME = COOKIE_NAME;
