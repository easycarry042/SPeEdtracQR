<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the citizen when their document moves to a new status stage
 * (gated by config/tracking.php + the per-ticket notify_citizen flag).
 */
class StatusUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        $stage = $this->document->statusEnum()->label();

        return new Envelope(
            subject: "Update on your request {$this->document->tracking_number}: {$stage}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.status-updated',
            with: [
                'stageLabel' => $this->document->statusEnum()->label(),
                'trackingUrl' => url('/track/'.$this->document->tracking_number),
            ],
        );
    }
}
