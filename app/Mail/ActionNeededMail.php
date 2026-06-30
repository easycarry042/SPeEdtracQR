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
 * Sent to the citizen when their document is placed On Hold with blocked_by =
 * 'citizen' — i.e. the office is waiting on them. States what's needed
 * (hold_reason) and links to the tracking page so they can respond / upload.
 * Gated by config/tracking.php (notify_citizen + the on_hold stage toggle).
 */
class ActionNeededMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action needed on your request {$this->document->tracking_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.action-needed',
            with: [
                'reason' => $this->document->hold_reason,
                'holdUntil' => $this->document->hold_until,
                'trackingUrl' => url('/track/'.$this->document->tracking_number),
            ],
        );
    }
}
