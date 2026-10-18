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
        // It used to be a popover that could only ask for a tracking number
        // and send you on; it is now the chat itself (see SpeedyTest).
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('images/portal/speedy.png', false)
            ->assertSee('aria-label="Ask Speedy"', false)
            ->assertSee('Check my request');
    }

    public function test_the_tracking_result_page_has_one_assistant_not_two(): void
    {
        // That page used to add its own "Ask a question" chat beside Speedy's
        // bubble, in the same corner. There is one Speedy now, already about
        // this request.
        $document = Document::create([
            'tracking_number' => 'SPD-20260101-ABC123',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        $content = $this->get(route('track.show', ['trackingNumber' => $document->tracking_number]))
            ->assertOk()
            ->assertDontSee('Ask about this document')
            ->getContent();

        $this->assertSame(1, substr_count($content, 'aria-label="Ask Speedy"'));
    }

    public function test_citizen_pages_are_plain_white(): void
    {
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertDontSee('doodle-pattern.jpg', false);
    }
}
