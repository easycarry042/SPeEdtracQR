<?php

namespace App\Events;

use App\Models\DocumentComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new collaboration post. Broadcasts to the per-document PRIVATE staff channel
 * always, and additionally to the PUBLIC document channel only when the post is
 * citizen-facing. Internal notes therefore never reach the public channel a
 * citizen could subscribe to.
 */
class DocumentCommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DocumentComment $comment) {}

    public function broadcastOn(): array
    {
        $document = $this->comment->document;

        $channels = [new PrivateChannel('documents.'.$this->comment->document_id.'.staff')];

        if ($this->comment->isPublic() && $document) {
            $channels[] = new Channel('documents.'.$document->tracking_number);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'comment';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'author' => $this->comment->authorLabel(),
            'author_type' => $this->comment->author_type,
            'body' => $this->comment->body,
            'visibility' => $this->comment->visibility,
            'parent_id' => $this->comment->parent_id,
            'timestamp' => $this->comment->created_at?->format('M d, Y h:i A'),
        ];
    }
}
