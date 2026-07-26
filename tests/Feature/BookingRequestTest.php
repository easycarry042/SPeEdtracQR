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

    private function equipmentType(): RequestType
    {
        $this->seedRolesAndPermissions();
        $resource = Resource::create(['name' => 'Monoblock Chairs']);

        return RequestType::create([
            'name' => 'Chairs Borrowing',
            'kind' => RequestType::KIND_EQUIPMENT,
            'resource_id' => $resource->id,
            'is_active' => true,
        ]);
    }

    public function test_submitting_a_booking_request_creates_a_pending_booking(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->courtType();
        $date = Carbon::parse('next monday');

        $this->post(route('public.request.store'), [
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Event Org',
            'citizen_email' => 'org@example.com',
            'consent' => '1',
            'booking_date' => $date->toDateString(),
            'start_time' => '16:00',
            'end_time' => '19:00',
        ])->assertOk();

        $booking = Document::firstOrFail()->booking;
        $this->assertNotNull($booking);
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame($type->resource_id, $booking->resource_id);
        $this->assertSame($date->copy()->setTime(16, 0)->format('Y-m-d H:i'), $booking->starts_at->format('Y-m-d H:i'));
        $this->assertSame($date->copy()->setTime(19, 0)->format('Y-m-d H:i'), $booking->ends_at->format('Y-m-d H:i'));
        $this->assertNull($booking->quantity);
    }

    public function test_a_conflicting_booking_is_refused(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->courtType();
        $date = Carbon::parse('next monday');
        $starts = $date->copy()->setTime(10, 0);

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
            'booking_date' => $date->toDateString(),
            'start_time' => '11:00',
            'end_time' => '13:00',
        ])->assertSessionHasErrors('booking_date');

        // No second document/booking created.
        $this->assertSame(1, Document::count());
        $this->assertSame(1, Booking::count());
    }

    public function test_submitting_an_equipment_request_records_the_quantity(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->equipmentType();
        $needed = Carbon::parse('next monday');

        $this->post(route('public.request.store'), [
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Fiesta Committee',
            'citizen_email' => 'fiesta@example.com',
            'consent' => '1',
            'quantity' => 50,
            'needed_date' => $needed->toDateString(),
            'return_date' => $needed->copy()->addDay()->toDateString(),
        ])->assertOk();

        $document = Document::firstOrFail();
        $booking = $document->booking;
        $this->assertNotNull($booking);
        $this->assertSame($type->resource_id, $booking->resource_id);
        // Quantity lives on the document (equipment: how many of the resource).
        $this->assertSame(50, $document->quantity);
    }

    public function test_submitting_a_service_request_records_quantity_and_due_date(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seedRolesAndPermissions();
        RequestType::create(['name' => 'Lei Making', 'kind' => RequestType::KIND_SERVICE, 'is_active' => true]);
        $needed = Carbon::parse('next monday');

        $this->post(route('public.request.store'), [
            'document_type' => 'Lei Making',
            'citizen_name' => 'Inauguration Committee',
            'citizen_email' => 'inaug@example.com',
            'consent' => '1',
            'quantity' => 10,
            'needed_by' => $needed->toDateString(),
        ])->assertOk();

        $document = Document::firstOrFail();
        $this->assertSame(10, $document->quantity);
        $this->assertSame($needed->toDateString(), $document->needed_by->toDateString());
        // Service requests reserve no resource — no booking row.
        $this->assertNull($document->booking);
        $this->assertSame(0, Booking::count());
    }

    public function test_equipment_requests_do_not_conflict_with_each_other(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->equipmentType();
        $needed = Carbon::parse('next monday');

        $payload = [
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Org A',
            'citizen_email' => 'a@example.com',
            'consent' => '1',
            'quantity' => 30,
            'needed_date' => $needed->toDateString(),
            'return_date' => $needed->toDateString(),
        ];

        $this->post(route('public.request.store'), $payload)->assertOk();
        // Same window, different requester — equipment is shared stock, so it is accepted.
        $this->post(route('public.request.store'), array_merge($payload, ['citizen_email' => 'b@example.com']))->assertOk();

        $this->assertSame(2, Booking::count());
    }

    public function test_a_facility_request_requirement_is_snapshotted(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $type = $this->courtType();
        $type->requirements()->create(['label' => 'Letter of Request', 'is_mandatory' => true, 'sort_order' => 0]);
        $date = Carbon::parse('next monday');

        $this->post(route('public.request.store'), [
            'document_type' => 'Covered Court Reservation',
            'citizen_name' => 'Event Org',
            'citizen_email' => 'org@example.com',
            'consent' => '1',
            'booking_date' => $date->toDateString(),
            'start_time' => '16:00',
            'end_time' => '19:00',
        ])->assertOk();

        $document = Document::firstOrFail();
        $this->assertSame(1, $document->requirements()->count());
        $this->assertSame('Letter of Request', $document->requirements()->first()->label);
    }
}
