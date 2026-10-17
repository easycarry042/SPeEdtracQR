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
 * Queued so one unreachable mailbox cannot abort the hourly SLA sweep.
 */
class SlaBreachMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document
    ) {}

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
