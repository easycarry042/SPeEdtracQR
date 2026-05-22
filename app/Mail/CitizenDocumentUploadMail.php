<?php

namespace App\Mail;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitizenDocumentUploadMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public Department $department,
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
