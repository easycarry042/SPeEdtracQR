<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\CitizenView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The citizen portal's two-card front door was retired: its cards (submit,
 * track) only repeated the landing page's hero actions one click later, and
 * every Back on a citizen page landed on it. The landing page is the citizen's
 * home now; the old address survives as a redirect for bookmarks.
 */
class CitizenPortalCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_old_portal_address_sends_citizens_to_the_landing_page(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertRedirect(CitizenView::homeUrl());
    }

    public function test_a_staff_login_on_a_shared_pc_cannot_divert_it_to_the_desk(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('citizen.dashboard'))
            ->assertRedirect(CitizenView::homeUrl());
    }

    public function test_the_landing_page_carries_both_of_the_portals_old_choices(): void
    {
        $response = $this->get(CitizenView::homeUrl())->assertOk();

        $response->assertSee('href="'.route('public.request.create').'"', false);
        $response->assertSee('href="'.route('citizen.track').'"', false);

        // Submit before Track: there is nothing to track until a request has
        // been filed.
        $response->assertSeeInOrder(['Submit a request', 'Track a request']);
    }

    public function test_speedy_rides_along_as_a_floating_launcher(): void
    {
        // It opens a popover rather than linking straight to the lookup — the
        // assistant is scoped to one request, so it has to ask which one first.
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('images/portal/speedy.png', false)
            ->assertSee('Ask Speedy about your request', false)
            ->assertSee('I answer questions about one request at a time.')
            ->assertSee('Open my request');
    }

    public function test_the_tracking_result_page_shows_only_the_real_assistant(): void
    {
        // That page carries the document-scoped <x-doc-assistant/>, which draws
        // its own launcher in the same corner — two bubbles would overlap.
        $document = Document::create([
            'tracking_number' => 'SPD-20260101-ABC123',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $this->get(route('track.show', ['trackingNumber' => $document->tracking_number]))
            ->assertOk()
            ->assertDontSee('images/portal/speedy.png', false);
    }

    public function test_citizen_pages_are_plain_white(): void
    {
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertDontSee('doodle-pattern.jpg', false);
    }
}
