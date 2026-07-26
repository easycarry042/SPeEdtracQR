<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private function courtType(): RequestType
    {
        $this->seedRolesAndPermissions();
        $resource = Resource::create(['name' => 'Covered Court']);

        return RequestType::create([
            'name' => 'Covered Court Reservation',
            'kind' => RequestType::KIND_BOOKING,
            'resource_id' => $resource->id,
            'is_active' => true,
        ]);
    }

    public function test_submitting_a_booking_request_creates_a_pending_booking(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->courtType();
        $starts = Carbon::parse('next monday 10:00');
        $ends = $starts->copy()->addHours(2);

        $this->post(route('public.request.store'), [
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Event Org',
            'citizen_email' => 'org@example.com',
            'consent' => '1',
            'starts_at' => $starts->format('Y-m-d H:i'),
            'ends_at' => $ends->format('Y-m-d H:i'),
        ])->assertOk();

        $booking = Document::firstOrFail()->booking;
        $this->assertNotNull($booking);
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame($type->resource_id, $booking->resource_id);
        $this->assertSame($starts->format('Y-m-d H:i'), $booking->starts_at->format('Y-m-d H:i'));
    }

    public function test_a_conflicting_booking_is_refused(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->courtType();
        $starts = Carbon::parse('next monday 10:00');

        // An existing approved booking occupies 10:00–12:00.
        $existing = Document::create([
            'tracking_number' => 'SPD-BK-EXIST',
            'document_type' => 'Covered Court Reservation',
            'status' => 'pending',
        ]);
        $existing->booking()->create([
            'resource_id' => $type->resource_id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHours(2),
            'status' => Booking::STATUS_APPROVED,
        ]);

        // A new request overlapping 11:00–13:00 must be refused.
        $this->post(route('public.request.store'), [
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Clasher',
            'citizen_email' => 'clash@example.com',
            'consent' => '1',
            'starts_at' => $starts->copy()->addHour()->format('Y-m-d H:i'),
            'ends_at' => $starts->copy()->addHours(3)->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('starts_at');

        // No second document/booking created.
        $this->assertSame(1, Document::count());
        $this->assertSame(1, Booking::count());
    }
}
