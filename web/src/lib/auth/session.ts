import { cookies } from 'next/headers';
import { SignJWT, jwtVerify } from 'jose';

/**
 * Signed-cookie sessions, replacing Laravel's server-side session table.
 *
 * Laravel's own session payload is PHP-serialised and encrypted with APP_KEY, so
 * it cannot be read here; signing in through the new stack mints a fresh cookie
 * instead. Passwords are untouched (see `verifyPassword`), so every existing
 * account signs in normally — only live sessions are dropped at cutover.
 */
const COOKIE_NAME = 'speedtraqr_session';
const MAX_AGE_SECONDS = 60 * 60 * 8; // matches Laravel's 480-minute session lifetime

export type SessionPayload = {
  /** `users.id` */
  sub: number;
};

function secret(): Uint8Array {
  const value = process.env.SESSION_SECRET;

  if (!value || value.length < 32) {
    throw new Error('SESSION_SECRET must be set to at least 32 characters.');
  }

  return new TextEncoder().encode(value);
}

export async function createSession(userId: number): Promise<void> {
  const token = await new SignJWT({ sub: String(userId) })
    .setProtectedHeader({ alg: 'HS256' })
    .setIssuedAt()
    .setExpirationTime(`${MAX_AGE_SECONDS}s`)
    .sign(secret());

  (await cookies()).set(COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: MAX_AGE_SECONDS,
  });
}

export async function destroySession(): Promise<void> {
  (await cookies()).delete(COOKIE_NAME);
}

export async function readSession(): Promise<SessionPayload | null> {
  const token = (await cookies()).get(COOKIE_NAME)?.value;

  return token ? verifySessionToken(token) : null;
}

/** Shared with the middleware, which has a cookie but no `cookies()` store. */
export async function verifySessionToken(token: string): Promise<SessionPayload | null> {
  try {
    const { payload } = await jwtVerify(token, secret());
    const sub = Number(payload.sub);

    return Number.isInteger(sub) && sub > 0 ? { sub } : null;
  } catch {
    // Expired or tampered-with cookie: treat as signed out rather than erroring.
    return null;
  }
}

export const SESSION_COOKIE_NAME = COOKIE_NAME;
