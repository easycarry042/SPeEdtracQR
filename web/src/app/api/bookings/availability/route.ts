import { NextResponse } from 'next/server';
import { bookedFacilityDates } from '@/lib/domain/facility-availability';
import { positiveId } from '@/lib/file-response';
import { throttle, tooManyRequests } from '@/lib/rate-limit';
import { formatNaive, now } from '@/lib/time';

export const dynamic = 'force-dynamic';

const MONTH_RE = /^\d{4}-(0[1-9]|1[0-2])$/;

/**
 * Public read-only availability check for facility booking calendars.
 * Returns occupied `YYYY-MM-DD` dates only — never requester names or details.
 */
export async function GET(request: Request): Promise<Response> {
  if (!(await throttle('publicLookup', request))) {
    return tooManyRequests();
  }

  const url = new URL(request.url, 'http://localhost');
  const resourceId = positiveId(url.searchParams.get('resourceId') ?? '');

  if (resourceId === null) {
    return NextResponse.json({ message: 'Invalid facility.' }, { status: 400 });
  }

  const rawMonth = url.searchParams.get('month');
  const month = rawMonth ? rawMonth.trim() : formatNaive(now(), 'Y-m');

  if (!MONTH_RE.test(month)) {
    return NextResponse.json({ message: 'Invalid month.' }, { status: 400 });
  }

  const bookedDates = await bookedFacilityDates(resourceId, month);

  return NextResponse.json(
    { resourceId, month, bookedDates },
    { headers: { 'Cache-Control': 'no-store' } },
  );
}
