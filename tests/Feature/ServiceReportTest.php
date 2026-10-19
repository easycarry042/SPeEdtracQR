<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * What the Services report used to show — request volume by service, booking
 * use per resource, the requirement checklist — now on a supervisor's Reports
 * page, for their own office. /reports/services leads there.
 */
class ServiceReportTest extends TestCase
{
    use RefreshDatabase;

    private Department $office;

    private function supervisor(): User
    {
        $this->seedRolesAndPermissions();
        $this->office = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);

        return User::factory()->create(['department_id' => $this->office->id])->assignRole('Supervisor');
    }

    public function test_staff_without_view_reports_cannot_open_reports(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('reports.services'))->assertForbidden();
    }

    public function test_the_old_services_report_leads_to_reports(): void
    {
        $this->actingAs($this->supervisor())->get(route('reports.services'))->assertRedirect(route('reports.index'));
    }

    public function test_reports_show_request_volume_and_booking_use(): void
    {
        $supervisor = $this->supervisor();
        $resource = Resource::create(['name' => 'Covered Court']);
        RequestType::create(['name' => 'Court Reservation', 'kind' => RequestType::KIND_BOOKING, 'resource_id' => $resource->id, 'department_id' => $this->office->id]);
        RequestType::create(['name' => 'Business Permit', 'kind' => RequestType::KIND_DOCUMENT, 'department_id' => $this->office->id]);

        Document::create(['tracking_number' => 'SPD-1', 'document_type' => 'Business Permit', 'status' => 'pending', 'department_id' => $this->office->id]);
        Document::create(['tracking_number' => 'SPD-2', 'document_type' => 'Business Permit', 'status' => 'pending', 'department_id' => $this->office->id]);

        // One Court Reservation with an approved upcoming booking and a pending one.
        $bookingDoc = Document::create(['tracking_number' => 'SPD-3', 'document_type' => 'Court Reservation', 'status' => 'pending', 'department_id' => $this->office->id]);
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

        $response = $this->actingAs($supervisor)->get(route('reports.index'));

        $response->assertOk()
            ->assertViewIs('reports.index')
            ->assertSee('General Services Office · Reports')
            ->assertSee('Bookings by Resource')
            ->assertSee('Covered Court');

        $this->assertSame(2, $response->viewData('typeBreakdown')->firstWhere('label', 'Business Permit')['value']);

        $court = $response->viewData('bookings')->firstWhere('name', 'Covered Court');
        $this->assertSame(1, $court['upcoming']);
        $this->assertSame(1, $court['pending']);
        $this->assertSame(2, $court['total']);
    }

    public function test_requirement_completion_rate_is_computed(): void
    {
        $supervisor = $this->supervisor();
        $type = RequestType::create(['name' => 'Business Permit', 'kind' => RequestType::KIND_DOCUMENT, 'department_id' => $this->office->id]);
        $req = $type->requirements()->create(['label' => 'Barangay Clearance', 'is_mandatory' => true]);

        $doc = Document::create(['tracking_number' => 'SPD-9', 'document_type' => 'Business Permit', 'status' => 'pending', 'department_id' => $this->office->id]);

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

        $stats = $this->actingAs($supervisor)->get(route('reports.index'))->viewData('requirementStats');

        $this->assertSame(2, $stats['mandatory_total']);
        $this->assertSame(1, $stats['submitted']);
        $this->assertSame(50, $stats['submitted_pct']);
        $this->assertSame(0, $stats['verified_pct']);
    }
}
