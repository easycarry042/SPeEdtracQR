import { NextResponse, type NextRequest } from 'next/server';
import * as DocumentAssistant from '@/lib/domain/ai/document-assistant';
import * as SiteAssistant from '@/lib/domain/ai/site-assistant';
import { contextFor } from '@/lib/domain/ai/speedy-context';
import { findTrackable } from '@/lib/domain/citizen-thread';
import { invalid, jsonBody, str } from '@/lib/json-route';
import { crossSite, throttle, tooManyRequests } from '@/lib/rate-limit';

/**
 * Port of `SpeedyController@ask` — Speedy, the one assistant on every citizen
 * page.
 *
 * Without a request in context it answers about the service (SiteAssistant).
 * A tracking number — sent as the context, or typed or scanned into the chat —
 * makes that request the topic, answered from its public facts. Knowing a
 * tracking number grants no more than the tracking page already shows, and
 * internal requests are reported as not found.
 */
export const dynamic = 'force-dynamic';

/** A tracking number anywhere in a message, e.g. "where is SPD-20261016-ABC123?". */
const TRACKING_IN_TEXT = /\b(SPD|INT)-\d{8}-[0-9A-Z]{6}\b/i;

export async function POST(request: NextRequest): Promise<Response> {
  if (crossSite(request)) {
    return new Response('Forbidden', { status: 403 });
  }

  if (!(await throttle('speedyAsk', request))) {
    return tooManyRequests();
  }

  const body = await jsonBody(request);
  const question = str(body.question).trim();
  const tracking = str(body.tracking).trim();

  if (question.length < 2 || question.length > 500) {
    return invalid('question', 'Please type a question between 2 and 500 characters.');
  }

  // A number in the message itself wins over the current topic: that is the
  // citizen switching to another request.
  const typed = TRACKING_IN_TEXT.exec(question);

  if (typed) {
    const trackingNumber = typed[0].toUpperCase();
    const document = await findTrackable(trackingNumber);

    if (!document) {
      return NextResponse.json({
        answer: `I couldn't find ${trackingNumber}. Check the number on your confirmation email or slip — it looks like SPD-20261016-ABC123.`,
        source: 'fallback',
      });
    }

    const context = await contextFor(document);

    return NextResponse.json({
      answer: `Found it — ${context.tracking} is a ${context.type}, currently ${context.status}. What would you like to know about it?`,
      source: 'fallback',
      context,
    });
  }

  if (tracking !== '') {
    const document = await findTrackable(tracking);

    if (!document) {
      return NextResponse.json({
        answer: "I can't find that request any more. Give me its tracking number again and I'll take another look.",
        source: 'fallback',
        context: null,
      });
    }

    return NextResponse.json(await DocumentAssistant.answer(document, question));
  }

  return NextResponse.json(await SiteAssistant.answer(question));
}
