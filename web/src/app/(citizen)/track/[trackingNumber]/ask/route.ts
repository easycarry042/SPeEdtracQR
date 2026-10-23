import { NextResponse, type NextRequest } from 'next/server';
import * as DocumentAssistant from '@/lib/domain/ai/document-assistant';
import { findTrackable } from '@/lib/domain/citizen-thread';
import { invalid, jsonBody, str } from '@/lib/json-route';
import { crossSite, throttle, tooManyRequests } from '@/lib/rate-limit';

/**
 * Port of `DocumentAssistantController@ask` — a question about ONE request,
 * answered from its public-safe facts. Internal requests are 404 here as on
 * every public page.
 */
export const dynamic = 'force-dynamic';

export async function POST(
  request: NextRequest,
  { params }: { params: Promise<{ trackingNumber: string }> },
): Promise<Response> {
  if (crossSite(request)) {
    return new Response('Forbidden', { status: 403 });
  }

  if (!(await throttle('speedyAsk', request))) {
    return tooManyRequests();
  }

  const document = await findTrackable(decodeURIComponent((await params).trackingNumber));

  if (!document) {
    return NextResponse.json({ message: 'Not found.' }, { status: 404 });
  }

  const question = str((await jsonBody(request)).question).trim();

  if (question.length < 2 || question.length > 500) {
    return invalid('question', 'Please type a question between 2 and 500 characters.');
  }

  const { answer, source } = await DocumentAssistant.answer(document, question);

  return NextResponse.json({ answer, source });
}
