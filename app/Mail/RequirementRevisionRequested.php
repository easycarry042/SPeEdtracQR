<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\DocumentRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the citizen when a staff member marks one of their supporting
 * documents as "needs revision" or "rejected", with the staff comment. For a
 * needs-revision item the citizen can re-upload just that document from the
 * tracking page; a rejected item shows the reason for reference.
 */
class RequirementRevisionRequested extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public DocumentRequirement $requirement,
    ) {}

    public function envelope(): Envelope
    {
        $verb = $this->requirement->isRejected() ? 'rejected' : 'needs revision';

        return new Envelope(
            subject: 'A document '.$verb.' — '.$this->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.requirement-revision',
            with: [
                'trackingUrl' => url('/track/'.$this->document->tracking_number),
            ],
        );
    }
}
