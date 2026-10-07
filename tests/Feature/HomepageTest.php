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

    public function test_landing_hero_has_no_tracking_search_bar(): void
    {
        // Tracking now lives in the citizen portal, not on the landing page.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Enter your tracking number to see its status here.')
            ->assertDontSee('trackLookup', false);
    }

    public function test_landing_header_has_no_call_to_action_buttons(): void
    {
        // The header is branding only — the hero carries the primary actions.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Submit a Request')
            ->assertDontSee('Staff Sign In');
    }

    public function test_hero_leads_with_one_citizen_button_and_a_staff_text_link(): void
    {
        // Staff are the minority audience: they get a sentence, not a button
        // competing with the citizen call to action.
        $this->get('/')
            ->assertOk()
            ->assertSee('Citizen Portal')
            ->assertSee('Are you a municipal staff?')
            ->assertSee('Click here')
            ->assertDontSee('Staff Login');
    }

    public function test_wordmark_splits_zain_and_nunito_across_its_two_halves(): void
    {
        // "SPeED" is Zain (font-display), "TraQR" is Nunito (font-sans) — the
        // span must opt back out because the lockup sits inside heading type.
        $content = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/font-display[^>]*>\s*SPeED\s*<\/span>\s*<span class="font-sans[^>]*>\s*TraQR/',
            $content
        );
    }

    public function test_how_it_works_is_papered_with_the_arrow_doodle(): void
    {
        $this->assertFileExists(public_path('images/landing/doodle-pattern.jpg'));

        $this->get('/')
            ->assertOk()
            ->assertSee('How It Works')
            ->assertSee('images/landing/doodle-pattern.jpg', false);
    }

    public function test_landing_hero_uses_the_city_hall_photo_as_its_backdrop(): void
    {
        $this->assertFileExists(public_path('images/landing/hero-cityhall.jpg'));
        $this->assertFileExists(public_path('images/landing/hero-gradient.svg'));

        $response = $this->get('/')->assertOk();

        // No placeholder card any more: the photo is the backdrop.
        $response->assertDontSee('Municipality photo');
        $response->assertSee('images/landing/hero-cityhall.jpg', false);

        // The exported vignette feathers the photo's edges into the paper the
        // copy sits on. Without it the photo ends on a hard vertical seam.
        $response->assertSee('images/landing/hero-gradient.svg', false);
    }

    public function test_nav_pill_sits_at_the_top_and_scrolls_away(): void
    {
        // It is absolute, not fixed: the design draws it as a capsule laid over
        // the hero, not a bar that follows the page down over the content.
        $this->get('/')
            ->assertOk()
            ->assertSee('absolute inset-x-0 top-[4px]', false)
            ->assertSeeInOrder(['Home', 'Features', 'How It Works', 'Security', 'FAQ']);
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
