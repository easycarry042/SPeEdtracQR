<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\BookingUpdated;
use App\Models\Booking;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    /**
     * Agenda of upcoming reservations grouped by resource, with overlapping
     * bookings flagged so staff can resolve clashes.
     */
    public function index(): Factory|View
    {
        $bookings = Booking::query()
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->where('ends_at', '>=', now()->startOfDay())
            ->with(['resource', 'document'])
            ->orderBy('starts_at')
            ->get();

        // Flag every booking that overlaps another non-cancelled one on the same
        // resource (either can be the one to reschedule/cancel).
        $conflictIds = [];
        foreach ($bookings->groupBy('resource_id') as $group) {
            foreach ($group as $a) {
                foreach ($group as $b) {
                    if ($a->id !== $b->id && $a->starts_at < $b->ends_at && $a->ends_at > $b->starts_at) {
                        $conflictIds[$a->id] = true;
                    }
                }
            }
        }

        // Group by calendar day (of the start) for the day panel, and build a
        // lightweight per-day map the JS calendar uses to mark days that have
        // reservations (and whether any of them conflict).
        $byDate = $bookings->groupBy(fn (Booking $b) => $b->starts_at->format('Y-m-d'));

        $dateMeta = $byDate->map(fn ($dayBookings, $date) => [
            'count' => $dayBookings->count(),
            'conflict' => $dayBookings->contains(fn (Booking $b) => in_array($b->id, array_keys($conflictIds), true)),
        ]);

        // Default the calendar to today, or the first day that has a booking.
        $defaultDate = $byDate->has(now()->format('Y-m-d'))
            ? now()->format('Y-m-d')
            : ($byDate->keys()->first() ?? now()->format('Y-m-d'));

        return view('bookings.index', [
            'byDate' => $byDate,
            'conflictIds' => array_keys($conflictIds),
            'dateMeta' => $dateMeta,
            'defaultDate' => $defaultDate,
        ]);
    }

    /** Approve a pending booking — refused if it clashes with an approved one. */
    public function approve(Booking $booking)
    {
        if ($booking->resource && $booking->resource->approvedConflicts($booking->starts_at, $booking->ends_at, $booking->id)->isNotEmpty()) {
            return back()->with('error', 'Cannot approve — that time clashes with an already-approved booking. Reschedule or cancel one first.');
        }

        $booking->update(['status' => Booking::STATUS_APPROVED]);
        activity()->performedOn($booking->document)->log("Approved booking for {$booking->resource?->name}.");
        $this->notifyCitizen($booking, BookingUpdated::OUTCOME_APPROVED);

        return back()->with('status', 'Booking approved.');
    }

    /** Move a booking to a new window (conflict-checked against approved ones). */
    public function reschedule(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'The end time must be after the start time.',
        ]);

        $starts = Carbon::parse($validated['starts_at']);
        $ends = Carbon::parse($validated['ends_at']);

        if ($booking->resource && $booking->resource->approvedConflicts($starts, $ends, $booking->id)->isNotEmpty()) {
            return back()->with('error', 'That new time clashes with an approved booking. Pick another slot.');
        }

        $booking->update(['starts_at' => $starts, 'ends_at' => $ends]);
        activity()->performedOn($booking->document)->log("Rescheduled booking for {$booking->resource?->name}.");
        $this->notifyCitizen($booking, BookingUpdated::OUTCOME_RESCHEDULED);

        return back()->with('status', 'Booking rescheduled.');
    }

    /** Cancel a booking (frees the slot; the request itself stays tracked). */
    public function cancel(Booking $booking)
    {
        $booking->update(['status' => Booking::STATUS_CANCELLED]);
        activity()->performedOn($booking->document)->log("Cancelled booking for {$booking->resource?->name}.");
        $this->notifyCitizen($booking, BookingUpdated::OUTCOME_CANCELLED);

        return back()->with('status', 'Booking cancelled.');
    }

    /**
     * Email the citizen about a booking outcome, honouring the same gate as
     * document status emails: the global kill switch, the bookings toggle, a
     * present citizen_email, and the document's per-ticket notify_citizen flag.
     */
    private function notifyCitizen(Booking $booking, string $outcome): void
    {
        if (! config('tracking.notify_citizen.enabled', true) || ! config('tracking.notify_citizen.bookings', true)) {
            return;
        }

        $document = $booking->document;

        if (! $document || ! $document->citizen_email || ! ($document->notify_citizen ?? true)) {
            return;
        }

        Mail::to($document->citizen_email)->send(new BookingUpdated($booking, $outcome));
        activity()->performedOn($document)->log("Emailed BookingUpdated ({$outcome}) to citizen");
    }
}
