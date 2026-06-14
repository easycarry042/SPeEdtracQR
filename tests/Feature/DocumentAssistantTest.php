<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Support\Ai\DocumentAssistant;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\OllamaProvider;
use App\Support\PredictiveAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DocumentAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function makeDocument(): Document
    {
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48, 'email' => 'acct@example.com']);
        $user = User::factory()->create(['department_id' => $dept->id]);

        $doc = Document::create([
            'tracking_number' => 'SPD-ASK-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $user->id,
        ]);
        $doc->syncRouteSteps([$dept->id]);
        $doc->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $dept->id,
            'action' => 'in',
            'scanned_at' => now()->subHours(3),
        ]);

        return $doc->fresh(['scans.department', 'scans.user', 'currentDepartment', 'routeSteps.department']);
    }

    public function test_rule_based_fallback_answers_from_facts(): void
    {
        $doc = $this->makeDocument();
        $assistant = new DocumentAssistant(new NullProvider, new PredictiveAnalytics);

        $result = $assistant->answer($doc, 'Where is my document right now?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringContainsString($doc->tracking_number, $result['answer']);
        $this->assertStringContainsString('Accounting', $result['answer']);
    }

    public function test_uses_llm_answer_when_provider_available(): void
    {
        $doc = $this->makeDocument();
        $fake = new class implements LlmProvider
        {
            public function chat(string $system, string $userMessage): ?string
            {
                return 'Your permit is being processed at the Accounting office.';
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $result = (new DocumentAssistant($fake, new PredictiveAnalytics))->answer($doc, 'where is it?');

        $this->assertSame('ai', $result['source']);
        $this->assertSame('Your permit is being processed at the Accounting office.', $result['answer']);
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
}
