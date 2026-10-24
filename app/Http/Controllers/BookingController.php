<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\Lending;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        // The screen's two side panels (Figma: STAFF BOOKING): what is happening
        // today, and what is coming after today. "Upcoming" is independent of
        // the calendar selection — it is the standing answer to "what's next?".
        $upcoming = $bookings
            ->filter(fn (Booking $b) => $b->starts_at->isAfter(now()->endOfDay()))
            ->take(12)
            ->values();

        return view('bookings.index', [
            'byDate' => $byDate,
            'conflictIds' => array_keys($conflictIds),
            'dateMeta' => $dateMeta,
            'defaultDate' => $defaultDate,
            'today' => now()->format('Y-m-d'),
            'upcoming' => $upcoming,
        ]);
    }

    /**
     * Approve a pending booking — refused if the resource cannot cover it.
     * Equipment takes the number of units staff are lending (whole numbers
     * only), which defaults to what the citizen asked for. Same rules as the
     * Resources page (App\Support\Lending).
     */
    public function approve(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'quantity.integer' => 'Enter how many to lend as a whole number.',
        ]);

        if ($refusal = Lending::approve($booking, isset($validated['quantity']) ? (int) $validated['quantity'] : null)) {
            return back()->with('error', $refusal);
        }

        return back()->with('status', 'Booking approved.');
    }

    /** Move a booking to a new window; the request's claiming date moves with it. */
    public function reschedule(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'The end time must be after the start time.',
        ]);

        // Same rules as the request's claiming date: an approved booking is
        // re-checked for the new dates, and the claiming date follows the pickup.
        if ($refusal = Lending::reschedule($booking, Carbon::parse($validated['starts_at']), Carbon::parse($validated['ends_at']))) {
            return back()->with('error', $refusal);
        }

        return back()->with('status', 'Booking rescheduled.');
    }

    /** Cancel a booking (frees the slot; the request itself stays tracked). */
    public function cancel(Booking $booking)
    {
        if ($refusal = Lending::cancel($booking)) {
            return back()->with('error', $refusal);
        }

        return back()->with('status', 'Booking cancelled.');
    }
}
