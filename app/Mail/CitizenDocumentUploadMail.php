<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the assigned staff member when a citizen uploads files. Queued so a
 * dead SMTP server cannot fail the citizen's upload request.
 */
class CitizenDocumentUploadMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public int $fileCount,
        public ?string $citizenNote = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Citizen uploaded documents — '.$this->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.citizen-upload',
        );
    }
}
