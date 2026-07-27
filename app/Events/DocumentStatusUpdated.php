<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a document's status stage changes (manual advance/revert/return).
 *
 * Broadcasts on the same per-document channel and under the same `.moved` alias
 * the citizen tracker already listens for, so real-time updates keep working
 * without touching the front-end JS.
 */
class DocumentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Document $document,
        public ?User $actor = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('documents.'.$this->document->tracking_number);
    }

    public function broadcastAs(): string
    {
        return 'moved';
    }

    public function broadcastWith(): array
    {
        $stage = $this->document->statusEnum();
        $actor = $this->actor?->name ? explode(' ', $this->actor->name)[0] : 'Staff';

        return [
            'status' => $this->document->status,
            'status_label' => $stage->label(),
            // "Handled by" value for the citizen page — the assigned staff member
            // in the manual model (null → the JS shows "Not yet assigned").
            'current_department' => $this->document->assignedTo?->name,
            'event' => "Status updated to {$stage->label()} by {$actor}",
            'assignee' => $this->document->assignedTo?->name,
            'timestamp' => ($this->document->status_changed_at ?? now())?->format('M d, Y h:i A'),
        ];
    }
}
