<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\BookingUpdated;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Staff scheduling: while reviewing an assigned request, set the calendar date
 * the request is served on (site visit, court reservation, equipment pickup…).
 *
 * A date reserves a resource for the whole day, so the "no double-booking" rule
 * is a plain overlap check on that resource: if any other non-cancelled booking
 * already covers the day, the save is refused and the staff member is told which
 * request holds it. Two requests may share a date only on different resources.
 */
class DocumentScheduleController extends Controller
{
    public function store(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->canBeAdvancedBy(auth()->user()), 403);

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
        ], [
            'scheduled_date.after_or_equal' => 'Pick today or a later date.',
            'resource_id.required' => 'Choose what is being reserved for this date.',
        ]);

        $resource = Resource::findOrFail($validated['resource_id']);
        [$starts, $ends] = self::dayWindow($validated['scheduled_date']);

        // The request's own booking is excluded — re-saving the same date on the
        // same request must not collide with itself.
        $booking = $document->booking;
        $clash = $resource->conflicts($starts, $ends, $booking?->id)->first();

        if ($clash) {
            return response()->json([
                'message' => 'That date is already taken.',
                'errors' => [
                    'scheduled_date' => [sprintf(
                        '%s is already booked for %s (%s). Pick another date.',
                        $starts->format('F j, Y'),
                        $resource->name,
                        $clash->document?->tracking_number ?? 'another request',
                    )],
                ],
            ], 422);
        }

        if ($booking) {
            $booking->update([
                'resource_id' => $resource->id,
                'starts_at' => $starts,
                'ends_at' => $ends,
                // A staff member setting the date IS the confirmation, so the slot
                // immediately blocks other requests.
                'status' => Booking::STATUS_APPROVED,
            ]);
        } else {
            $booking = $document->booking()->create([
                'resource_id' => $resource->id,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'status' => Booking::STATUS_APPROVED,
            ]);
        }

        activity()->performedOn($document)->log(
            "Scheduled {$resource->name} for ".$starts->format('M j, Y')
        );

        $this->notifyCitizen($document, $booking);

        return response()->json([
            'status' => $document->status,
            'schedule_date' => $starts->toDateString(),
            'schedule_label' => $starts->format('M j, Y').' · '.$resource->name,
            'resource_id' => $resource->id,
        ]);
    }

    /**
     * Dates already reserved on a resource, so the picker can grey them out
     * before the staff member submits (the server check above stays the
     * authority — this is only a courtesy).
     */
    public function bookedDates(Request $request, Resource $resource): JsonResponse
    {
        $ignoreDocumentId = (int) $request->query('ignore_document', 0);

        $dates = $resource->bookings()
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->where('ends_at', '>=', now()->startOfDay())
            ->when($ignoreDocumentId, fn ($q) => $q->where('document_id', '!=', $ignoreDocumentId))
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            ->flatMap(function (Booking $b): array {
                $days = [];
                // A booking can span days; every covered day is unavailable.
                for ($day = $b->starts_at->copy()->startOfDay(); $day <= $b->ends_at; $day->addDay()) {
                    $days[] = $day->toDateString();
                }

                return $days;
            })
            ->unique()
            ->values();

        return response()->json(['dates' => $dates]);
    }

    /**
     * A scheduled date reserves the whole working day — offices book the court
     * or the truck by the day, not by the hour.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function dayWindow(string $date): array
    {
        $starts = Carbon::parse($date)->startOfDay();

        return [$starts, $starts->copy()->endOfDay()];
    }

    /** Same gate as every other citizen email: kill switch, address, per-ticket flag. */
    private function notifyCitizen(Document $document, Booking $booking): void
    {
        if (! config('tracking.notify_citizen.enabled', true) || ! config('tracking.notify_citizen.bookings', true)) {
            return;
        }

        if (! $document->citizen_email || ! ($document->notify_citizen ?? true)) {
            return;
        }

        Mail::to($document->citizen_email)->send(
            new BookingUpdated($booking->fresh('resource'), BookingUpdated::OUTCOME_RESCHEDULED)
        );
        activity()->performedOn($document)->log('Emailed BookingUpdated (rescheduled) to citizen');
    }
}
