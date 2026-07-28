<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_department(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.departments.store'), ['name' => 'Municipal Budget Office', 'code' => 'bo'])
            ->assertRedirect(route('admin.departments.index'));

        // Codes are normalized to uppercase for consistent badges/routing chips.
        $this->assertDatabaseHas('departments', ['name' => 'Municipal Budget Office', 'code' => 'BO', 'is_active' => true]);
    }

    public function test_department_name_and_code_must_be_unique(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        Department::factory()->create(['name' => 'Tourism Office', 'code' => 'TRSM']);

        $this->actingAs($admin)
            ->post(route('admin.departments.store'), ['name' => 'Tourism Office', 'code' => 'TRSM'])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_super_admin_can_update_and_toggle_a_department(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $department = Department::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

        $this->actingAs($admin)
            ->put(route('admin.departments.update', $department), ['name' => 'New Name', 'code' => 'NEW'])
            ->assertRedirect(route('admin.departments.index'));

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name', 'code' => 'NEW']);

        $this->actingAs($admin)
            ->patch(route('admin.departments.toggle-active', $department))
            ->assertRedirect();

        $this->assertFalse($department->fresh()->is_active);
    }

    public function test_supervisor_cannot_manage_departments(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        // Unauthorized admin-area hits return a 403 Access Denied page.
        $this->actingAs($supervisor)->get(route('admin.departments.index'))->assertForbidden();
        $this->actingAs($supervisor)
            ->post(route('admin.departments.store'), ['name' => 'Rogue Office', 'code' => 'NOPE'])
            ->assertForbidden();
        $this->assertDatabaseMissing('departments', ['code' => 'NOPE']);
    }

    public function test_admin_can_assign_a_department_when_creating_and_updating_a_user(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $department = Department::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dept Supervisor',
            'email' => 'dept.supervisor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Supervisor',
            'department_id' => $department->id,
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'dept.supervisor@example.com')->firstOrFail();
        $this->assertSame($department->id, $user->department_id);
        $this->assertTrue($user->department->is($department));

        // Clearing the select detaches the user from any office.
        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'Supervisor',
            'department_id' => null,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh()->department_id);
    }

    public function test_inactive_departments_are_not_offered_on_user_forms(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $active = Department::factory()->create(['name' => 'Active Office']);
        Department::factory()->inactive()->create(['name' => 'Ghost Office']);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSee('Ghost Office');
    }
}
