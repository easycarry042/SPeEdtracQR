<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use App\Notifications\DocumentEvent;
use Illuminate\Support\Facades\Notification;

/**
 * Keeps the header bell meaningful for the oversight side: every status move on
 * a citizen request notifies that department's supervisors and the super admins.
 *
 * Hooked to DocumentStatusUpdated — the single event every status path already
 * fires (advance, move back, return, hold, resume, accept, complete) — so no
 * controller can change a stage without oversight hearing about it. The citizen
 * side of the same event is covered by the broadcast to the public tracking page
 * plus the StatusUpdated email.
 */
class NotifyOversightOfStatusChange
{
    public function handle(DocumentStatusUpdated $event): void
    {
        // The caller already pinged oversight with a more specific message.
        if ($event->oversightNotified) {
            return;
        }

        $document = $event->document;

        // Internal dept-to-dept requests run the endorsement chain, which sends
        // its own hop notifications — this would double up.
        if ($document->origin === Document::ORIGIN_INTERNAL) {
            return;
        }

        $recipients = DocumentEvent::departmentTriagers($document->department_id, $event->actor?->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            DocumentEvent::statusChanged($document, $event->actor?->name ?? 'Staff')
        );
    }
}
