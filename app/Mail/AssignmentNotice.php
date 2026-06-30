<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a staff member when an admin assigns them a document to advance.
 */
class AssignmentNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public User $assignee,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New document assigned to you — '.$this->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assignment-notice',
            with: [
                'documentUrl' => url('/track/'.$this->document->tracking_number),
            ],
        );
    }
}
