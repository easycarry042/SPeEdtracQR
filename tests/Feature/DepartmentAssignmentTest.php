<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(?Department $dept = null): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-ASG-'.uniqid(),
            'document_type' => 'Business Permit',
            'department_id' => $dept?->id,
            'status' => 'pending',
        ]);
    }

    public function test_supervisor_can_assign_staff_in_their_own_department(): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::create(['name' => 'BPLO', 'code' => 'BPLO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $doc = $this->ticket($dept);

        $this->actingAs($supervisor)
            ->postJson(route('documents.assign-approve', $doc), ['assigned_to' => $staff->id])
            ->assertOk();

        $this->assertSame($staff->id, $doc->fresh()->assigned_to);
    }

    public function test_supervisor_cannot_assign_staff_from_another_department(): void
    {
        $this->seedRolesAndPermissions();
        $bplo = Department::create(['name' => 'BPLO', 'code' => 'BPLO', 'is_active' => true]);
        $treasury = Department::create(['name' => 'Treasury', 'code' => 'TRE', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $bplo->id])->assignRole('Supervisor');
        $otherStaff = User::factory()->create(['department_id' => $treasury->id])->assignRole('staff');
        $doc = $this->ticket($bplo);

        $this->actingAs($supervisor)
            ->postJson(route('documents.assign-approve', $doc), ['assigned_to' => $otherStaff->id])
            ->assertStatus(422);

        $this->assertNull($doc->fresh()->assigned_to);
    }

    public function test_super_admin_can_assign_across_departments(): void
    {
        $this->seedRolesAndPermissions();
        $treasury = Department::create(['name' => 'Treasury', 'code' => 'TRE', 'is_active' => true]);
        $admin = User::factory()->create(['department_id' => null])->assignRole('super_admin');
        $staff = User::factory()->create(['department_id' => $treasury->id])->assignRole('staff');
        $doc = $this->ticket($treasury);

        $this->actingAs($admin)
            ->postJson(route('documents.assign-approve', $doc), ['assigned_to' => $staff->id])
            ->assertOk();

        $this->assertSame($staff->id, $doc->fresh()->assigned_to);
    }
}
