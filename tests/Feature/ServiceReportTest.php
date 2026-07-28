<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceReportTest extends TestCase
{
    use RefreshDatabase;

    private function reporter(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    public function test_staff_without_view_reports_cannot_open_the_report(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('reports.services'))->assertForbidden();
    }

    public function test_report_renders_request_volume_and_booking_utilisation(): void
    {
        $resource = Resource::create(['name' => 'Covered Court']);
        RequestType::create(['name' => 'Court Reservation', 'kind' => RequestType::KIND_BOOKING, 'resource_id' => $resource->id]);
        RequestType::create(['name' => 'Business Permit', 'kind' => RequestType::KIND_DOCUMENT]);

        // Two Business Permit documents, one Court Reservation with an approved
        // upcoming booking and one pending booking.
        Document::create(['tracking_number' => 'SPD-1', 'document_type' => 'Business Permit', 'status' => 'pending']);
        Document::create(['tracking_number' => 'SPD-2', 'document_type' => 'Business Permit', 'status' => 'pending']);

        $bookingDoc = Document::create(['tracking_number' => 'SPD-3', 'document_type' => 'Court Reservation', 'status' => 'pending']);
        $resource->bookings()->create([
            'document_id' => $bookingDoc->id,
            'starts_at' => Carbon::now()->addDays(3),
            'ends_at' => Carbon::now()->addDays(3)->addHours(2),
            'status' => Booking::STATUS_APPROVED,
        ]);
        $resource->bookings()->create([
            'document_id' => $bookingDoc->id,
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(5)->addHours(2),
            'status' => Booking::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->reporter())->get(route('reports.services'));

        $response->assertOk()
            ->assertViewIs('reports.services')
            ->assertSee('Business Permit')
            ->assertSee('Covered Court')
            ->assertSee('Services report');

        $volume = $response->viewData('volumeByType');
        $this->assertSame(2, $volume->firstWhere('name', 'Business Permit')['total']);

        $bookings = $response->viewData('bookingsByResource');
        $court = $bookings->firstWhere('name', 'Covered Court');
        $this->assertSame(1, $court['upcoming']);
        $this->assertSame(1, $court['pending']);
        $this->assertSame(2, $court['total']);
    }

    public function test_requirement_completion_rate_is_computed(): void
    {
        $type = RequestType::create(['name' => 'Business Permit', 'kind' => RequestType::KIND_DOCUMENT]);
        $req = $type->requirements()->create(['label' => 'Barangay Clearance', 'is_mandatory' => true]);

        $doc = Document::create(['tracking_number' => 'SPD-9', 'document_type' => 'Business Permit', 'status' => 'pending']);

        // One mandatory requirement submitted (has a file), not yet verified.
        $doc->requirements()->create([
            'request_type_requirement_id' => $req->id,
            'label' => 'Barangay Clearance',
            'is_mandatory' => true,
            'uploaded_file_path' => 'document-requirements/x.pdf',
        ]);
        // A second mandatory requirement with nothing submitted.
        $doc->requirements()->create([
            'request_type_requirement_id' => $req->id,
            'label' => 'Barangay Clearance (copy)',
            'is_mandatory' => true,
            'uploaded_file_path' => null,
        ]);

        $response = $this->actingAs($this->reporter())->get(route('reports.services'));

        $stats = $response->viewData('requirementStats');
        $this->assertSame(2, $stats['mandatory_total']);
        $this->assertSame(1, $stats['submitted']);
        $this->assertSame(50, $stats['submitted_pct']);
        $this->assertSame(0, $stats['verified_pct']);
    }
}
