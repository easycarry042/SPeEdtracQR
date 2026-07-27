<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use App\Support\Ai\DocumentAssistant;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\OllamaProvider;
use App\Support\CompletionPredictor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(): Document
    {
        $handler = User::factory()->create(['name' => 'Ana Cruz']);

        return Document::create([
            'tracking_number' => 'SPD-ASK-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
            'created_by' => $handler->id,
            'assigned_to' => $handler->id,
            'status_changed_at' => now()->subHours(3),
        ])->fresh('assignedTo');
    }

    public function test_rule_based_fallback_answers_from_facts(): void
    {
        $doc = $this->makeDocument();
        $assistant = new DocumentAssistant(new NullProvider, new CompletionPredictor);

        $result = $assistant->answer($doc, 'Who is handling my document right now?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString($doc->tracking_number, $result['answer']);
        $this->assertStringContainsString('Ana Cruz', $result['answer']);
    }

    public function test_uses_llm_answer_when_provider_available(): void
    {
        $doc = $this->makeDocument();
        $fake = new class implements LlmProvider
        {
            public function chat(string $system, string $userMessage): ?string
            {
                return 'Your permit is being processed by Ana Cruz.';
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        // An open-ended question (no status/when/who/where keyword) reaches the LLM.
        $result = (new DocumentAssistant($fake, new CompletionPredictor))
            ->answer($doc, 'Can you reassure me in a friendly sentence?');

        $this->assertSame('ai', $result['source']);
        $this->assertSame('Your permit is being processed by Ana Cruz.', $result['answer']);
    }

    public function test_ollama_provider_parses_chat_response(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'Hello from llama']], 200)]);

        $provider = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', 5);

        $this->assertSame('Hello from llama', $provider->chat('system', 'hi'));
    }

    public function test_ollama_provider_returns_null_on_failure(): void
    {
        Http::fake(['*/api/chat' => Http::response('boom', 500)]);

        $provider = new OllamaProvider('http://127.0.0.1:11434', 'llama3.2', 5);

        $this->assertNull($provider->chat('system', 'hi'));
    }

    public function test_ask_endpoint_returns_grounded_answer(): void
    {
        $doc = $this->makeDocument();
        $this->app->instance(LlmProvider::class, new NullProvider); // no network in tests

        $this->postJson(route('track.ask', $doc->tracking_number), ['question' => 'When will it be ready?'])
            ->assertOk()
            ->assertJsonStructure(['answer', 'source'])
            ->assertJsonPath('source', 'fallback');
    }

    public function test_ask_endpoint_validates_question(): void
    {
        $doc = $this->makeDocument();

        $this->postJson(route('track.ask', $doc->tracking_number), ['question' => ''])
            ->assertStatus(422);
    }

    public function test_ask_endpoint_404_for_unknown_document(): void
    {
        $this->postJson(route('track.ask', 'SPD-DOES-NOT-EXIST'), ['question' => 'where is it?'])
            ->assertNotFound();
    }

    private function seedServiceCatalog(): void
    {
        $bp = RequestType::create(['name' => 'Business Permit', 'kind' => 'document', 'is_active' => true]);
        $bp->requirements()->createMany([
            ['label' => 'Barangay Business Clearance', 'is_mandatory' => true, 'sort_order' => 1],
            ['label' => 'Community Tax Certificate (Cedula)', 'is_mandatory' => true, 'sort_order' => 2],
        ]);

        $building = RequestType::create(['name' => 'Building Permit', 'kind' => 'document', 'is_active' => true]);
        $building->requirements()->createMany([
            ['label' => 'Lot Plan / Survey', 'is_mandatory' => true, 'sort_order' => 1],
        ]);
    }

    public function test_answers_requirements_for_a_named_service(): void
    {
        $this->seedServiceCatalog();
        $doc = $this->makeDocument(); // Business Permit
        $assistant = new DocumentAssistant(new NullProvider, new CompletionPredictor);

        // Names a DIFFERENT service than the document being viewed.
        $result = $assistant->answer($doc, 'What are the requirements for a Building Permit?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString('Building Permit', $result['answer']);
        $this->assertStringContainsString('Lot Plan / Survey', $result['answer']);
    }

    public function test_requirements_question_falls_back_to_the_viewed_document_type(): void
    {
        $this->seedServiceCatalog();
        $doc = $this->makeDocument(); // Business Permit
        $assistant = new DocumentAssistant(new NullProvider, new CompletionPredictor);

        $result = $assistant->answer($doc, 'What are the requirements?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString('Barangay Business Clearance', $result['answer']);
        $this->assertStringContainsString('Community Tax Certificate', $result['answer']);
    }

    public function test_requirements_answer_available_through_the_endpoint(): void
    {
        $this->seedServiceCatalog();
        $doc = $this->makeDocument();
        $this->app->instance(LlmProvider::class, new NullProvider);

        $response = $this->postJson(route('track.ask', $doc->tracking_number), ['question' => 'What documents do I need to bring?'])
            ->assertOk()
            ->assertJsonPath('source', 'fallback');

        $this->assertStringContainsString('Barangay Business Clearance', $response->json('answer'));
    }

    public function test_common_questions_skip_the_llm_for_speed(): void
    {
        $doc = $this->makeDocument();
        // A provider that fails the test if it is ever called.
        $neverCalled = new class implements LlmProvider
        {
            public function chat(string $system, string $userMessage): ?string
            {
                throw new \RuntimeException('LLM should not be called for common questions.');
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $result = (new DocumentAssistant($neverCalled, new CompletionPredictor))
            ->answer($doc, 'What is the current status?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString($doc->tracking_number, $result['answer']);
    }

    public function test_when_ready_answer_includes_a_completion_estimate(): void
    {
        // Seed history so the predictor has data: three Business Permits that
        // each took exactly 5 days (created → completed).
        foreach (range(1, 3) as $i) {
            $doc = Document::create([
                'tracking_number' => 'SPD-HIST-'.$i,
                'document_type' => 'Business Permit',
                'status' => 'completed',
            ]);
            $doc->forceFill([
                'created_at' => now()->subDays(20),
                'completed_at' => now()->subDays(15),
            ])->save();
        }

        $doc = $this->makeDocument(); // in_progress, created just now
        $assistant = new DocumentAssistant(new NullProvider, new CompletionPredictor);

        $result = $assistant->answer($doc, 'When will it be ready?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString('likely ready by', $result['answer']);
        $this->assertStringContainsString('day(s) from now', $result['answer']);
    }
}
