<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackLookupTest extends TestCase
{
    use RefreshDatabase;

    private function citizenDoc(string $tracking = 'SPD-20260728-K7M9Q2'): Document
    {
        return Document::create([
            'tracking_number' => $tracking,
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
            'origin' => Document::ORIGIN_EXTERNAL,
        ]);
    }

    public function test_lookup_returns_the_status_for_a_real_tracking_number(): void
    {
        $doc = $this->citizenDoc();

        $this->getJson(route('track.lookup', ['tracking_number' => $doc->tracking_number]))
            ->assertOk()
            ->assertJsonPath('status', 'found')
            ->assertJsonPath('data.tracking_number', $doc->tracking_number)
            ->assertJsonPath('data.document_type', 'Business Permit');
    }

    public function test_lookup_is_case_insensitive(): void
    {
        $doc = $this->citizenDoc();

        $this->getJson(route('track.lookup', ['tracking_number' => strtolower($doc->tracking_number)]))
            ->assertOk()
            ->assertJsonPath('status', 'found');
    }

    public function test_lookup_reports_not_found_for_a_well_formed_but_unknown_number(): void
    {
        $this->getJson(route('track.lookup', ['tracking_number' => 'SPD-20260728-ZZZZZZ']))
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found')
            ->assertJsonPath('title', 'No record found.');
    }

    public function test_lookup_rejects_invalid_formats(): void
    {
        foreach (['garbage!!', 'SPD-123', '<script>', 'ABC-20260728-K7M9Q2', ''] as $bad) {
            $this->getJson(route('track.lookup', ['tracking_number' => $bad]))
                ->assertStatus(422)
                ->assertJsonPath('status', 'invalid')
                ->assertJsonPath('title', 'Invalid tracking number.');
        }
    }

    public function test_internal_requests_are_not_publicly_resolvable(): void
    {
        Document::create([
            'tracking_number' => 'INT-20260728-K7M9Q2',
            'document_type' => 'Procurement',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        // Valid format, but internal — reported as not found (no info leak).
        $this->getJson(route('track.lookup', ['tracking_number' => 'INT-20260728-K7M9Q2']))
            ->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_guest_visiting_the_bare_lookup_hub_is_sent_home(): void
    {
        $this->get(route('track.index'))->assertRedirect(route('welcome'));
        $this->get(route('track.index', ['find' => 1]))->assertRedirect(route('welcome'));
    }

    public function test_guest_cannot_open_an_internal_request_through_the_public_tracker(): void
    {
        Document::create([
            'tracking_number' => 'INT-20260728-ABC234',
            'document_type' => 'Procurement',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        $this->get(route('track.show', 'INT-20260728-ABC234'))->assertNotFound();
    }
}
