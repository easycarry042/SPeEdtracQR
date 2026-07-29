<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The citizen portal resolves a tracking number itself instead of forwarding an
 * unchecked one to track.show — whose "not found" path sends guests to the
 * public landing page, stranding them with no explanation.
 */
class CitizenTrackLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_malformed_tracking_number_reports_the_error_on_the_track_page(): void
    {
        $this->get(route('citizen.track', ['tracking' => 'not-a-number']))
            ->assertOk()
            ->assertSee('is not a valid tracking number')
            ->assertSee('Track a Document');
    }

    public function test_a_well_formed_but_unknown_tracking_number_reports_not_found(): void
    {
        $this->get(route('citizen.track', ['tracking' => 'SPD-20260728-ZZZ999']))
            ->assertOk()
            ->assertSee('No document found for SPD-20260728-ZZZ999');
    }

    public function test_a_known_tracking_number_opens_the_public_timeline(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-20260728-ABC234',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
        ]);

        $this->get(route('citizen.track', ['tracking' => $document->tracking_number]))
            ->assertRedirect(route('track.show', ['trackingNumber' => $document->tracking_number]));
    }

    public function test_lowercase_input_is_normalised_before_lookup(): void
    {
        $document = Document::create([
            'tracking_number' => 'SPD-20260728-ABC234',
            'document_type' => 'Cedula',
            'citizen_name' => 'Maria Santos',
            'status' => 'pending',
        ]);

        $this->get(route('citizen.track', ['tracking' => ' spd-20260728-abc234 ']))
            ->assertRedirect(route('track.show', ['trackingNumber' => $document->tracking_number]));
    }

    public function test_guests_are_never_told_that_an_internal_request_exists(): void
    {
        Document::create([
            'tracking_number' => 'INT-20260728-ABC234',
            'document_type' => 'Budget Endorsement',
            'citizen_name' => 'Records Office',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        // Reports as "not found" rather than redirecting into a 404 downstream.
        $this->get(route('citizen.track', ['tracking' => 'INT-20260728-ABC234']))
            ->assertOk()
            ->assertSee('No document found for INT-20260728-ABC234');
    }

    public function test_an_empty_lookup_just_renders_the_form(): void
    {
        $this->get(route('citizen.track'))
            ->assertOk()
            ->assertSee('Enter Tracking Number')
            ->assertDontSee('We couldn\'t track that number.');
    }
}
