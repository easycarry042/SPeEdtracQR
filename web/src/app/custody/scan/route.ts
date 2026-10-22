import { NextResponse, type NextRequest } from 'next/server';
import { currentActor } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';
import { receiveByScan } from '@/lib/domain/custody';

/**
 * One scan receives.
 *
 * `window.SpeedQr.openScanned()` posts here for every INT- code the header's
 * Scan QR Code or the Look Up page reads: if the scanner may receive the
 * request and nobody at the office has, this same scan records the receipt and
 * answers with where to go. Receiving used to take a second scan on the
 * request's own card.
 */
export async function POST(request: NextRequest) {
  const actor = await currentActor();

  if (!actor) {
    return NextResponse.json({ message: 'Sign in to scan.' }, { status: 401 });
  }

  let body: { scanned_value?: unknown; capture_method?: unknown };

  try {
    body = (await request.json()) as typeof body;
  } catch {
    return NextResponse.json({ message: 'Send the scanned value.' }, { status: 422 });
  }

  const scannedValue = typeof body.scanned_value === 'string' ? body.scanned_value : '';
  // A paper folder is received by camera only; `image` is for soft copies.
  const captureMethod = body.capture_method === 'image' ? 'image' : 'scan';

  if (scannedValue.trim() === '' || scannedValue.length > 2000) {
    return NextResponse.json({ message: 'Send the scanned value.' }, { status: 422 });
  }

  try {
    return NextResponse.json(await receiveByScan(actor, { scannedValue, captureMethod }), {
      headers: { 'Cache-Control': 'no-store' },
    });
  } catch (error) {
    if (error instanceof ActionError) {
      return NextResponse.json({ message: error.message }, { status: error.status });
    }

    console.error('[custody scan]', error);

    return NextResponse.json({ message: 'That scan could not be read.' }, { status: 500 });
  }
}
