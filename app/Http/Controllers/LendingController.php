<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Support\Lending;
use App\Support\RequestReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The lending desk on the Resources page: approve a borrowing with the number
 * of units, hand the items over, take them back, chase the overdue ones. The
 * rules live in App\Support\Lending, shared with the Booking calendar.
 */
class LendingController extends Controller
{
    public function approve(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'quantity' => [$booking->resource?->isEquipment() ? 'required' : 'nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'quantity.required' => 'Enter how many to lend.',
            'quantity.integer' => 'Enter how many to lend as a whole number.',
            'quantity.min' => 'Lend at least 1.',
        ]);

        return $this->outcome(
            Lending::approve($booking, isset($validated['quantity']) ? (int) $validated['quantity'] : null),
            'Approved — '.$this->what($booking->fresh()).' reserved for '.$this->borrower($booking).'.',
            $booking,
        );
    }

    public function release(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->outcome(
            Lending::release($booking, $request->user()),
            'Handed over '.$this->what($booking).' to '.$this->borrower($booking).'.',
        );
    }

    /**
     * Items coming back. A facility has no counts: this marks it done.
     */
    public function receive(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        if ($booking->resource?->isFacility()) {
            return $this->outcome(Lending::finish($booking, $request->user()), "{$booking->resource->name} is free again.");
        }

        $validated = $request->validate([
            'returned' => ['required', 'integer', 'min:0', 'max:100000'],
            'damaged' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lost' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            '*.integer' => 'Counts are whole numbers.',
            '*.min' => 'Counts cannot be negative.',
        ]);

        $refusal = Lending::receive(
            $booking,
            (int) $validated['returned'],
            (int) ($validated['damaged'] ?? 0),
            (int) ($validated['lost'] ?? 0),
            $validated['notes'] ?? null,
            $request->user(),
        );

        $booking->refresh();

        return $this->outcome($refusal, $booking->isClosed()
            ? "Loan closed — everything from {$this->borrower($booking)} is accounted for."
            : "Return recorded. {$booking->unitsOutstanding()} still out with {$this->borrower($booking)}.");
    }

    /** A new return date — the borrower keeping the items longer, or back early. */
    public function extend(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'return_by' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'return_by.required' => 'Pick the new return date.',
            'return_by.after_or_equal' => 'The return date cannot be in the past.',
        ]);

        $refusal = Lending::extend($booking, Carbon::parse($validated['return_by']));

        return $this->outcome($refusal, "{$this->borrower($booking)} now returns {$this->what($booking)} by "
            .$booking->fresh()->ends_at->format('D, M j').'.');
    }

    public function remind(Booking $booking): RedirectResponse|JsonResponse
    {
        if (! $booking->isOverdue()) {
            return back()->with('error', 'Only an overdue loan can be chased.');
        }

        $contact = $booking->document?->citizen_contact;

        return Lending::remind($booking)
            ? back()->with('status', "Reminder emailed to {$this->borrower($booking)}.")
            : back()->with('error', "There is no email to send a reminder to — call {$this->borrower($booking)}"
                .($contact ? " on {$contact}" : '').' instead.');
    }

    public function cancel(Booking $booking): RedirectResponse|JsonResponse
    {
        return $this->outcome(Lending::cancel($booking), 'Booking cancelled — anything it held is free again.');
    }

    /**
     * Back to the page, or JSON for the Review modal — which also gets the
     * reservation as it now stands, to redraw its card in place.
     */
    private function outcome(?string $refusal, string $done, ?Booking $booking = null): RedirectResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            return $refusal
                ? response()->json(['message' => $refusal], 422)
                : response()->json([
                    'message' => $done,
                    'booking' => $booking?->document ? RequestReview::bookingPayload($booking->document->fresh()) : null,
                ]);
        }

        return $refusal ? back()->with('error', $refusal) : back()->with('status', $done);
    }

    private function what(Booking $booking): string
    {
        $name = $booking->resource?->name ?? 'the items';

        return $booking->resource?->isEquipment() ? number_format($booking->units()).' × '.$name : $name;
    }

    private function borrower(Booking $booking): string
    {
        return $booking->document?->citizen_name ?: 'the borrower';
    }
}
