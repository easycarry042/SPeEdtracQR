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

        // The form is now a modal on the dashboard; the old create URL redirects there.
        $this->actingAs($this->userWithRole('staff'))->get(route('documents.create'))->assertRedirect(route('dashboard'));

        // Intake-only and system admins cannot create submissions.
        $this->actingAs($this->userWithRole('receiving_staff'))->get(route('documents.create'))->assertForbidden();
        $this->actingAs($this->userWithRole('super_admin'))->get(route('documents.create'))->assertForbidden();
    }

    public function test_analytics_requires_view_reports_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('analytics'))
            ->assertRedirect(route('dashboard'));

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
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_system_admin_routes_require_manage_system_permission(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('Supervisor'))
            ->get(route('admin.audit-log.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.audit-log.index'))
            ->assertOk();
    }
}
