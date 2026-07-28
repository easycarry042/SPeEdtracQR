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
