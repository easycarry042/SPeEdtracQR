<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    /** The page's <header> element, so header assertions don't match the body. */
    private function header(string $content): string
    {
        $start = (int) strpos($content, '<header');

        return substr($content, $start, (int) strpos($content, '</header>') - $start);
    }

    public function test_guests_see_the_public_landing_page_at_root(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Your requests,');
    }

    public function test_landing_hero_has_no_tracking_search_bar(): void
    {
        // Tracking lives on the Track page; the hero links there instead.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Enter your tracking number to see its status here.')
            ->assertDontSee('trackLookup', false);
    }

    public function test_header_leads_with_the_city_seal_and_the_wordmark(): void
    {
        // The owner comes first: a visitor should know this is the City's own
        // service before they read the product name.
        $header = $this->header($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('images/staff/seal-2.png', $header);
        $this->assertStringContainsString('alt="City of San Pedro seal"', $header);
        $this->assertStringContainsString('City of San Pedro, Laguna', $header);
        $this->assertFileExists(public_path('images/staff/seal-2.png'));
    }

    public function test_header_gives_staff_a_quiet_way_in_and_citizens_no_competing_button(): void
    {
        // Staff are the minority audience: one outlined link in the header,
        // never a filled button competing with the citizen actions in the hero.
        $header = $this->header($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('login').'"', $header);
        $this->assertStringContainsString('Staff sign in', $header);
        $this->assertStringNotContainsString('Submit a request', $header);
    }

    public function test_hero_offers_both_citizen_actions_directly(): void
    {
        // Straight to the form and the Track page, not to a portal page that
        // only offers the same two choices again. "Click here" is gone: a link
        // names where it goes.
        $content = $this->get('/')->assertOk()->getContent();
        $start = (int) strpos($content, 'id="top"');
        $hero = substr($content, $start, (int) strpos($content, 'id="how-it-works"') - $start);

        $this->assertStringContainsString('href="'.route('public.request.create').'"', $hero);
        $this->assertStringContainsString('href="'.route('citizen.track').'"', $hero);
        $this->assertStringContainsString('Submit a request', $hero);
        $this->assertStringContainsString('Track a request', $hero);
        $this->assertStringNotContainsString('Click here', $content);
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

    public function test_the_landing_page_carries_no_doodle_backdrop(): void
    {
        // The redesign took the arrow doodle off the landing page; it is plain
        // white paper now, like the rest of the portal.
        $this->get('/')
            ->assertOk()
            ->assertSee('How it works')
            ->assertDontSee('doodle-pattern.jpg', false);
    }

    public function test_how_it_works_walks_through_the_four_tracking_stages(): void
    {
        // The stages are the spine of the page: a missing one leaves a citizen
        // without the step that tells them when to come collect the document.
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'Submit a request',
                'City Hall processes it',
                'Track its progress',
                'Claim your document',
            ])
            // The label a citizen actually sees on their tracking page.
            ->assertSee('Ready for pickup');
    }

    public function test_features_are_one_section_with_no_repeats(): void
    {
        // "What We Do", "Key Features" and "Security" described the same ideas
        // three times over; they are one grid of six now.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('What We Do')
            ->assertDontSee('Key Features')
            ->assertDontSee('features-person.png', false)
            ->assertSeeInOrder([
                'Real-time status',
                'QR codes only City Hall can read',
                'Routed office to office',
                'Processing-time targets',
                'Your details stay private',
                'Every action on record',
            ]);
    }

    public function test_every_section_the_nav_links_to_exists_and_clears_the_sticky_header(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        foreach (['how-it-works', 'features', 'faq'] as $id) {
            $this->assertStringContainsString('href="#'.$id.'"', $this->header($content));
            $this->assertMatchesRegularExpression('/id="'.$id.'" class="[^"]*scroll-mt-/', $content);
        }
    }

    public function test_faq_answers_are_rendered_server_side_and_each_is_distinct(): void
    {
        // Native <details>: the copy ships in the HTML, so a reader without
        // JavaScript (and a search crawler) still gets it.
        $content = $this->get('/')
            ->assertOk()
            ->assertSee('Frequently asked')
            ->assertSee('How can I track the status of my document or request?')
            ->assertSee('Data Privacy Act of 2012 (RA&nbsp;10173)', false)
            ->assertSee('Ease of Doing Business Act (RA&nbsp;11032)', false)
            ->getContent();

        // Two questions once shared one copy-pasted answer.
        preg_match_all('#</summary>\s*<p[^>]*>(.*?)</p>#s', $content, $answers);
        $this->assertCount(6, $answers[1]);
        $this->assertSame($answers[1], array_values(array_unique($answers[1])));
    }

    public function test_every_landing_image_referenced_by_the_page_exists(): void
    {
        // Catches a renamed or un-exported asset before it reaches a reviewer as
        // a broken image on the municipality's front page.
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#images/(?:landing|staff)/[\w.-]+\.(?:svg|png|jpg)#', $html, $matches);

        $this->assertNotEmpty($matches[0], 'The landing page referenced no images at all.');

        foreach (array_unique($matches[0]) as $path) {
            $this->assertFileExists(public_path($path));
        }
    }

    public function test_landing_hero_uses_the_city_hall_photo_as_its_backdrop(): void
    {
        $this->assertFileExists(public_path('images/landing/hero-cityhall.jpg'));
        $this->assertFileExists(public_path('images/landing/hero-gradient.svg'));

        $response = $this->get('/')->assertOk();

        $response->assertDontSee('Municipality photo');
        $response->assertSee('images/landing/hero-cityhall.jpg', false);

        // The exported vignette feathers the photo's edges into the paper the
        // copy sits on. Without it the photo ends on a hard vertical seam.
        $response->assertSee('images/landing/hero-gradient.svg', false);
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
