import { NextResponse, type NextRequest } from 'next/server';
import { canBeVerified, isVerified } from '@/lib/domain/citizen-thread-access';
import { findTrackable, isReplyTarget, markRead, messages, post, type TrackableDocument } from '@/lib/domain/citizen-thread';
import { check as checkUpload } from '@/lib/domain/upload-rules';
import { invalid } from '@/lib/json-route';
import { crossSite, throttle, tooManyRequests } from '@/lib/rate-limit';

/**
 * Port of `CitizenMessageController@thread` / `@store` — the citizen's half of
 * the conversation, behind Speedy's "Office messages" tab.
 *
 * Reading and writing both need the citizen confirmed (verify-contact): staff
 * replies can carry personal details, and the tracking number is printed on
 * slips and QR stickers. Everything here is visibility=public; internal staff
 * notes are never loaded.
 */
export const dynamic = 'force-dynamic';

type Params = { params: Promise<{ trackingNumber: string }> };

async function confirmed(trackingNumber: string): Promise<TrackableDocument | Response> {
  const document = await findTrackable(decodeURIComponent(trackingNumber));

  if (!document) {
    return NextResponse.json({ message: 'Not found.' }, { status: 404 });
  }

  if (!(await isVerified(document.tracking_number))) {
    return NextResponse.json({ verified: false, can_verify: canBeVerified(document) }, { status: 403 });
  }

  return document;
}

/** Opening the thread is reading it, so the office's messages stop counting as unread. */
async function thread(document: TrackableDocument): Promise<Response> {
  await markRead(document.id);

  return NextResponse.json(
    { verified: true, messages: await messages(document) },
    { headers: { 'Cache-Control': 'private, no-store' } },
  );
}

export async function GET(request: NextRequest, { params }: Params): Promise<Response> {
  if (!(await throttle('threadRead', request))) {
    return tooManyRequests();
  }

  const document = await confirmed((await params).trackingNumber);

  return document instanceof Response ? document : thread(document);
}

export async function POST(request: NextRequest, { params }: Params): Promise<Response> {
  if (crossSite(request)) {
    return new Response('Forbidden', { status: 403 });
  }

  if (!(await throttle('threadWrite', request))) {
    return tooManyRequests();
  }

  const document = await confirmed((await params).trackingNumber);

  if (document instanceof Response) {
    return document;
  }

  const form = await request.formData();
  const body = String(form.get('body') ?? '').trim();
  const file = form.get('attachment');
  const attachment = file instanceof File && file.size > 0 ? file : null;
  const parentRaw = form.get('parent_id');

  if (body === '') {
    return invalid('body', 'Write your message first.');
  }

  if (body.length > 5000) {
    return invalid('body', 'Keep your message under 5,000 characters.');
  }

  const problem = checkUpload(attachment);

  if (problem) {
    return invalid('attachment', problem);
  }

  let parentId: number | null = null;

  if (parentRaw !== null && parentRaw !== '') {
    parentId = Number(parentRaw);

    if (!Number.isInteger(parentId) || !(await isReplyTarget(document, parentId))) {
      return invalid('parent_id', 'That message is no longer available to reply to.');
    }
  }

  await post(document, body, attachment, parentId);

  return thread(document);
}
