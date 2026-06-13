<?php

namespace App\Events;

use App\Models\Document;
use App\Models\DocumentScan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentMoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Document $document,
        public DocumentScan $scan,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('documents.' . $this->document->tracking_number);
    }

    public function broadcastAs(): string
    {
        return 'moved';
    }

    public function broadcastWith(): array
    {
        // Ensure relations are loaded
        $scan = $this->scan->load(['department', 'user']);

        // Build timeline entry (matching the citizen view logic)
        $firstName = explode(' ', $scan->user->name ?? 'System')[0];
        $event = $scan->action === 'in'
            ? "Received by {$firstName}"
            : "Handed over by {$firstName}";

        return [
            'status' => $this->document->status,
            'current_department' => $this->document->currentDepartment?->name ?? null,
            'event' => $event . ' (' . ($scan->department->name ?? 'Unknown Department') . ')',
            'action' => $scan->action,
            'timestamp' => optional($scan->scanned_at)->format('M d, Y h:i A'),
        ];
    }
}
