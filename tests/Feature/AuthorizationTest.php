<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, ?Department $dept = null): User
    {
        $dept ??= Department::create(['name' => 'Reception '.$role, 'sla_hours' => 48]);

        return User::factory()->create(['department_id' => $dept->id])->assignRole($role);
    }

    public function test_create_document_capability_by_role(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))->get(route('documents.create'))->assertOk();
        $this->actingAs($this->userWithRole('department_admin'))->get(route('documents.create'))->assertOk();

        $this->actingAs($this->userWithRole('receiving_staff'))->get(route('documents.create'))->assertForbidden();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('documents.create'))->assertForbidden();
    }

    public function test_scan_capability_by_role(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('receiving_staff'))
            ->postJson('/api/scan', ['tracking_number' => 'NOPE', 'department_id' => 1, 'action' => 'in'])
            ->assertStatus(404);

        $this->actingAs($this->userWithRole('super_admin'))
            ->postJson('/api/scan', ['tracking_number' => 'NOPE', 'department_id' => 1, 'action' => 'in'])
            ->assertForbidden();
    }

    public function test_staff_cannot_scan_for_another_department(): void
    {
        $this->seedRolesAndPermissions();

        $deptA = Department::create(['name' => 'Dept A', 'sla_hours' => 48]);
        $deptB = Department::create(['name' => 'Dept B', 'sla_hours' => 48]);
        $staff = $this->userWithRole('staff', $deptA);

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-AUTH01',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $deptB->id,
            'created_by' => $staff->id,
        ]);
        $document->syncRouteSteps([$deptB->id]);

        $this->actingAs($staff)
            ->postJson('/api/scan', [
                'tracking_number' => $document->tracking_number,
                'department_id' => $deptB->id,
                'action' => 'in',
            ])
            ->assertForbidden();
    }

    public function test_analytics_requires_view_reports_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('analytics'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->userWithRole('department_admin'))
            ->get(route('analytics'))
            ->assertOk();
    }

    public function test_user_management_requires_manage_users_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.users.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->userWithRole('department_admin'))
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_system_admin_routes_require_manage_system_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('department_admin'))
            ->get(route('admin.departments.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.departments.index'))
            ->assertOk();
    }
}
