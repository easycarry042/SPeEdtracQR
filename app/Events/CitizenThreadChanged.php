<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "This request's conversation changed" — on the request's PUBLIC channel,
 * with nothing in it but who spoke.
 *
 * Anyone who knows a tracking number can listen on that channel, so it must
 * not carry what was said. It used to: DocumentCommentPosted broadcast every
 * citizen-visible message there, body and staff name included, which let a
 * stranger with the number read the conversation live while the page itself
 * asked them to confirm who they were. A confirmed browser hears this and
 * fetches the thread through the gated endpoint.
 */
class CitizenThreadChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Document $document, public string $from) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('documents.'.$this->document->tracking_number)];
    }

    public function broadcastAs(): string
    {
        return 'thread';
    }

    /** @return array{from: string} */
    public function broadcastWith(): array
    {
        return ['from' => $this->from];
    }
}
