<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function routedDocument(): array
    {
        $dept = Department::create(['name' => 'Business Permits Office', 'code' => 'BPLO', 'is_active' => true]);
        $staff = User::factory()->create(['name' => 'Juan Dela Cruz', 'department_id' => $dept->id]);

        $doc = Document::create([
            'tracking_number' => 'SPD-TRK-'.uniqid(),
            'document_type' => 'Business Permit',
            'department_id' => $dept->id,
            'assigned_to' => $staff->id,
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'citizen_contact' => '09171234567',
            'status' => 'in_progress',
        ]);

        return [$dept, $staff, $doc];
    }

    public function test_public_tracking_page_shows_department_thed_and_assignee(): void
    {
        [$dept, $staff, $doc] = $this->routedDocument();

        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('BPLO')
            ->assertSee('Business Permits Office')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('maria@example.com')
            ->assertSee('09171234567');
    }

    /**
     * Regression: the citizen page polls this endpoint every 30 s and writes
     * `current_department` into the "Handled by" field. It used to report the
     * (now removed) current_department relation, which was always null in the
     * manual-assignment model — so every auto-refresh blanked the assigned
     * staff name back to "Not yet assigned" until a manual reload.
     */
    public function test_status_poll_reports_the_assigned_staff_member(): void
    {
        [, $staff, $doc] = $this->routedDocument();

        $this->getJson(route('track.status', $doc->tracking_number))
            ->assertOk()
            ->assertJson([
                'status' => 'in_progress',
                'current_department' => $staff->name,
            ]);
    }

    public function test_status_poll_reports_null_only_when_nobody_is_assigned(): void
    {
        [, , $doc] = $this->routedDocument();
        $doc->update(['assigned_to' => null]);

        $this->getJson(route('track.status', $doc->tracking_number))
            ->assertOk()
            ->assertJson(['current_department' => null]);
    }

    public function test_internal_tracking_page_shows_department_thed_and_assignee(): void
    {
        $this->seedRolesAndPermissions();
        [$dept, $staff, $doc] = $this->routedDocument();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('BPLO')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('09171234567');
    }
}
