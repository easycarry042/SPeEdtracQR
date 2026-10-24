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

    public function test_public_tracking_page_shows_department_but_never_the_assignee(): void
    {
        [$dept, $staff, $doc] = $this->routedDocument();

        // Staff stay anonymous to the public: the office, never the person.
        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('BPLO')
            ->assertSee('Business Permits Office')
            ->assertDontSee('Juan Dela Cruz')
            ->assertSee('Maria Santos');
    }

    public function test_public_tracking_page_masks_the_requesters_contact_details(): void
    {
        // Anyone with the tracking number can open this page, and the email and
        // phone are what the message gate asks for — printing them in full
        // handed a stranger the answer.
        [$dept, $staff, $doc] = $this->routedDocument();

        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('m•••@example.com')
            ->assertSee('•••4567')
            ->assertDontSee('maria@example.com')
            ->assertDontSee('09171234567');
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
