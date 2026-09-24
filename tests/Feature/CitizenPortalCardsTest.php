<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The portal's front door: three choice cards over the arrow-doodle wash.
 */
class CitizenPortalCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_offers_three_choices_with_their_own_calls_to_action(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Welcome to the Citizen Portal')
            ->assertSee('How can we help you today?')
            ->assertSee('Track a Document')
            ->assertSee('Track Now')
            ->assertSee('Submit a Request')
            ->assertSee('Submit Now')
            ->assertSee('Speedy')
            ->assertSee('Chat Speedy');
    }

    public function test_each_card_links_to_its_destination(): void
    {
        $response = $this->get(route('citizen.dashboard'))->assertOk();

        $response->assertSee('href="'.route('citizen.track').'"', false);
        $response->assertSee('href="'.route('public.request.create').'"', false);

        // Speedy is document-scoped, so its card routes through the lookup —
        // two of the three cards point at the tracking page.
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'href="'.route('citizen.track').'"')
        );
    }

    public function test_speedy_card_wordmark_and_button_carry_the_gradient_treatment(): void
    {
        // The rotating ring, the gradient wordmark and the gradient button are
        // one look: if any hook is dropped the card falls back to flat green.
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('speedy-card', false)
            ->assertSee('speedy-text', false)
            ->assertSee('speedy-btn', false);
    }

    public function test_all_three_cards_use_the_login_card_glass(): void
    {
        $response = $this->get(route('citizen.dashboard'))->assertOk();

        $this->assertSame(3, substr_count($response->getContent(), 'portal-card'));
    }

    public function test_portal_wash_uses_the_arrow_doodle(): void
    {
        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('images/doodle-bg.png', false);
    }
}
