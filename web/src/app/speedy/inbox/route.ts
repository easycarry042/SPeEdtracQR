import { NextResponse, type NextRequest } from 'next/server';
import { inbox } from '@/lib/domain/ai/speedy-context';
import { throttle, tooManyRequests } from '@/lib/rate-limit';

/** Unread office messages for the requests this browser has confirmed. */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  if (!(await throttle('threadRead', request))) {
    return tooManyRequests();
  }

  return NextResponse.json(await inbox(), { headers: { 'Cache-Control': 'private, no-store' } });
}
