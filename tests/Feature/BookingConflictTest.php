<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function bookedResource(string $status = Booking::STATUS_APPROVED): Resource
    {
        $resource = Resource::create(['name' => 'Covered Court']);
        $doc = Document::create([
            'tracking_number' => 'SPD-BK-'.strtoupper(uniqid()),
            'document_type' => 'Covered Court Reservation',
            'status' => 'pending',
        ]);
        $resource->bookings()->create([
            'document_id' => $doc->id,
            'starts_at' => Carbon::parse('2026-08-01 10:00'),
            'ends_at' => Carbon::parse('2026-08-01 12:00'),
            'status' => $status,
        ]);

        return $resource;
    }

    public function test_overlapping_window_is_a_conflict(): void
    {
        $resource = $this->bookedResource();

        $this->assertCount(1, $resource->conflicts(
            Carbon::parse('2026-08-01 11:00'),
            Carbon::parse('2026-08-01 13:00'),
        ));
    }

    public function test_adjacent_window_is_not_a_conflict(): void
    {
        $resource = $this->bookedResource();

        // Starts exactly when the existing booking ends — no overlap.
        $this->assertCount(0, $resource->conflicts(
            Carbon::parse('2026-08-01 12:00'),
            Carbon::parse('2026-08-01 14:00'),
        ));
    }

    public function test_cancelled_bookings_never_conflict(): void
    {
        $resource = $this->bookedResource(Booking::STATUS_CANCELLED);

        $this->assertCount(0, $resource->conflicts(
            Carbon::parse('2026-08-01 10:30'),
            Carbon::parse('2026-08-01 11:30'),
        ));
    }
}
