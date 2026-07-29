<?php

namespace Tests\Feature;

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

    public function test_citizen_portal_header_offers_a_route_back_to_the_public_homepage(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Back to Homepage')
            ->assertSee('href="'.route('welcome').'"', false);
    }

    public function test_citizen_portal_header_drops_the_track_and_staff_login_buttons(): void
    {
        // Both actions are reachable from the landing page and the portal cards;
        // the header stays down to a single wayfinding control.
        $this->get(route('citizen.dashboard'))
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

    public function test_sub_pages_show_only_a_back_button_not_a_homepage_button(): void
    {
        // One wayfinding control per page: sub-pages step back a level, so the
        // homepage shortcut would be a second, competing "back".
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('Back')
            ->assertDontSee('Back to Homepage');
    }

    public function test_back_always_lands_on_the_citizen_portal(): void
    {
        // Never history.back() — that returns the visitor to whatever preceded
        // this page, which is not necessarily one level up in the portal.
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('href="'.route('citizen.dashboard').'"', false)
            ->assertDontSee('history.back()', false);
    }
}
