<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\RequestType;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\SiteAssistant;
use App\Support\CitizenReceipt;
use App\Support\SiteFaq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Speedy: one assistant on every citizen page. It answers questions about the
 * service from the site FAQ and the services catalogue, and about one request
 * once its tracking number is typed or scanned into the chat.
 *
 * It replaced two assistants that did not know about each other — a mascot
 * popover that could only send you to the tracking page, and a chat on that
 * page that knew only the one request.
 */
class SpeedyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic answers unless a test says otherwise.
        $this->app->instance(LlmProvider::class, new NullProvider);
    }

    private function document(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-20261017-SPD234',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'status_changed_at' => now(),
        ], $attributes));
    }

    private function ask(string $question, ?string $tracking = null): TestResponse
    {
        return $this->postJson(route('speedy.ask'), ['question' => $question, 'tracking' => $tracking])->assertOk();
    }

    // ── where Speedy appears ─────────────────────────────────────────────

    public function test_every_citizen_page_has_exactly_one_speedy(): void
    {
        $document = $this->document();

        $receipt = $this->withSession([CitizenReceipt::SESSION_KEY => [
            'tracking_number' => $document->tracking_number,
            'expires_at' => now()->addMinutes(CitizenReceipt::LIFETIME_MINUTES)->getTimestamp(),
        ]])->get(route('public.request.submitted'))->assertOk()->getContent();

        $pages = [
            'landing page' => $this->get(route('welcome'))->getContent(),
            'request form' => $this->get(route('public.request.create'))->getContent(),
            'track lookup' => $this->get(route('citizen.track'))->getContent(),
            'tracking result' => $this->get($document->citizenTrackingUrl())->getContent(),
            'receipt' => $receipt,
            'privacy notice' => $this->get(route('privacy'))->getContent(),
        ];

        foreach ($pages as $name => $content) {
            $this->assertSame(1, substr_count($content, 'aria-label="Ask Speedy"'), "{$name}: expected one Speedy launcher");
            $this->assertStringContainsString('Automated assistant', $content, "{$name}: Speedy must say it is automated");
            $this->assertStringNotContainsString('Ask about this document', $content, "{$name}: the old second assistant is back");
        }
    }

    public function test_a_page_about_one_request_starts_speedy_on_it(): void
    {
        $document = $this->document();

        $this->get($document->citizenTrackingUrl())
            ->assertOk()
            ->assertSee('SPD-20261017-SPD234', false)
            ->assertSee('When will my Business Permit be ready?');
    }

    public function test_other_pages_start_speedy_on_the_service(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('Check my request')
            ->assertSee('How do I file a request?');
    }

    public function test_the_landing_page_faq_and_speedy_share_one_source(): void
    {
        $content = $this->get(route('welcome'))->assertOk()->getContent();

        foreach (SiteFaq::all() as $entry) {
            $this->assertStringContainsString(e($entry['question']), $content);
        }
    }

    // ── answering about the service ──────────────────────────────────────

    public function test_it_answers_from_the_site_faq(): void
    {
        $this->ask('Do I need an account?')
            ->assertJsonPath('source', 'faq')
            ->assertJsonPath('answer', fn (string $answer) => str_starts_with($answer, 'No. Anyone can file and track a request'));
    }

    public function test_a_question_about_your_own_request_offers_to_look_it_up(): void
    {
        $answer = $this->ask('How long until my document is ready?')->json('answer');

        $this->assertStringContainsString(SiteAssistant::OWN_REQUEST_HINT, $answer);
    }

    public function test_it_lists_requirements_for_a_named_service(): void
    {
        RequestType::create(['name' => 'Building Permit', 'kind' => 'document', 'is_active' => true])
            ->requirements()->create(['label' => 'Lot Plan / Survey', 'is_mandatory' => true, 'sort_order' => 1]);

        $answer = $this->ask('What do I need for a Building Permit?')->json('answer');

        $this->assertStringContainsString('Lot Plan / Survey', $answer);
    }

    public function test_it_asks_which_service_when_none_is_named(): void
    {
        RequestType::create(['name' => 'Cedula', 'kind' => 'document', 'is_active' => true]);

        $answer = $this->ask('What are the requirements?')->json('answer');

        $this->assertStringStartsWith('Which service is it for?', $answer);
        $this->assertStringContainsString('Cedula', $answer);
    }

    public function test_an_open_question_goes_to_the_model_grounded_in_the_faq(): void
    {
        $model = new class implements LlmProvider
        {
            public string $system = '';

            public function chat(string $system, string $userMessage): ?string
            {
                $this->system = $system;

                return 'Grounded reply.';
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };
        $this->app->instance(LlmProvider::class, $model);

        $this->ask('Is there parking near City Hall?')
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('answer', 'Grounded reply.');

        $this->assertStringContainsString('Answer ONLY from the FAQ', $model->system);
        $this->assertStringContainsString(SiteFaq::all()[0]['question'], $model->system);
    }

    public function test_without_a_model_it_says_what_it_can_help_with(): void
    {
        $this->ask('Is there parking near City Hall?')
            ->assertJsonPath('source', 'fallback')
            ->assertJsonPath('answer', fn (string $answer) => str_contains($answer, 'list the requirements for a service'));
    }

    // ── switching to a request ───────────────────────────────────────────

    public function test_a_tracking_number_in_the_chat_makes_that_request_the_topic(): void
    {
        $this->document();

        $this->ask('where is spd-20261017-spd234?')
            ->assertJsonPath('context.tracking', 'SPD-20261017-SPD234')
            ->assertJsonPath('context.type', 'Business Permit')
            ->assertJsonPath('context.status', 'Pending')
            ->assertJsonPath('answer', fn (string $answer) => str_starts_with($answer, 'Found it'));
    }

    public function test_then_it_answers_from_that_requests_facts(): void
    {
        $this->document();

        $answer = $this->ask('What is the status?', 'SPD-20261017-SPD234')->json('answer');

        $this->assertStringContainsString('SPD-20261017-SPD234', $answer);
        $this->assertStringContainsString('pending', $answer);
    }

    public function test_asking_what_to_bring_for_pickup_gets_the_qr_code_not_the_filing_checklist(): void
    {
        // "Bring" also means requirements, so this chip used to answer with the
        // checklist for filing — for a document that was already finished.
        RequestType::create(['name' => 'Business Permit', 'kind' => 'document', 'is_active' => true])
            ->requirements()->create(['label' => 'Barangay Business Clearance', 'is_mandatory' => true, 'sort_order' => 1]);
        $this->document(['status' => 'completed', 'completed_at' => now()]);

        $about = $this->ask('What do I bring for pickup?', 'SPD-20261017-SPD234')->json('answer');
        $general = $this->ask('What do I bring for pickup?')->json('answer');

        foreach ([$about, $general] as $answer) {
            $this->assertStringContainsString('QR code', $answer);
            $this->assertStringNotContainsString('Barangay Business Clearance', $answer);
        }
    }

    public function test_an_unknown_number_is_reported_without_a_topic(): void
    {
        $this->ask('SPD-20261017-NOPE99')
            ->assertJsonMissingPath('context')
            ->assertJsonPath('answer', fn (string $answer) => str_starts_with($answer, "I couldn't find SPD-20261017-NOPE99"));
    }

    public function test_internal_office_requests_are_never_a_citizens_topic(): void
    {
        // Every public page reports them as not found; so does Speedy.
        $this->document(['tracking_number' => 'INT-20261017-OFF234', 'origin' => Document::ORIGIN_INTERNAL]);

        $this->ask('INT-20261017-OFF234')->assertJsonMissingPath('context');
        $this->ask('What is the status?', 'INT-20261017-OFF234')->assertJsonPath('context', null);
    }

    public function test_the_tracking_page_endpoint_no_longer_answers_for_internal_requests(): void
    {
        // It used to look requests up by number alone, internal ones included.
        $this->document(['tracking_number' => 'INT-20261017-OFF567', 'origin' => Document::ORIGIN_INTERNAL]);

        $this->postJson(route('track.ask', 'INT-20261017-OFF567'), ['question' => 'What is the status?'])
            ->assertNotFound();
    }

    public function test_it_rejects_a_question_too_short_to_answer(): void
    {
        $this->postJson(route('speedy.ask'), ['question' => 'a'])->assertUnprocessable();
    }

    public function test_it_is_rate_limited(): void
    {
        foreach (range(1, 20) as $i) {
            $this->postJson(route('speedy.ask'), ['question' => 'Do I need an account?'])->assertOk();
        }

        $this->postJson(route('speedy.ask'), ['question' => 'Do I need an account?'])->assertTooManyRequests();
    }
}
