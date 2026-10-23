import { NextResponse, type NextRequest } from 'next/server';
import { db } from '@/lib/db';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';
import { throttle, tooManyRequests } from '@/lib/rate-limit';

/**
 * Port of `TrackController@status` — the tracking page's light poll. "Handled
 * by" is the OFFICE, never the staff member. Internal requests are not found
 * here, as on every public surface.
 */
export const dynamic = 'force-dynamic';

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ trackingNumber: string }> },
): Promise<Response> {
  if (!(await throttle('trackStatus', request))) {
    return tooManyRequests();
  }

  const tracking = decodeURIComponent((await params).trackingNumber).trim().toUpperCase();
  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, origin: { not: ORIGIN_INTERNAL }, deleted_at: null },
    select: { status: true, updated_at: true, department: { select: { name: true } } },
  });

  if (!document) {
    return NextResponse.json({ message: 'Not found.' }, { status: 404 });
  }

  return NextResponse.json(
    {
      status: document.status,
      current_department: document.department?.name ?? null,
      // Naive Manila wall-clock, labelled as it is stored.
      updated_at: document.updated_at?.toISOString() ?? null,
    },
    { headers: { 'Cache-Control': 'no-store' } },
  );
}
