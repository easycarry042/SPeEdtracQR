<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCockpitRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_cockpit_and_keeps_held_documents(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');

        $inProgress = Document::create([
            'tracking_number' => 'SPD-20260701-INPROG',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $held = Document::create([
            'tracking_number' => 'SPD-20260701-ONHOLD',
            'document_type' => 'Cedula',
            'citizen_name' => 'John Citizen',
            'status' => 'on_hold',
            'status_before_hold' => 'in_review',
            'blocked_by' => 'citizen',
            'hold_reason' => 'Waiting on clearance',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $res = $this->actingAs($staff)->get(route('staff.dashboard'));

        $res->assertOk();
        // Flow passed to the Alpine config (label present)
        $res->assertSee('In Review', false);
        // Both docs listed — held one must NOT be filtered out
        $res->assertSee($inProgress->tracking_number, false);
        $res->assertSee($held->tracking_number, false);
        // New cockpit payload fields serialized into the page
        $res->assertSee('status_before_hold', false);
        $res->assertSee('hold_reason', false);
    }

    public function test_status_set_accepts_a_return_reason(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');

        $doc = Document::create([
            'tracking_number' => 'SPD-20260701-RETURN',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'in_review',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.set', $doc), [
                'expected_status' => 'in_review',
                'status' => 'returned',
                'reason' => 'Missing barangay clearance',
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertSame('returned', $doc->status);
        $this->assertSame('Missing barangay clearance', $doc->remarks);
    }
}
