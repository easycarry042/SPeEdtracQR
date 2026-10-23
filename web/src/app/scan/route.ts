import { NextResponse, type NextRequest } from 'next/server';
import { routes } from '@/lib/routes';

/**
 * Port of `ScanController@index` — the legacy /scan entry point. Scanning now
 * lives in the header's Scan QR Code, on every staff page, so this just lands
 * the person where they work.
 */
export const dynamic = 'force-dynamic';

export function GET(request: NextRequest): Response {
  return NextResponse.redirect(new URL(routes.home(), request.url));
}
