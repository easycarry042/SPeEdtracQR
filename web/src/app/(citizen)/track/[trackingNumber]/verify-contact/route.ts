import { NextResponse, type NextRequest } from 'next/server';
import { detailMatches, markVerified } from '@/lib/domain/citizen-thread-access';
import { findTrackable, markRead, messages } from '@/lib/domain/citizen-thread';
import { invalid, jsonBody, str } from '@/lib/json-route';
import { crossSite, hit, throttle, tooManyRequests } from '@/lib/rate-limit';

/**
 * Port of `CitizenMessageController@verify` — confirm the email or contact
 * number on file, which unlocks the request's conversation in this browser.
 *
 * Throttled per caller (as Laravel was) and also per request, so guesses at
 * one citizen's mobile number cannot be spread across many addresses.
 */
export const dynamic = 'force-dynamic';

export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ trackingNumber: string }> },
): Promise<Response> {
  if (crossSite(request)) {
    return new Response('Forbidden', { status: 403 });
  }

  if (!(await throttle('verifyContact', request))) {
    return tooManyRequests();
  }

  const document = await findTrackable(decodeURIComponent((await params).trackingNumber));

  if (!document) {
    return NextResponse.json({ message: 'Not found.' }, { status: 404 });
  }

  if (!(await hit(`verifyContact:doc:${document.tracking_number}`, { max: 30, perSeconds: 3600 }))) {
    return tooManyRequests();
  }

  const detail = str((await jsonBody(request)).detail).trim();

  if (detail === '' || detail.length > 255) {
    return invalid('detail', 'Enter the email address or mobile number on this request.');
  }

  if (!detailMatches(document, detail)) {
    // One message for both cases: never confirm which detail is on file.
    return invalid(
      'detail',
      "That doesn't match the contact details on this request. Try the email address or mobile number you filed it with.",
    );
  }

  await markVerified(document.tracking_number);
  await markRead(document.id);

  return NextResponse.json({ verified: true, messages: await messages(document) });
}
