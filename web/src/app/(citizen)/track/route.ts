import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';

/**
 * Port of `TrackController@index` — the old /track entry point, still printed
 * in emails and bookmarked. A tracking number (or a legacy `?open=`) resolves
 * on its own page; anything else goes home: guests to the landing page (they
 * search inline there), staff to their role's landing (the Look Up desk is
 * gone — the header's Scan QR finds a request).
 */
export const dynamic = 'force-dynamic';

export async function GET(request: NextRequest): Promise<Response> {
  const query = request.nextUrl.searchParams;
  const tracking = (query.get('tracking_number') ?? query.get('open') ?? '').trim().toUpperCase();

  const signedIn = (await currentActor()) !== null;

  if (tracking !== '') {
    // Staff follow the request to their own surfaces; ?view=citizen would pin
    // them to the public page instead.
    const target = signedIn ? `/track/${encodeURIComponent(tracking)}` : routes.trackShow(tracking);

    return NextResponse.redirect(new URL(target, request.url));
  }

  return NextResponse.redirect(new URL(signedIn ? routes.home() : '/', request.url));
}
