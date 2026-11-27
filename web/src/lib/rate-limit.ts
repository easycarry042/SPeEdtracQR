import { db } from '@/lib/db';

/**
 * Laravel's `throttle:{max},{minutes}` for the public endpoints.
 *
 * A fixed window per key, counted in Postgres in one statement, because a
 * Vercel deployment runs many function instances with no memory in common —
 * an in-process counter would let each instance grant its own quota. The
 * window clock is the database's real time (timestamptz), not the naive
 * Manila encoding the domain tables use.
 */
export type Limit = { max: number; perSeconds: number };

/** The limits Laravel's routes declared, by name. */
export const LIMITS = {
  speedyAsk: { max: 30, perSeconds: 60 },
  threadRead: { max: 60, perSeconds: 60 },
  threadWrite: { max: 12, perSeconds: 60 },
  /** Guessing someone's email or mobile number is the attack to slow down. */
  verifyContact: { max: 10, perSeconds: 60 },
  citizenUpload: { max: 12, perSeconds: 60 },
  trackStatus: { max: 60, perSeconds: 60 },
  publicLookup: { max: 30, perSeconds: 60 },
  passwordReset: { max: 6, perSeconds: 60 },
  login: { max: 10, perSeconds: 60 },
  citizenSubmit: { max: 8, perSeconds: 60 },
} as const satisfies Record<string, Limit>;

const memoryCounters = new Map<string, { hits: number; resetAt: number }>();

/** Clear in-memory fallback counters (intended for test isolation). */
export function resetMemoryRateLimits(): void {
  memoryCounters.clear();
}

function memoryHit(key: string, limit: Limit): boolean {
  const nowMs = Date.now();
  const windowMs = limit.perSeconds * 1000;
  const current = memoryCounters.get(key);

  if (!current || current.resetAt <= nowMs) {
    if (memoryCounters.size > 5000) {
      for (const [entryKey, entry] of memoryCounters) {
        if (entry.resetAt <= nowMs) {
          memoryCounters.delete(entryKey);
        }
      }
    }
    memoryCounters.set(key, { hits: 1, resetAt: nowMs + windowMs });
    return true;
  }

  current.hits += 1;
  return current.hits <= limit.max;
}

/**
 * Count one hit against `key`. Returns false once the window's quota is spent.
 * Falls back to a per-instance in-memory window if the database query fails so
 * abuse protection degrades gracefully instead of failing wide open.
 */
export async function hit(key: string, limit: Limit): Promise<boolean> {
  try {
    const rows = await db.$queryRaw<Array<{ hits: number }>>`
      INSERT INTO "rate_limits" ("key", "hits", "reset_at")
      VALUES (${key}, 1, NOW() + make_interval(secs => ${limit.perSeconds}))
      ON CONFLICT ("key") DO UPDATE SET
        "hits" = CASE WHEN "rate_limits"."reset_at" <= NOW() THEN 1 ELSE "rate_limits"."hits" + 1 END,
        "reset_at" = CASE WHEN "rate_limits"."reset_at" <= NOW()
          THEN NOW() + make_interval(secs => ${limit.perSeconds})
          ELSE "rate_limits"."reset_at" END
      RETURNING "hits"`;

    // Expired windows are dead rows; sweep a few now and then.
    if (Math.random() < 0.01) {
      void db.rate_limits.deleteMany({ where: { reset_at: { lt: new Date() } } }).catch(() => {});
    }

    return (rows[0]?.hits ?? 0) <= limit.max;
  } catch (error) {
    console.error('[rate-limit] counter unavailable, using in-memory fallback:', error);

    return memoryHit(key, limit);
  }
}

/** The caller's address as Vercel (or any proxy) reports it. */
export function clientIp(headers: Headers): string {
  const forwarded = headers.get('x-forwarded-for')?.split(',')[0]?.trim();

  return forwarded || headers.get('x-real-ip') || 'unknown';
}

/**
 * `hit()` keyed by route name + caller, the way Laravel's throttle keyed it.
 * Takes a route handler's Request, or a server action's `await headers()`.
 */
export function throttle(name: keyof typeof LIMITS, from: Request | Headers): Promise<boolean> {
  return hit(`${name}:${clientIp(from instanceof Request ? from.headers : from)}`, LIMITS[name]);
}

export function tooManyRequests(): Response {
  return Response.json(
    { message: 'Too many attempts. Please wait a minute and try again.' },
    { status: 429, headers: { 'Retry-After': '60' } },
  );
}

/**
 * Refuse a state-changing request sent from another site. Browsers always send
 * `Origin` on a cross-site POST; one that names another host is refused. (The
 * cookies these endpoints read are SameSite=Lax as well — this is the second
 * lock, standing in for Laravel's CSRF token.)
 */
export function crossSite(request: Request): boolean {
  const secFetchSite = request.headers.get('sec-fetch-site')?.toLowerCase();
  if (secFetchSite && !['same-origin', 'same-site', 'none'].includes(secFetchSite)) {
    return true;
  }

  const host = request.headers.get('x-forwarded-host') ?? request.headers.get('host');
  const origin = request.headers.get('origin');

  if (origin !== null) {
    try {
      return new URL(origin).host !== host;
    } catch {
      return true;
    }
  }

  const referer = request.headers.get('referer');
  if (referer !== null) {
    try {
      return new URL(referer).host !== host;
    } catch {
      return true;
    }
  }

  return false;
}
