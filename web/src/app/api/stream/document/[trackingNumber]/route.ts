import type { NextRequest } from 'next/server';
import { db } from '@/lib/db';
import { documentStatusPoll, eventStream, publicChannel } from '@/lib/realtime';

/**
 * PUBLIC live stream for one request's stage — the SSE replacement for the
 * Reverb channel `documents.{tracking}`.
 *
 * Anyone who knows a tracking number may subscribe, exactly as before, so this
 * carries only what the tracking page already shows: the stage, its label, and
 * the handling OFFICE. Never a staff name, and never anything from the
 * conversation (the `thread` event is a content-free ping by design).
 */
export const dynamic = 'force-dynamic';

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ trackingNumber: string }> },
) {
  const { trackingNumber } = await params;
  const tracking = decodeURIComponent(trackingNumber).toUpperCase();

  // Internal requests are not publicly trackable, so they get no public stream
  // either — and an unknown number must not be distinguishable from one.
  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, deleted_at: null, origin: { not: 'internal' } },
    select: { id: true },
  });

  if (!document) {
    return new Response('Not found', { status: 404 });
  }

  return eventStream(
    {
      channels: [publicChannel(tracking)],
      poll: documentStatusPoll(tracking),
    },
    request.signal,
  );
}
