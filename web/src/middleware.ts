import { NextResponse, type NextRequest } from 'next/server';
import { SESSION_COOKIE_NAME, verifySessionToken } from '@/lib/auth/session';

/**
 * Replaces Laravel's route middleware stack.
 *
 * Only the cheap, cookie-level checks happen here — the Edge runtime has no
 * database, so permission checks (`permission:`, department scoping) stay in
 * the pages and route handlers where the Actor can be loaded. This is the
 * `auth` / `guest` gate and the `no-store` headers, nothing more.
 */

/** Everything under these prefixes needs a signed-in user. */
const PROTECTED_PREFIXES = [
  '/dashboard',
  '/my-dashboard',
  '/admin',
  '/documents',
  '/requests',
  '/request-steps',
  '/inventory',
  '/bookings',
  '/reports',
  '/analytics',
  '/history',
  '/scan',
  '/staff',
  '/profile',
  '/notifications',
  '/attachments',
  '/custody',
  '/nav/badges',
  '/messages',
];

/** A signed-in user visiting these is sent to their landing page instead. */
const GUEST_ONLY = ['/login', '/forgot-password', '/reset-password'];

/**
 * Public, and deliberately so — citizens reach these with no account, and the
 * internal tracker is what an INT- QR opens on a phone.
 */
const ALWAYS_PUBLIC = [
  '/',
  '/home',
  '/privacy',
  '/citizen',
  '/request',
  '/track',
  '/track-lookup',
  '/track-search',
  '/verify',
  '/internal-requests/track',
  '/speedy',
];

function isUnder(pathname: string, prefixes: string[]): boolean {
  return prefixes.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

export async function middleware(request: NextRequest) {
  const { pathname, search } = request.nextUrl;

  const token = request.cookies.get(SESSION_COOKIE_NAME)?.value;
  const session = token ? await verifySessionToken(token) : null;

  if (isUnder(pathname, GUEST_ONLY) && session) {
    // Where they actually belong depends on their role, which needs the
    // database; `/home` resolves that server-side and redirects on.
    return NextResponse.redirect(new URL('/home', request.url));
  }

  if (!session && isUnder(pathname, PROTECTED_PREFIXES) && !isUnder(pathname, ALWAYS_PUBLIC)) {
    const login = new URL('/login', request.url);

    // Come back here after signing in, the way Laravel's `intended()` did.
    if (pathname !== '/') {
      login.searchParams.set('next', `${pathname}${search}`);
    }

    return NextResponse.redirect(login);
  }

  const response = NextResponse.next();

  // Signed-in pages must not sit in the back/forward cache: pressing Back after
  // signing out used to show the previous user's dashboard from bfcache.
  if (session) {
    response.headers.set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    response.headers.set('Pragma', 'no-cache');
  }

  return response;
}

export const config = {
  // Skip Next's internals and static assets; SSE streams manage their own
  // auth and must not be wrapped in a redirect.
  matcher: ['/((?!_next/static|_next/image|favicon.ico|api/stream).*)'],
};
