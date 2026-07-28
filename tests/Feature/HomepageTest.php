<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_landing_page_at_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Citizen Portal');
    }

    public function test_authenticated_users_are_redirected_off_the_public_landing(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        // Signed-in users never see the guest landing page — they go to their
        // workspace, so the Back button can't strand them on the public site.
        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('home'));
    }

    public function test_home_dispatches_a_super_admin_to_the_command_center(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_home_requires_authentication(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
