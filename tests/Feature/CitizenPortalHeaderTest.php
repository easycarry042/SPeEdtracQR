<?php

namespace Tests\Feature;

use App\Support\CitizenView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public portal header is shared by the citizen layout, the guest view of
 * app-layout pages, and the public request form — so its contents are pinned
 * here rather than in a per-page test.
 */
class CitizenPortalHeaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function citizenPages(): array
    {
        return [route('citizen.track'), route('public.request.create'), route('privacy')];
    }

    public function test_every_citizen_page_offers_one_way_back_to_the_homepage(): void
    {
        foreach ($this->citizenPages() as $url) {
            $content = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('Back to Homepage', $content);
            // Citizen-flagged, so a staff login on a shared PC cannot divert it.
            $this->assertStringContainsString('href="'.e(CitizenView::homeUrl()).'"', $content);
            // The retired portal page is never a destination.
            $this->assertStringNotContainsString('href="'.route('citizen.dashboard').'"', $content);
        }
    }

    public function test_the_header_drops_the_track_and_staff_login_buttons(): void
    {
        // Both actions are reachable from the landing page; the header stays
        // down to a single wayfinding control.
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertDontSee('Track Document')
            ->assertDontSee('Staff Login');
    }

    public function test_public_request_form_shares_the_same_header(): void
    {
        $this->get(route('public.request.create'))
            ->assertOk()
            ->assertDontSee('Staff Login');
    }

    public function test_the_bar_carries_its_own_white_surface_on_every_page(): void
    {
        // The bar no longer sits on a deep-green wash, so the wordmark is dark
        // green on white everywhere — one lockup, no light/dark variants.
        foreach ($this->citizenPages() as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('images/landing/speed-icon.png', false)
                ->assertSee('text-[#004004]', false)
                ->assertDontSee('images/icon-white.png', false);
        }
    }

    public function test_back_never_relies_on_browser_history(): void
    {
        // history.back() returns the visitor to whatever preceded this page — a
        // search engine, the staff login — not necessarily the homepage.
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertDontSee('history.back()', false);
    }
}
