<?php

namespace App\Mail;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlaWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public Department $department
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SLA Warning: Document Approaching Deadline — Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sla-warning',
        );
    }
}