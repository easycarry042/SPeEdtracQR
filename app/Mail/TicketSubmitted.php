<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Sent to the citizen right after they submit an online ticket: their tracking
 * number, a link to the public tracking page, and the QR code (if generated).
 */
class TicketSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Document $document) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your request — '.$this->document->tracking_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-submitted',
            with: [
                'trackingUrl' => url('/track/'.$this->document->tracking_number),
            ],
        );
    }

    public function attachments(): array
    {
        $path = $this->document->qr_code_path;

        if ($path && Storage::disk('public')->exists($path)) {
            return [
                Attachment::fromStorageDisk('public', $path)
                    ->as('tracking-qr.png')
                    ->withMime('image/png'),
            ];
        }

        return [];
    }
}
