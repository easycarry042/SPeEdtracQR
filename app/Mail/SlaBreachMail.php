<?php

namespace App\Mail;

use App\Models\Department;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlaBreachMail extends Mailable
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
            subject: 'SLA Alert: Document Pending Too Long',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sla-breach',
        );
    }
}
