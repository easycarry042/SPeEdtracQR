<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public help desk on the citizen landing page: procedural answers grounded
 * in the service catalogue, with no access to any individual request.
 */
class HelpAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function courtService(): RequestType
    {
        $type = RequestType::create([
            'name' => 'Basketball Court Reservation',
            'kind' => RequestType::KIND_BOOKING,
            'is_active' => true,
        ]);

        $type->requirements()->createMany([
            ['label' => 'Letter of intent addressed to the mayor', 'is_mandatory' => true],
            ['label' => 'Barangay clearance', 'is_mandatory' => true],
            ['label' => 'List of participants', 'is_mandatory' => false],
        ]);

        return $type;
    }

    public function test_it_answers_requirements_for_a_named_service(): void
    {
        $this->courtService();

        $response = $this->postJson(route('help.ask'), [
            'question' => 'What documents do I need to request a basketball court?',
        ])->assertOk();

        $answer = $response->json('answer');
        $this->assertStringContainsString('Letter of intent', $answer);
        $this->assertStringContainsString('Barangay clearance', $answer);
        // Optional items are labelled, not mixed in with the required ones.
        $this->assertStringContainsString('Optional', $answer);
    }

    public function test_it_explains_the_authentication_process(): void
    {
        $response = $this->postJson(route('help.ask'), [
            'question' => 'What is the process for document authentication?',
        ])->assertOk();

        $this->assertStringContainsString('authenticat', strtolower((string) $response->json('answer')));
    }

    public function test_it_explains_how_to_track_a_request(): void
    {
        $response = $this->postJson(route('help.ask'), [
            'question' => 'How do I track my request?',
        ])->assertOk();

        $this->assertStringContainsString('tracking number', strtolower((string) $response->json('answer')));
    }

    public function test_a_question_needs_actual_content(): void
    {
        $this->postJson(route('help.ask'), ['question' => 'a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_the_landing_page_carries_the_help_desk_and_the_tracking_page_does_not(): void
    {
        $this->courtService();

        $this->get('/')
            ->assertOk()
            ->assertSee('Need help?')
            ->assertSee('helpAssistant(', false);

        $document = Document::create([
            'tracking_number' => 'SPD-20260730-HLP123',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
        ]);

        // The per-document assistant is gone from the tracking page: help belongs
        // on the landing page, status belongs here.
        $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->assertDontSee('docAssistant(', false)
            ->assertDontSee('Ask about this document');
    }
}
