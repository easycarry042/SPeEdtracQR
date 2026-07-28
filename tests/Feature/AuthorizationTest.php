<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_create_document_capability_by_role(): void
    {
        $this->seedRolesAndPermissions();

        // Only guests (the public request form) create submissions. Staff process
        // and manage assigned requests — they cannot create. System admins can't either.
        $this->actingAs($this->userWithRole('staff'))->get(route('documents.create'))->assertForbidden();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('documents.create'))->assertForbidden();
    }

    public function test_analytics_requires_view_reports_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('analytics'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Supervisor'))
            ->get(route('analytics'))
            ->assertOk();
    }

    public function test_user_management_requires_manage_users_permission(): void
    {
        $this->seedRolesAndPermissions();

        // Supervisor manages documents, not users — only super_admin manages users.
        $this->actingAs($this->userWithRole('Supervisor'))
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_system_admin_routes_require_manage_system_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('Supervisor'))
            ->get(route('admin.audit-log.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.audit-log.index'))
            ->assertOk();
    }

    public function test_unauthorized_access_renders_the_access_denied_page(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Access denied');
    }

    public function test_guest_is_redirected_to_login_from_a_protected_page(): void
    {
        $this->seedRolesAndPermissions();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_direct_url_access_to_another_roles_area_is_blocked(): void
    {
        $this->seedRolesAndPermissions();

        // A staff member typing an admin URL straight into the bar is refused,
        // regardless of navigation path (history, bookmark, etc.).
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('bookings.index'))
            ->assertOk(); // staff DO manage bookings — sanity check the gate is scoped
    }
}
