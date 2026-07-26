<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the citizen when staff act on their booking request — approved,
 * rescheduled, or cancelled. Gated by config/tracking.php + the per-ticket
 * notify_citizen flag (see BookingController).
 */
class BookingUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const OUTCOME_APPROVED = 'approved';

    public const OUTCOME_RESCHEDULED = 'rescheduled';

    public const OUTCOME_CANCELLED = 'cancelled';

    public function __construct(public Booking $booking, public string $outcome) {}

    public function envelope(): Envelope
    {
        $resource = $this->booking->resource?->name ?? 'your reservation';
        $tracking = $this->booking->document?->tracking_number;

        return new Envelope(
            subject: match ($this->outcome) {
                self::OUTCOME_APPROVED => "Your booking for {$resource} is confirmed — {$tracking}",
                self::OUTCOME_RESCHEDULED => "Your booking for {$resource} was rescheduled — {$tracking}",
                self::OUTCOME_CANCELLED => "Your booking for {$resource} was cancelled — {$tracking}",
                default => "Update on your booking — {$tracking}",
            },
        );
    }

    public function content(): Content
    {
        $document = $this->booking->document;

        return new Content(
            view: 'emails.booking-updated',
            with: [
                'outcome' => $this->outcome,
                'resourceName' => $this->booking->resource?->name ?? 'Reservation',
                'startsAt' => $this->booking->starts_at,
                'endsAt' => $this->booking->ends_at,
                'citizenName' => $document?->citizen_name,
                'trackingUrl' => $document ? url('/track/'.$document->tracking_number) : url('/'),
            ],
        );
    }
}
