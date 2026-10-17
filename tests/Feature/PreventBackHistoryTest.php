<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreventBackHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_are_not_cacheable(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $response = $this->actingAs($user)->get('/home');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_logout_clears_site_data_so_cached_pages_cannot_return(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertStringContainsString('cache', (string) $response->headers->get('Clear-Site-Data'));
        $this->assertStringContainsString('cookies', (string) $response->headers->get('Clear-Site-Data'));
    }

    /**
     * Back from a citizen page can land a still-signed-in user on /login. They
     * must go to their OWN workspace — not the supervisor dashboard, which is
     * Laravel's default target and bounces plain staff to their profile.
     */
    public function test_signed_in_staff_reaching_login_go_to_their_own_workspace(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $this->actingAs($user)->get(route('login'))->assertRedirect('/home');
        $this->actingAs($user)->get('/home')->assertRedirect(route('staff.dashboard'));
    }

    public function test_signed_in_users_reaching_the_landing_page_are_sent_to_their_workspace(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $this->actingAs($user)->get(route('welcome'))->assertRedirect(route('home'));
    }

    /** Back after logout re-requests the page (no-store); the server must refuse it. */
    public function test_staff_pages_are_refused_once_the_session_is_gone(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $this->actingAs($user)->get(route('staff.dashboard'))->assertOk();

        $this->post(route('logout'));
        $this->assertGuest();

        $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    /** Standalone public pages must carry the bfcache reload guard like the layouts do. */
    public function test_public_request_page_includes_the_bfcache_guard(): void
    {
        $this->seedRolesAndPermissions();

        $this->get(route('public.request.create'))
            ->assertOk()
            ->assertSee('event.persisted', false);
    }

    public function test_guest_pages_are_also_no_store_to_defeat_bfcache(): void
    {
        // The public login/landing pages must never be restored from the
        // browser's back/forward cache — otherwise Back after login could strand
        // a signed-in user on a stale guest page. So guests get no-store too.
        $response = $this->get(route('login'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
