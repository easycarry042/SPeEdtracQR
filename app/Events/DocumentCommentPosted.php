<?php

namespace App\Events;

use App\Models\DocumentComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new collaboration post, on the per-document PRIVATE staff channel.
 *
 * It used to go to the request's PUBLIC channel as well whenever the post was
 * citizen-facing — body and staff name included — and anyone who knew the
 * tracking number could subscribe to that. The citizen side now gets a
 * content-free ping instead (CitizenThreadChanged) and fetches the thread
 * through the confirmed-citizen endpoint.
 */
class DocumentCommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DocumentComment $comment) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('documents.'.$this->comment->document_id.'.staff')];
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
