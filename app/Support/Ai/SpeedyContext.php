<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\CitizenThread;
use App\Support\CitizenThreadAccess;
use Illuminate\Http\Request;

/**
 * The request Speedy is currently talking about, as the chat panel needs it.
 *
 * Built in one place for both ways a request becomes the topic: the page the
 * citizen is on (tracking page, receipt) and a tracking number typed or
 * scanned into the chat. Only what the public tracking page already shows.
 */
final class SpeedyContext
{
    /**
     * Internal office-to-office requests are never a citizen's topic: every
     * public page treats them as not found, and so does Speedy.
     */
    public static function findTrackable(string $trackingNumber): ?Document
    {
        return Document::where('tracking_number', trim($trackingNumber))
            ->where('origin', '!=', Document::ORIGIN_INTERNAL)
            ->with('assignedTo')
            ->first();
    }

    /**
     * @param  bool  $verified  whether this browser has confirmed the citizen
     *                          for this request (CitizenThreadAccess) — only
     *                          then does Speedy show its office messages
     * @return array{tracking: string, type: string, status: string, url: string, suggestions: list<string>, verified: bool, unread: int, can_verify: bool}
     */
    public static function for(Document $document, bool $verified = false): array
    {
        $type = $document->document_type ?: 'document';

        // The questions the tracking page does NOT answer at a glance.
        $suggestions = $document->statusEnum() === DocumentStatus::Completed
            ? ["How do I claim my {$type}?", "Is my {$type} authentic?", 'What do I bring for pickup?']
            : ["When will my {$type} be ready?", "What are the requirements for {$type}?", 'What happens next?'];

        return [
            'tracking' => $document->tracking_number,
            'type' => $type,
            'status' => $document->statusEnum()->label(),
            'url' => $document->citizenTrackingUrl(),
            'suggestions' => $suggestions,
            'verified' => $verified,
            'unread' => $verified ? CitizenThread::unreadCount($document) : 0,
            'can_verify' => CitizenThreadAccess::canBeVerified($document),
        ];
    }

    /**
     * Unread office messages across every request this browser has confirmed,
     * for the badge on Speedy's launcher.
     *
     * @return array{total: int, threads: list<array{tracking: string, type: string, unread: int}>}
     */
    public static function inbox(Request $request): array
    {
        $numbers = CitizenThreadAccess::verifiedTrackingNumbers($request);

        if ($numbers === []) {
            return ['total' => 0, 'threads' => []];
        }

        $threads = Document::whereIn('tracking_number', $numbers)
            ->where('origin', '!=', Document::ORIGIN_INTERNAL)
            ->get()
            ->map(fn (Document $document): array => [
                'tracking' => $document->tracking_number,
                'type' => $document->document_type ?: 'request',
                'unread' => CitizenThread::unreadCount($document),
            ])
            ->values()
            ->all();

        return ['total' => array_sum(array_column($threads, 'unread')), 'threads' => $threads];
    }
}
