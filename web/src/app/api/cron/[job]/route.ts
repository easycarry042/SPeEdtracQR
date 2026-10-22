import { timingSafeEqual } from 'node:crypto';
import { NextResponse, type NextRequest } from 'next/server';
import { SWEEPS } from '@/lib/sweeps';

/**
 * The scheduled sweeps, as HTTP endpoints.
 *
 * Laravel ran these from `schedule:run` on cron. There is no scheduler in a Next
 * process, so each sweep is invoked by an external cron hitting this route —
 * which is why it must be authenticated: these endpoints send mail and write to
 * every held or overdue request, so an open one is both a spam cannon and a way
 * to force-stamp `sla_breached_at` across the office.
 *
 * Set CRON_SECRET and call with `Authorization: Bearer <secret>`:
 *
 *   0 * * * *  curl -fsS -H "Authorization: Bearer $CRON_SECRET" \
 *                https://host/api/cron/check-sla
 *   0 7 * * *  …/api/cron/check-holds
 *   0 * * * *  …/api/cron/check-overdue-loans
 *
 * With no CRON_SECRET set the route refuses every request rather than running
 * unauthenticated — a misconfigured deploy should skip its sweeps loudly, not
 * expose them.
 */
export const dynamic = 'force-dynamic';

/** Constant-time compare, so the secret can't be recovered a byte at a time. */
function presented(request: NextRequest): boolean {
  const secret = process.env.CRON_SECRET;

  if (!secret || secret.length < 16) {
    return false;
  }

  const header = request.headers.get('authorization') ?? '';
  const offered = header.startsWith('Bearer ') ? header.slice(7) : '';

  const a = Buffer.from(offered);
  const b = Buffer.from(secret);

  return a.length === b.length && timingSafeEqual(a, b);
}

export async function POST(
  request: NextRequest,
  context: { params: Promise<{ job: string }> },
): Promise<NextResponse> {
  const { job } = await context.params;

  if (!presented(request)) {
    // Deliberately terse: an unauthenticated caller learns nothing about which
    // jobs exist.
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const sweep = SWEEPS[job];

  if (!sweep) {
    return NextResponse.json(
      { error: 'Unknown job', known: Object.keys(SWEEPS) },
      { status: 404 },
    );
  }

  try {
    const result = await sweep();

    return NextResponse.json({ ok: true, ...result });
  } catch (error) {
    // A crashed sweep is the exact silent failure these commands exist to
    // prevent, so it answers 500 — a monitored cron will alert on that.
    const message = error instanceof Error ? error.message : String(error);
    console.error(`[cron] ${job} failed:`, error);

    return NextResponse.json({ ok: false, job, error: message }, { status: 500 });
  }
}

/** GET is allowed too, because most cron services only issue GETs. */
export const GET = POST;
