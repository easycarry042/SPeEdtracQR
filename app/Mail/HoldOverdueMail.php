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
 * A held document has run past its own hold deadline. Sent to the assignee and
 * copied to the handling department's Supervisor, because a hold suppresses
 * every other SLA signal — this mail is the only thing that surfaces the stall.
 *
 * Queued so one unreachable mailbox cannot abort the daily sweep.
 */
class HoldOverdueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        /** True when this copy goes to a Supervisor rather than the assignee. */
        public bool $isEscalation = false,
    ) {}

    public function envelope(): Envelope
    {
        $days = $this->document->holdOverdueDays();
        $subject = $this->isEscalation
            ? "Stalled hold needs a decision: {$this->document->tracking_number} ({$days}d past its hold date)"
            : "Hold expired — resume or re-date {$this->document->tracking_number}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hold-overdue',
        );
    }
}
