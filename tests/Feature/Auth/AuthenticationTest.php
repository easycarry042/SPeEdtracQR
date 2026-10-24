<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        // Role-less users take the default staff landing: the Requests page.
        $response->assertRedirect(route('staff.dashboard', absolute: false));
    }

    public function test_staff_land_on_requests_even_after_a_signed_out_visit_to_a_profile(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        // A signed-out hit on a profile page stores it as the intended URL. It
        // must not hijack the landing page — staff belong on their Requests
        // workspace, not on a read-only profile.
        $this->get("/staff/{$staff->id}")->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect(route('staff.dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_login_still_honours_a_genuine_deep_link(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        // Deep links that are real work destinations (e.g. a document from a
        // notification email) still survive the login round-trip.
        $this->get('/documents/create')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect('/documents/create');
    }

    public function test_a_page_left_behind_by_another_account_is_not_followed_if_off_limits(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->assertFalse($supervisor->can('manage bookings'));

        // A staff session lapsed on Booking, leaving /bookings as the intended
        // URL. A supervisor signing in next must land on their own dashboard,
        // not on a page they are refused (403).
        $this->get('/bookings')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $supervisor->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_page_left_behind_is_still_followed_when_the_new_account_may_open_it(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $this->assertTrue($staff->can('manage bookings'));

        $this->get('/bookings')->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertRedirect('/bookings');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
