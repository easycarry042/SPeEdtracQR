<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_is_scoped_to_their_department(): void
    {
        $this->seedRolesAndPermissions();

        $mine = Department::create(['name' => 'Business Permits', 'code' => 'BPLO', 'is_active' => true]);
        $other = Department::create(['name' => 'Treasury', 'code' => 'TRE', 'is_active' => true]);

        $supervisor = User::factory()->create(['department_id' => $mine->id])->assignRole('Supervisor');
        $myStaff = User::factory()->create(['name' => 'Ana Reyes', 'department_id' => $mine->id])->assignRole('staff');
        $otherStaff = User::factory()->create(['name' => 'Ben Cruz', 'department_id' => $other->id])->assignRole('staff');

        // A ticket handled by my department vs one in another department.
        Document::create(['tracking_number' => 'SPD-MINE-1', 'document_type' => 'Business Permit', 'department_id' => $mine->id, 'assigned_to' => $myStaff->id, 'status' => 'in_progress']);
        Document::create(['tracking_number' => 'SPD-OTHER-1', 'document_type' => 'Cedula', 'department_id' => $other->id, 'assigned_to' => $otherStaff->id, 'status' => 'in_progress']);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        // The workload board lists my department's staff, not another department's.
        $response->assertSee('Ana Reyes');
        $response->assertDontSee('Ben Cruz');
        // Department context banner is shown.
        $response->assertSee('Business Permits');
    }
}
