<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_super_admin_sees_the_analytics_command_center(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('City of San Pedro · Analytics');
    }

    public function test_command_center_accepts_filter_params(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard', ['range' => 90]))
            ->assertOk();
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard', ['range' => 999]))
            ->assertSessionHasErrors('range');
    }

    public function test_non_super_admin_is_redirected_away_from_the_command_center(): void
    {
        $this->seedRolesAndPermissions();

        // EnsureHasPermission redirects users lacking "manage system" to their
        // own dashboard rather than throwing a 403.
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('dashboard'));
    }
}
