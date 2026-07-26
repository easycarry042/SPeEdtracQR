<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DocumentComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the citizen when staff post a citizen-facing (public) message on their
 * document. Internal notes never trigger this.
 */
class StaffMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public DocumentComment $comment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New message about your request '.$this->comment->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-message',
            with: [
                'document' => $this->comment->document,
                'trackingUrl' => url('/track/'.$this->comment->document->tracking_number),
            ],
        );
    }
}
