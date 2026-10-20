<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Events\DocumentCommentPosted;
use App\Events\DocumentStatusUpdated;
use App\Mail\BookingUpdated;
use App\Models\Booking;
use App\Models\Document;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The counter's side of a borrowing, shared by the Resources page and the
 * Booking calendar so the two can never apply different rules.
 *
 *   approve  — staff grant a number of units; refused if the stock cannot cover it
 *   release  — the items are handed over (they leave the stockroom)
 *   receive  — some or all come back; broken ones go out of service, missing
 *              ones are written off; the loan closes when nothing is outstanding
 *   finish   — a facility is done early
 *   remind   — an overdue borrower is emailed
 *
 * Each returns null on success or the sentence to show the person instead.
 */
class Lending
{
    /**
     * Grant a booking. For equipment, $units is how many staff are lending —
     * a whole number, defaulting to what the citizen asked for.
     */
    public static function approve(Booking $booking, ?int $units = null): ?string
    {
        if ($booking->isCancelled()) {
            return 'This booking was cancelled.';
        }

        if ($booking->isApproved()) {
            return 'This booking is already approved.';
        }

        if ($notReady = self::requestNotReady($booking)) {
            return $notReady;
        }

        return DB::transaction(function () use ($booking, $units): ?string {
            $resource = $booking->resource()->lockForUpdate()->first();
            $units = $resource?->isEquipment() ? ($units ?? $booking->unitsRequested()) : null;

            if ($refusal = self::refusal($booking, $booking->starts_at, $booking->ends_at, $units)) {
                return $refusal;
            }

            $booking->update(['status' => Booking::STATUS_APPROVED, 'quantity' => $units]);

            $what = $units ? number_format($units).' × '.$resource?->name : $resource?->name;
            self::log($booking, "Approved booking for {$what}.");
            self::notifyCitizen($booking, BookingUpdated::OUTCOME_APPROVED);

            return null;
        });
    }

    /**
     * Why a booking cannot be granted for the window, or null if it can. The
     * booking itself is excluded, so it never competes with its own approval.
     *
     * A facility is taken or it is not; equipment is a pile several borrowers
     * share until it runs out. Both are refused rather than warned, so the
     * office never promises the same chairs twice.
     */
    public static function refusal(Booking $booking, CarbonInterface $starts, CarbonInterface $ends, ?int $units = null): ?string
    {
        $resource = $booking->resource;

        if (! $resource) {
            return null;
        }

        if ($resource->isFacility()) {
            return $resource->approvedConflicts($starts, $ends, $booking->id)->isNotEmpty()
                ? "{$resource->name} is already reserved during that time. Choose another time, or reschedule or cancel the other reservation first."
                : null;
        }

        $wanted = $units ?? $booking->units();

        if ($wanted < 1) {
            return 'Enter how many to lend — a whole number, 1 or more.';
        }

        if ($resource->serviceableUnits() < 1) {
            return "Cannot approve — no count has been set for {$resource->name} yet. Set how many the office owns on the Resources page first.";
        }

        $free = $resource->unitsAvailable($starts, $ends, $booking->id);

        if ($wanted > $free) {
            return "Not enough {$resource->name}: this lends {$wanted}, but only {$free} are free for those dates."
                .' Lend fewer, reschedule it, or wait for items on loan to come back.';
        }

        return null;
    }

    /**
     * Move a booking to a new window. An approved one is re-checked against
     * the stock (or the venue's other reservations) for the new dates; one
     * already handed over cannot move. The request's claiming date follows the
     * pickup, so the calendar and the request never show two different days.
     */
    public static function reschedule(Booking $booking, CarbonInterface $starts, CarbonInterface $ends, bool $moveClaimDate = true): ?string
    {
        if ($booking->isReleased()) {
            return 'These items were handed over on '.$booking->released_at->format('M j').', so the pickup date can no longer move.';
        }

        if ($booking->isApproved() && $refusal = self::refusal($booking, $starts, $ends)) {
            return "Can't move it to {$starts->format('M j')}: {$refusal}";
        }

        $booking->update(['starts_at' => $starts, 'ends_at' => $ends]);

        if ($moveClaimDate && $booking->document) {
            $booking->document->update(['claim_date' => $starts->copy()->startOfDay()]);
        }

        self::log($booking, 'Rescheduled '.self::what($booking).' to '.self::window($booking).'.');
        self::notifyCitizen($booking, BookingUpdated::OUTCOME_RESCHEDULED);

        return null;
    }

    /**
     * Staff set or moved a request's claiming date (Review modal, or advancing
     * its stage): carry its booking along. The pickup moves to that day at the
     * same time, and the return date by the same number of days, so the
     * borrowing keeps its length. Null when there is nothing to move.
     */
    public static function followClaimDate(Document $document, CarbonInterface $claimDate): ?string
    {
        $booking = $document->booking;

        // Already collected (or finished): the pickup has happened, so there
        // is nothing left for a claiming date to move.
        if (! $booking || $booking->isCancelled() || $booking->isClosed() || $booking->isReleased()) {
            return null;
        }

        $days = (int) $booking->starts_at->copy()->startOfDay()->diffInDays($claimDate->copy()->startOfDay(), false);

        if ($days === 0) {
            return null;
        }

        return self::reschedule(
            $booking,
            $booking->starts_at->copy()->addDays($days),
            $booking->ends_at->copy()->addDays($days),
            moveClaimDate: false,
        );
    }

    /** "Oct 25 – Oct 29" (or one day's times for a same-day reservation). */
    public static function window(Booking $booking): string
    {
        return $booking->starts_at->isSameDay($booking->ends_at)
            ? $booking->starts_at->format('M j, g:i A').' – '.$booking->ends_at->format('g:i A')
            : $booking->starts_at->format('M j').' – '.$booking->ends_at->format('M j');
    }

    /**
     * Change when a borrowing is due back — usually the borrower asking to
     * keep the chairs longer, sometimes back early. The pickup stays; the
     * extra days are checked against the stock (or the venue's other
     * reservations), and an overdue loan given a new date stops being overdue.
     */
    public static function extend(Booking $booking, CarbonInterface $returnBy): ?string
    {
        if (! $booking->isApproved() || $booking->isClosed()) {
            return 'Only an approved borrowing that is still open can change its return date.';
        }

        $old = $booking->ends_at->copy();
        $ends = $returnBy->copy()->setTimeFrom($old);

        if ($ends->lte($booking->starts_at)) {
            return 'The return date must be after the pickup ('.$booking->starts_at->format('M j').').';
        }

        if ($ends->lt(now()->startOfDay())) {
            return 'The return date cannot be in the past.';
        }

        if ($ends->equalTo($old)) {
            return 'That is already the return date.';
        }

        // Only what is still out needs the extra days.
        $units = $booking->isReleased() ? $booking->unitsOutstanding() : $booking->units();

        if ($ends->gt($old) && $refusal = self::refusal($booking, $booking->starts_at, $ends, $booking->resource?->isEquipment() ? $units : null)) {
            return "Can't keep it until {$ends->format('M j')}: {$refusal}";
        }

        // The claiming date is the pickup, which does not move.
        $booking->update(['ends_at' => $ends, 'overdue_reminded_at' => null]);

        self::log($booking, 'Return date for '.self::what($booking).' changed from '.$old->format('M j').' to '.$ends->format('M j').'.');
        self::notifyCitizen($booking, BookingUpdated::OUTCOME_EXTENDED);

        return null;
    }

    /** The items leave the stockroom with the borrower. */
    public static function release(Booking $booking, User $by): ?string
    {
        if (! $booking->isAwaitingPickup()) {
            return 'Only an approved booking that has not been handed over can be released.';
        }

        $resource = $booking->resource;

        if ($resource?->isEquipment()) {
            // Approval checked the dates; this checks the shelf. An earlier
            // borrower who is late bringing theirs back can leave it short.
            $onHand = $resource->unitsOnHand();

            if ($booking->units() > $onHand) {
                return "Only {$onHand} {$resource->name} are in the stockroom right now — some are still out or overdue."
                    .' Record those returns first, or lend fewer.';
            }
        }

        $document = $booking->document;

        if ($document && ! in_array($document->statusEnum(), [DocumentStatus::Approved, DocumentStatus::Completed], true)) {
            return "The request is still {$document->statusEnum()->label()}. Move it to Approved first (requirements verified), then hand the items over.";
        }

        $booking->update(['released_at' => now(), 'released_by' => $by->id]);
        self::log($booking, 'Handed over '.self::what($booking).'.');

        if ($document) {
            self::markRequestReleased($document, $by);
        }

        return null;
    }

    /**
     * Why staff cannot approve this booking yet, or null. A reservation is
     * part of the request, so it follows the request's own order: the office
     * supervisor assigns it, the staff member accepts it, and only then is
     * the stock promised.
     */
    public static function requestNotReady(Booking $booking): ?string
    {
        $document = $booking->document;

        if (! $document) {
            return null;
        }

        if ($document->statusEnum()->isTerminal()) {
            return "The request is already {$document->statusEnum()->label()}.";
        }

        if ($document->assigned_to === null) {
            return 'Not yet — the office supervisor has to assign this request to a staff member first.';
        }

        if ($document->accepted_at === null) {
            return 'Not yet — '.($document->assignedTo?->name ?? 'the assigned staff member').' has to accept the request first.';
        }

        return null;
    }

    /**
     * Handing the items over is the request's release: an Approved request
     * becomes Completed (what the citizen came for has been given), and the
     * release is stamped as it is for a document claimed at the counter.
     */
    private static function markRequestReleased(Document $document, User $by): void
    {
        $from = $document->statusEnum();

        if ($from === DocumentStatus::Approved) {
            $document->applyStatus(DocumentStatus::Completed);
        }

        $document->claimed_at ??= now();
        $document->released_by ??= $by->id;
        $document->save();

        if ($from === DocumentStatus::Approved) {
            activity()->performedOn($document)->causedBy($by)
                ->withProperties(['from' => $from->value, 'to' => DocumentStatus::Completed->value])
                ->log('Completed: Approved → Completed (items handed over)');

            event(new DocumentStatusUpdated($document, $by));
            event(new DocumentCommentPosted($document->logSystemComment("Completed — items handed over by {$by->name}")));
        }
    }

    /**
     * Record items coming back. $returned is everything that came back
     * (including $damaged, which go out of service); $lost is written off the
     * office's count. Whatever is left stays on loan.
     */
    public static function receive(Booking $booking, int $returned, int $damaged, int $lost, ?string $notes, User $by): ?string
    {
        if (! $booking->isOnLoan()) {
            return 'Only items that were handed over can be returned.';
        }

        $outstanding = $booking->unitsOutstanding();

        if ($returned + $lost < 1) {
            return 'Enter how many came back (or how many are missing).';
        }

        if ($returned + $lost > $outstanding) {
            return "Only {$outstanding} are still out on this loan.";
        }

        if ($damaged > $returned) {
            return 'The damaged ones are part of what came back, so there cannot be more damaged than returned.';
        }

        DB::transaction(function () use ($booking, $returned, $damaged, $lost, $notes, $by, $outstanding): void {
            $resource = $booking->resource()->lockForUpdate()->first();
            $closes = $returned + $lost === $outstanding;

            $booking->update([
                'returned_quantity' => (int) $booking->returned_quantity + $returned,
                'damaged_quantity' => (int) $booking->damaged_quantity + $damaged,
                'lost_quantity' => (int) $booking->lost_quantity + $lost,
                'return_notes' => trim(implode("\n", array_filter([$booking->return_notes, $notes]))) ?: null,
                'returned_at' => $closes ? now() : null,
                'returned_by' => $closes ? $by->id : null,
            ]);

            if ($resource && ($damaged > 0 || $lost > 0)) {
                $resource->update([
                    'damaged_quantity' => (int) $resource->damaged_quantity + $damaged,
                    'total_quantity' => max(0, (int) $resource->total_quantity - $lost),
                ]);
            }

            $parts = array_filter([
                $returned - $damaged > 0 ? ($returned - $damaged).' in good condition' : null,
                $damaged > 0 ? "{$damaged} damaged (now out of service)" : null,
                $lost > 0 ? "{$lost} missing (written off)" : null,
            ]);
            $left = $booking->unitsOutstanding();

            self::log($booking, 'Returned '.$resource?->name.': '.implode(', ', $parts).'.'
                .($left > 0 ? " {$left} still out." : ''));

            if ($closes) {
                self::notifyCitizen($booking, BookingUpdated::OUTCOME_RETURNED);
            }
        });

        return null;
    }

    /** A facility's reservation ends now — the venue is free again. */
    public static function finish(Booking $booking, User $by): ?string
    {
        if (! $booking->isApproved() || $booking->isClosed() || $booking->starts_at->isFuture()) {
            return 'Only a reservation that is under way can be marked done.';
        }

        $booking->update(['returned_at' => now(), 'returned_by' => $by->id]);
        self::log($booking, "Marked {$booking->resource?->name} done.");

        return null;
    }

    /** Cancel a booking. Items already handed over must be returned instead. */
    public static function cancel(Booking $booking, bool $notify = true): ?string
    {
        if ($booking->isOnLoan()) {
            return 'These items have been handed over. Record their return instead of cancelling.';
        }

        $booking->update(['status' => Booking::STATUS_CANCELLED]);
        self::log($booking, "Cancelled booking for {$booking->resource?->name}.");

        if ($notify) {
            self::notifyCitizen($booking, BookingUpdated::OUTCOME_CANCELLED);
        }

        return null;
    }

    /**
     * Email an overdue borrower. Returns false when there is no way to — no
     * email on file, or citizen emails switched off — so the page can say to
     * call instead.
     */
    public static function remind(Booking $booking): bool
    {
        $sent = self::notifyCitizen($booking, BookingUpdated::OUTCOME_OVERDUE);

        $booking->forceFill(['overdue_reminded_at' => now()])->save();
        self::log($booking, $sent
            ? "Reminded the borrower that {$booking->resource?->name} is {$booking->daysOverdue()} day(s) overdue."
            : "{$booking->resource?->name} is {$booking->daysOverdue()} day(s) overdue — no email to remind the borrower.");

        return $sent;
    }

    /**
     * Email the citizen about their booking, honouring the same gate as status
     * emails: the global switch, the bookings switch, an email on file, and
     * the request's own notify_citizen flag.
     */
    public static function notifyCitizen(Booking $booking, string $outcome): bool
    {
        if (! config('tracking.notify_citizen.enabled', true) || ! config('tracking.notify_citizen.bookings', true)) {
            return false;
        }

        $document = $booking->document;

        if (! $document || ! $document->citizen_email || ! ($document->notify_citizen ?? true)) {
            return false;
        }

        Mail::to($document->citizen_email)->send(new BookingUpdated($booking, $outcome));
        activity()->performedOn($document)->log("Emailed BookingUpdated ({$outcome}) to citizen");

        return true;
    }

    /** "10 × Monoblock Chairs", or the facility's name. */
    private static function what(Booking $booking): string
    {
        $name = $booking->resource?->name ?? 'the items';

        return $booking->resource?->isEquipment() ? number_format($booking->units())." × {$name}" : $name;
    }

    /**
     * On the request (its history) and on the resource (the item's own
     * history on the Resources page).
     */
    private static function log(Booking $booking, string $line): void
    {
        if ($booking->document) {
            activity()->performedOn($booking->document)->log($line);
        }

        if ($booking->resource) {
            $tracking = $booking->document?->tracking_number;
            activity()->performedOn($booking->resource)->log($tracking ? "{$line} ({$tracking})" : $line);
        }
    }
}
