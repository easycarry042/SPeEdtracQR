<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The portal's front door: two choice cards over the arrow-doodle backdrop,
 * with Speedy parked in the corner as a floating launcher.
 */
class CitizenPortalCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_offers_two_choices_with_their_own_calls_to_action(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Welcome to the Citizen Portal')
            ->assertSee('How can we help you today?')
            ->assertSee('Track a Document')
            ->assertSee('Track Now')
            ->assertSee('Submit a Request')
            ->assertSee('Submit Now');
    }

    public function test_each_card_links_to_its_destination(): void
    {
        $response = $this->get(route('citizen.dashboard'))->assertOk();

        $response->assertSee('href="'.route('citizen.track').'"', false);
        $response->assertSee('href="'.route('public.request.create').'"', false);

        // Two links land on the lookup: the Track card, and the "Scan your QR
        // code" escape hatch inside Speedy's popover. The bubble itself is a
        // button, not a link.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'href="'.route('citizen.track').'"')
        );
    }

    public function test_speedy_rides_along_as_a_floating_launcher(): void
    {
        // The mascot moved out of the card row into a fixed bubble. It opens a
        // popover rather than linking straight to the lookup — the assistant is
        // scoped to one request, so it has to ask which one first, and a bare
        // link did visibly nothing when you were already on the lookup.
        $this->get(route('citizen.dashboard'))
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
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('images/portal/speedy.png', false);

        $document = Document::create([
            'tracking_number' => 'SPD-20260101-ABC123',
            'document_type' => 'Business Permit',
            'status' => 'pending',
        ]);

        // The lookup redirects to the result page; that page is the one that
        // carries the real assistant.
        $this->get(route('track.show', ['trackingNumber' => $document->tracking_number]))
            ->assertOk()
            ->assertDontSee('images/portal/speedy.png', false);
    }

    public function test_both_cards_carry_the_lime_glass_treatment(): void
    {
        $response = $this->get(route('citizen.dashboard'))->assertOk();

        // Matched on the card box itself — the lime wash alone also appears on
        // the header's Back control, which is not one of the choice cards.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'h-[380px] w-full max-w-[340px]')
        );
    }

    public function test_portal_backdrop_uses_the_arrow_doodle(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('images/landing/doodle-pattern.jpg', false);
    }
}
