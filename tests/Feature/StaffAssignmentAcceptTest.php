<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssignmentAcceptTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_staff_can_accept_pending_assignment(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $document = Document::create([
            'tracking_number' => 'SPD-20260701-TEST01',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'pending',
            'assigned_to' => $staff->id,
            'assigned_by' => $supervisor->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue(RequestReview::needsTriage($document->fresh()));

        $this->actingAs($staff)
            ->postJson(route('documents.assignment.accept', $document))
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');

        $document->refresh();
        $this->assertSame('in_progress', $document->status);
        $this->assertNotNull($document->accepted_at);
        $this->assertFalse(RequestReview::needsTriage($document));
    }

    public function test_non_assignee_cannot_accept(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');
        $other = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-20260701-TEST02',
            'document_type' => 'Cedula',
            'citizen_name' => 'John Citizen',
            'status' => 'pending',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($other)
            ->postJson(route('documents.assignment.accept', $document))
            ->assertForbidden();
    }
}
