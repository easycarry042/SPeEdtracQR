<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Ai\DocumentAssistant;
use App\Support\Ai\SiteAssistant;
use App\Support\Ai\SpeedyContext;
use App\Support\CitizenThreadAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Speedy, the one assistant on every citizen page.
 *
 * Without a request in context it answers questions about the service
 * (SiteAssistant). A tracking number — sent as the context, or typed or
 * scanned into the chat — makes that request the topic, and the
 * request-scoped DocumentAssistant answers from its public facts. Knowing a
 * tracking number grants no more than the tracking page already shows, and
 * internal office-to-office requests are reported as not found.
 */
class SpeedyController extends Controller
{
    /** A tracking number anywhere in a message, e.g. "where is SPD-20261016-ABC123?". */
    private const string TRACKING_IN_TEXT = '/\b(SPD|INT)-\d{8}-[0-9A-Z]{6}\b/i';

    public function ask(Request $request, SiteAssistant $site, DocumentAssistant $documents): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
            'tracking' => ['nullable', 'string', 'max:32'],
        ]);

        $question = $validated['question'];

        // A number in the message itself wins over the current topic: that is
        // the citizen switching to another request.
        if (preg_match(self::TRACKING_IN_TEXT, $question, $match) === 1) {
            return $this->switchTo($match[0]);
        }

        if (filled($validated['tracking'] ?? null)) {
            $document = SpeedyContext::findTrackable($validated['tracking']);

            if (! $document) {
                return response()->json([
                    'answer' => "I can't find that request any more. Give me its tracking number again and I'll take another look.",
                    'source' => 'fallback',
                    'context' => null,
                ]);
            }

            return response()->json($documents->answer($document, $question));
        }

        return response()->json($site->answer($question));
    }

    /** Unread office messages for the requests this browser has confirmed. */
    public function inbox(Request $request): JsonResponse
    {
        return response()->json(SpeedyContext::inbox($request));
    }

    private function switchTo(string $trackingNumber): JsonResponse
    {
        // Typed by a person, so possibly lower-case; issued numbers are not.
        $trackingNumber = strtoupper($trackingNumber);
        $document = SpeedyContext::findTrackable($trackingNumber);

        if (! $document) {
            return response()->json([
                'answer' => "I couldn't find {$trackingNumber}. Check the number on your confirmation email or slip — it looks like SPD-20261016-ABC123.",
                'source' => 'fallback',
            ]);
        }

        $context = SpeedyContext::for($document, CitizenThreadAccess::isVerified(request(), $document));

        return response()->json([
            'answer' => "Found it — {$context['tracking']} is a {$context['type']}, currently {$context['status']}. What would you like to know about it?",
            'source' => 'fallback',
            'context' => $context,
        ]);
    }
}
