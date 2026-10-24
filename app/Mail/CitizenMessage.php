<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the staff member handling a request when the citizen posts a message
 * or question on it — the mirror of StaffMessage, which notifies the citizen.
 */
class CitizenMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentComment $comment,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New citizen message on '.$this->comment->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citizen-message',
            with: [
                'document' => $this->comment->document,
                'recipient' => $this->recipient,
                'body' => $this->comment->body,
                'reviewUrl' => route('track.show', $this->comment->document->tracking_number),
            ],
        );
    }
}
