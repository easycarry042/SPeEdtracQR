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

    public function test_landing_hero_uses_the_municipality_photo_as_its_backdrop(): void
    {
        $response = $this->get('/')->assertOk();

        // No placeholder card any more: either the photo is the backdrop, or the
        // gradient stands in for it.
        $response->assertDontSee('Municipality photo');

        foreach ($this->municipalityPhotos() as $photo) {
            $response->assertSee("images/{$photo}", false);
        }

        // With or without a photo the hero keeps its light background, so the
        // headline stays readable either way.
        $response->assertSee('from-[#eef4f0] via-[#f1f6f3] to-[#dfeee6]', false);
    }

    public function test_multiple_photos_cross_fade_as_a_slideshow(): void
    {
        $photos = $this->municipalityPhotos();

        if (count($photos) < 2) {
            $this->markTestSkipped('Slideshow needs at least two hero photos on disk.');
        }

        $response = $this->get('/')->assertOk();

        // One staggered slide per photo, driven by a single shared keyframe.
        $response->assertSee('@keyframes heroSlideFade', false);
        $this->assertSame(
            count($photos),
            substr_count($response->getContent(), 'class="hero-slide')
        );
        $response->assertSee('prefers-reduced-motion: reduce', false);
    }

    /**
     * @return list<string>
     */
    private function municipalityPhotos(): array
    {
        $found = [];

        foreach (['hero-image', 'hero-image2', 'hero-image3', 'municipality-hero'] as $name) {
            foreach (['jpg', 'png', 'webp'] as $extension) {
                if (file_exists(public_path("images/{$name}.{$extension}"))) {
                    $found[] = "{$name}.{$extension}";
                }
            }
        }

        return $found;
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
