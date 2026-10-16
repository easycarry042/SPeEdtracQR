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
        //
        // Scoped to the <header> rather than the whole page: both phrases are
        // legitimate doormat links in the footer, so a page-wide assertion was
        // measuring the wrong element and failed the moment one was added.
        $content = $this->get('/')->assertOk()->getContent();

        $header = substr($content, (int) strpos($content, '<header'), (int) strpos($content, '</header>') - (int) strpos($content, '<header'));

        $this->assertStringNotContainsString('Submit a Request', $header);
        $this->assertStringNotContainsString('Staff Sign In', $header);
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

<<<<<<< HEAD
    public function test_how_it_works_has_no_doodle_backdrop(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('How It Works')
            ->assertDontSee('doodle-pattern.jpg', false);
=======
    public function test_how_it_works_walks_through_the_four_tracking_stages(): void
    {
        // The stages are the spine of the page: a missing one leaves a citizen
        // without the step that tells them when to come collect the document.
        $this->get('/')
            ->assertOk()
            ->assertSee('HOW IT WORKS')
            ->assertSee('Tracking Flow')
            ->assertSeeInOrder([
                'Submit Request',
                'Document Processing',
                'Track Progress',
                'Claim Document',
            ]);
    }

    public function test_what_we_do_introduces_the_three_services(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('What We Do')
            ->assertSeeInOrder([
                'Citizen Tracking',
                'QR-Code Verification',
                'Smart Chatbot Assistance',
            ]);
    }

    public function test_key_features_lists_every_tool_with_its_icon(): void
    {
        // Each tile's glyph is its own exported file; a missing one renders an
        // empty frosted square rather than failing loudly in the browser.
        foreach (['file-text', 'shield', 'search', 'people', 'clock'] as $glyph) {
            $this->assertFileExists(public_path("images/landing/feat-{$glyph}.svg"));
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('FEATURES')
            ->assertSeeInOrder([
                'Document Routing',
                'Secure QR Identification',
                'Accountability Audit',
                'Role-Based Access',
                'Real-Time Status',
            ]);
    }

    public function test_security_section_names_the_four_safeguards(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('SECURITY')
            ->assertSee('Data Safe')
            ->assertSeeInOrder([
                'Law-Compliant Service',
                'Staff Logs',
                'Private Tracking',
                'Offline Backup',
            ]);
    }

    public function test_faq_answers_are_rendered_server_side(): void
    {
        // The panels are Alpine accordions, but the copy ships in the HTML so a
        // reader without JavaScript — and a search crawler — still gets it.
        $this->get('/')
            ->assertOk()
            ->assertSee('Frequently Asked')
            ->assertSee('How can I track the status of my document or request?')
            ->assertSee('Data Privacy Act of 2012 (RA 10173)')
            ->assertSee('Ease of Doing Business Act (RA 11032)');
    }

    public function test_every_landing_image_referenced_by_the_page_exists(): void
    {
        // Catches a renamed or un-exported asset before it reaches a reviewer as
        // a broken image on the municipality's front page.
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#images/landing/[\w.-]+\.(?:svg|png|jpg)#', $html, $matches);

        $this->assertNotEmpty($matches[0], 'The landing page referenced no images at all.');

        foreach (array_unique($matches[0]) as $path) {
            $this->assertFileExists(public_path($path));
        }
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
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
