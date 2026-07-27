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

    /**
     * A super admin flagged an overdue document for the responsible party's
     * attention — a nudge, not a reassignment.
     */
    public static function nudge(Document $document, string $byName, ?string $note = null): self
    {
        $label = $document->document_type ?? 'A document';

        return new self(
            event: 'nudge',
            title: 'Flagged for your attention',
            body: "{$label} ({$document->tracking_number}) is overdue. {$byName} asked you to follow up.".($note ? " Note: {$note}" : ''),
            url: route('track.show', $document->tracking_number),
            tracking: $document->tracking_number,
        );
    }

    /** A citizen re-uploaded a document that was returned for revision. */
    public static function revisionResubmitted(Document $document, string $requirementLabel): self
    {
        return new self(
            event: 'revision_resubmitted',
            title: 'Revised document uploaded',
            body: 'The citizen re-uploaded "'.$requirementLabel.'" for '.$document->tracking_number.' — ready for re-review.',
            url: route('track.show', $document->tracking_number),
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

    /** An internal request arrived at this department's hop — its supervisors must act. */
    public static function internalHopArrived(Document $document, string $action): self
    {
        return new self(
            event: 'internal_hop',
            title: 'Internal request awaiting your office',
            body: ($document->purpose ?? $document->document_type)." — {$action}.",
            url: route('requests.show', $document),
            tracking: $document->tracking_number,
        );
    }

    /** The chain moved (approved/denied/returned/completed) — tell the filing supervisor. */
    public static function internalOutcome(Document $document, string $summary): self
    {
        return new self(
            event: 'internal_outcome',
            title: 'Internal request update',
            body: ($document->purpose ?? $document->document_type)." — {$summary}",
            url: route('requests.show', $document),
            tracking: $document->tracking_number,
        );
    }

    /** Supervisors of one department (the ones who can act on its hops). */
    public static function departmentSupervisors(int $departmentId, ?int $excludeUserId = null): Collection
    {
        return User::permission('act on internal requests')
            ->where('department_id', $departmentId)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();
    }

    /** Everyone who triages/assigns (Supervisors + super_admin), minus the actor. */
    public static function supervisors(?int $excludeUserId = null): Collection
    {
        return User::permission('assign documents')
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();
    }

    /**
     * Who triages a citizen ticket routed to a department: that department's
     * assigning Supervisors plus all super admins (who see every department). No
     * department → every triager. Excludes the actor when given.
     */
    public static function departmentTriagers(?int $departmentId, ?int $excludeUserId = null): Collection
    {
        if (! $departmentId) {
            return self::supervisors($excludeUserId);
        }

        $deptSupervisors = User::permission('assign documents')
            ->where('department_id', $departmentId)
            ->get();

        $superAdmins = User::permission('manage system')->get();

        return $deptSupervisors->merge($superAdmins)
            ->unique('id')
            ->when($excludeUserId !== null, fn (Collection $c) => $c->reject(fn (User $u) => $u->id === $excludeUserId))
            ->values();
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
