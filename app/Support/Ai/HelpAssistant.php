<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\RequestType;
use Illuminate\Support\Str;

/**
 * The public help desk assistant on the citizen landing page.
 *
 * Where DocumentAssistant answers "where is MY document?" for one tracked
 * request, this one answers procedural questions before a request exists:
 * "what do I need to reserve the basketball court?", "how do I get a document
 * authenticated?", "how do I track my request?".
 *
 * It is grounded in two public sources only — the office's service catalogue
 * (request types + their requirement checklists) and a fixed set of process
 * explanations — so it can never surface anyone's record. It holds no document
 * context at all, which is exactly why it is safe to put on a public page.
 */
class HelpAssistant
{
    public function __construct(private readonly LlmProvider $llm) {}

    /**
     * @return array{answer:string, source:string}
     */
    public function answer(string $question): array
    {
        $service = $this->matchService($question);

        // "What do I need for X?" — the most common question, answered
        // deterministically from the catalogue (instant and auditable).
        if ($service && $this->asksAboutRequirements($question)) {
            return ['answer' => $this->requirementsAnswer($service), 'source' => 'fallback'];
        }

        // Process questions come next, so "how much is a business permit?" gets
        // the fees answer rather than a requirements checklist.
        if ($canned = $this->processAnswer($question)) {
            return ['answer' => $canned, 'source' => 'fallback'];
        }

        // A service was named with no other recognisable intent — its checklist
        // is the most useful thing we can offer.
        if ($service) {
            return ['answer' => $this->requirementsAnswer($service), 'source' => 'fallback'];
        }

        // Anything open-ended goes to the local model, grounded in the catalogue.
        $reply = $this->llm->chat($this->systemPrompt(), $question);

        if ($reply !== null) {
            return ['answer' => $reply, 'source' => 'ai'];
        }

        return ['answer' => $this->fallback(), 'source' => 'fallback'];
    }

    /**
     * Suggested opening questions, built from real services so the chips match
     * what this office actually offers.
     *
     * @return list<string>
     */
    public function suggestions(): array
    {
        // The landing page is the site's front door: it must render even if the
        // catalogue can't be read, so fall back to the process questions.
        try {
            $services = $this->services()->take(2)
                ->map(fn (RequestType $t): string => "What do I need for a {$t->name}?")
                ->all();
        } catch (\Throwable) {
            $services = [];
        }

        return array_values(array_filter([
            ...$services,
            'How do I submit a request?',
            'How do I track my request?',
        ]));
    }

    /** Active services, longest name first so specific names beat partial ones. */
    private function services()
    {
        return RequestType::with('requirements')
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->sortByDesc(fn (RequestType $t): int => Str::length($t->name))
            ->values();
    }

    /** Whether the citizen is asking what to prepare rather than how things work. */
    private function asksAboutRequirements(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'requirement', 'require', 'what do i need', 'what i need', 'need to bring',
            'documents needed', 'documents do i need', 'what to bring', 'bring',
            'checklist', 'prepare', 'need for', 'needed for',
        ]);
    }

    /**
     * Resolve which service a question is about. Citizens rarely type a service's
     * full registered name ("Basketball Court Reservation") — they write "request
     * a basketball court" — so match on the distinctive words of each name and
     * take the best-scoring service, longest name breaking ties.
     */
    private function matchService(string $question): ?RequestType
    {
        $q = Str::lower($question);

        // Words shared by many service names carry no signal on their own.
        $generic = ['reservation', 'request', 'requests', 'application', 'form', 'for', 'and', 'the'];

        $scored = $this->services()
            ->map(function (RequestType $type) use ($q, $generic): array {
                $tokens = collect(preg_split('/[^a-z0-9]+/', Str::lower($type->name), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                    ->reject(fn (string $token): bool => Str::length($token) < 3 || in_array($token, $generic, true));

                // The full name still wins outright when it is spelled out.
                $score = Str::contains($q, Str::lower($type->name))
                    ? PHP_INT_MAX
                    : $tokens->filter(fn (string $token): bool => Str::contains($q, $token))->count();

                return ['type' => $type, 'score' => $score];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score');

        return $scored->first()['type'] ?? null;
    }

    private function requirementsAnswer(RequestType $service): string
    {
        $items = $service->requirements;

        if ($items->isEmpty()) {
            return "I don't have a requirements checklist on file for {$service->name} yet. "
                .'Please contact the handling office to confirm what to prepare.';
        }

        $mandatory = $items->where('is_mandatory', true);
        $optional = $items->where('is_mandatory', false);
        $listed = $mandatory->isNotEmpty() ? $mandatory : $items;

        $text = "For a {$service->name}, you'll typically need:\n"
            .$listed->map(fn ($r): string => '• '.$r->label)->implode("\n");

        if ($mandatory->isNotEmpty() && $optional->isNotEmpty()) {
            $text .= "\nOptional / if applicable:\n"
                .$optional->map(fn ($r): string => '• '.$r->label)->implode("\n");
        }

        return $text."\nYou can submit these online through \"Submit a Request\" — "
            .'the office confirms the final list for your case.';
    }

    /** Fixed answers for how-the-office-works questions. */
    private function processAnswer(string $question): ?string
    {
        $q = Str::lower($question);

        $intents = [
            ['keys' => ['how do i submit', 'how to submit', 'how do i file', 'how to file', 'how do i request', 'how to request', 'apply'],
                'answer' => "Use \"Submit a Request\" on this page: choose the service, fill in your details, and attach the required documents.\nYou'll get a tracking number and a QR-coded claim slip right away — keep it, that's how you follow and claim your request."],

            ['keys' => ['track', 'follow up', 'check the status', 'check status', 'where is my'],
                'answer' => "Enter your tracking number (it looks like SPD-20260728-K7M9Q2) in the tracking search, or scan the QR code on your claim slip.\nThe tracking page shows the current stage, who is handling it, and every update as it happens."],

            ['keys' => ['authentic', 'certified true copy', 'verify', 'verification', 'notar'],
                'answer' => "For document authentication: submit the document with a valid ID through \"Submit a Request\", and the handling office verifies it and issues the authenticated copy.\nEvery released document carries a QR seal — scanning it confirms the copy is genuinely ours."],

            ['keys' => ['how long', 'how many days', 'processing time', 'when will'],
                'answer' => "Processing time depends on the service and the completeness of your requirements.\nOnce your request is filed, its tracking page shows the current stage and an estimated completion date based on similar past requests."],

            ['keys' => ['claim', 'pick up', 'pickup', 'releasing', 'release'],
                'answer' => "When your request reaches Completed, bring your claim slip (or the QR code) and a valid ID to the handling office.\nStaff scan your QR code to release the document to you."],

            ['keys' => ['fee', 'payment', 'how much', 'cost', 'price'],
                'answer' => "Fees depend on the service and are confirmed by the handling office when your request is reviewed.\nThe office will contact you using the email or number you provide if a payment is needed."],

            ['keys' => ['office hours', 'open', 'schedule', 'location', 'address', 'where do i go'],
                'answer' => 'You can submit and track requests here at any time. For office hours and the counter location, please contact the handling office listed on your request.'],

            ['keys' => ['court', 'plaza', 'venue', 'reserve', 'reservation', 'book', 'booking'],
                'answer' => "To reserve a facility or equipment, file the matching reservation request under \"Submit a Request\" and include your preferred date.\nStaff confirm the date on their calendar — a date already reserved for that facility can't be double-booked, so they'll offer the nearest free one."],
        ];

        foreach ($intents as $intent) {
            if (Str::contains($q, $intent['keys'])) {
                return $intent['answer'];
            }
        }

        return null;
    }

    private function systemPrompt(): string
    {
        $catalogue = $this->services()
            ->map(function (RequestType $t): string {
                $requirements = $t->requirements->map(fn ($r): string => $r->label)->implode(', ');

                return '- '.$t->name.($requirements !== '' ? ': '.$requirements : ': no checklist on file');
            })
            ->implode("\n");

        $catalogue = $catalogue !== '' ? $catalogue : '- (no services published yet)';

        return <<<PROMPT
        You are the help desk assistant for SPeED TraQR, the San Pedro records office request tracker. Answer citizens' questions about HOW TO get things done, using ONLY the services and facts below. You have no access to anyone's individual request — if asked about a specific request's status, tell them to enter their tracking number in the tracking search. If something is not in the facts, say you don't have that information and suggest contacting the handling office. Never invent requirements, fees, or dates. Reply in 1-3 short sentences.

        SERVICES AND THEIR REQUIREMENTS:
        {$catalogue}

        HOW THE OFFICE WORKS:
        - Citizens submit a request online via "Submit a Request", with attachments.
        - Each request gets a tracking number (format SPD-YYYYMMDD-XXXXXX) and a QR-coded claim slip.
        - Staff review the request, may return it for revision, then approve and complete it.
        - Citizens track progress any time with the tracking number or by scanning their QR code.
        - Completed documents are claimed at the office with the claim slip and a valid ID.
        PROMPT;
    }

    private function fallback(): string
    {
        return 'I can help with what to prepare for a service, how to submit a request, and how to track or claim it. '
            .'For anything specific to your own request, enter your tracking number in the tracking search above.';
    }
}
