<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One in-app (database) notification type for the header bell, with named
 * constructors per event. Email already has its own dedicated Mailables —
 * this class only feeds the bell.
 */
class DocumentEvent extends Notification
{
    public function __construct(
        public string $event,
        public string $title,
        public string $body,
        public string $url,
        public string $tracking,
    ) {}

    /** A citizen or staff member filed a new request — supervisors triage it. */
    public static function newTicket(Document $document): self
    {
        return new self(
            event: 'new_ticket',
            title: 'New request to review',
            body: trim(($document->document_type ?? 'Document').' — '.($document->citizen_name ?? 'no name'), ' —'),
            url: route('track.show', $document->tracking_number),
            tracking: $document->tracking_number,
        );
    }

    /** A document was assigned to this staff member. */
    public static function assigned(Document $document, string $byName): self
    {
        return new self(
            event: 'assigned',
            title: 'Assigned to you',
            body: ($document->document_type ?? 'Document')." — assigned by {$byName}. Accept it on your Requests page.",
            url: route('staff.dashboard'),
            tracking: $document->tracking_number,
        );
    }

    /** Staff declined an assignment — the request is back in the queue. */
    public static function declined(Document $document, string $byName, ?string $reason): self
    {
        return new self(
            event: 'declined',
            title: 'Assignment declined',
            body: "{$byName} declined ".($document->document_type ?? 'a document').($reason ? " — {$reason}" : '.'),
            url: route('track.show', $document->tracking_number),
            tracking: $document->tracking_number,
        );
    }

    /** Everyone who triages/assigns (Supervisors + super_admin), minus the actor. */
    public static function supervisors(?int $excludeUserId = null): Collection
    {
        return User::permission('assign documents')
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tracking' => $this->tracking,
        ];
    }
}
