import { cookies } from 'next/headers';
import { SignJWT, jwtVerify } from 'jose';

/**
 * Remembers which request the citizen just filed, so the receipt page
 * (/request/submitted) can be reopened while they save the QR.
 *
 * Port of `App\Support\CitizenReceipt`. Laravel kept this in the server session;
 * there isn't one here, so it rides in its own signed, httpOnly cookie with the
 * same 15-minute life. Signing matters: the tracking number stays out of the URL
 * precisely so nobody can open someone else's receipt, and an unsigned cookie
 * would hand that back by letting anyone paste a tracking number into it.
 *
 * It used to be a one-view flash, so any detour — the printable slip, the
 * tracking page, which polls in the background — wiped it and Back landed on a
 * blank "Submit a request" form. It now lasts a short while instead, and is
 * forgotten the moment anyone starts over. That is what the next person at a
 * shared counter does, so they still never find the previous citizen's receipt
 * waiting for them.
 */
import { RECEIPT_COOKIE_NAME, RECEIPT_LIFETIME_MINUTES } from '@/lib/domain/receipt-cookie';

export const LIFETIME_MINUTES = RECEIPT_LIFETIME_MINUTES;

function secret(): Uint8Array {
  const value = process.env.SESSION_SECRET;

  if (!value || value.length < 32) {
    throw new Error('SESSION_SECRET must be set to at least 32 characters.');
  }

  return new TextEncoder().encode(value);
}

export async function remember(trackingNumber: string): Promise<void> {
  const token = await new SignJWT({ tracking: trackingNumber })
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(`${LIFETIME_MINUTES}m`)
    .sign(secret());

  (await cookies()).set(RECEIPT_COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: LIFETIME_MINUTES * 60,
  });
}

/** The tracking number of the receipt still on offer, or null once it has expired or been forgotten. */
export async function trackingNumber(): Promise<string | null> {
  const token = (await cookies()).get(RECEIPT_COOKIE_NAME)?.value;

  if (!token) {
    return null;
  }

  try {
    const { payload } = await jwtVerify(token, secret());
    const tracking = payload.tracking;

    return typeof tracking === 'string' && tracking !== '' ? tracking : null;
  } catch {
    // Expired or tampered-with: treat as no receipt rather than erroring.
    return null;
  }
}

export async function forget(): Promise<void> {
  (await cookies()).delete(RECEIPT_COOKIE_NAME);
}

