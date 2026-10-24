<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\RequestType;
use App\Support\SiteFaq;
use Illuminate\Support\Str;

/**
 * Speedy before a request is named: questions about the service itself.
 *
 * Grounded in two public sources only — the site FAQ (App\Support\SiteFaq) and
 * the services catalogue (request types and their requirements). It knows
 * nothing about anyone's request; for that it asks for a tracking number, and
 * the request-scoped DocumentAssistant takes over.
 *
 * Deterministic first, the model last: a greeting, a requirements question or
 * an FAQ match is answered instantly from the source text. Only a question none
 * of those cover reaches the local LLM, and it is told to answer from the same
 * text and nothing else. With no LLM it says what it can help with.
 */
final class SiteAssistant
{
    /** Shown whenever Speedy offers to look up the citizen's own request. */
    public const string OWN_REQUEST_HINT = 'If you tell me your tracking number (it looks like SPD-20261016-ABC123), I can check yours.';

    public function __construct(private readonly LlmProvider $llm) {}

    /**
     * @return array{answer: string, source: string}
     */
    public function answer(string $question): array
    {
        $q = Str::lower(trim($question));

        if ($this->isGreeting($q)) {
            return ['answer' => $this->introduction(), 'source' => 'fallback'];
        }

        if (Str::contains($q, ['thank', 'salamat'])) {
            return ['answer' => "You're welcome! Anything else I can help with?", 'source' => 'fallback'];
        }

        if (RequirementsAnswer::asks($question)) {
            $requirements = RequirementsAnswer::resolve($question);

            return [
                'answer' => $requirements !== null
                    ? RequirementsAnswer::text($requirements)
                    : 'Which service is it for? For example: '.$this->serviceExamples().'.',
                'source' => 'fallback',
            ];
        }

        if ($faq = $this->bestFaq($q)) {
            $answer = $faq['answer_text'];

            if ($faq['about_own_request']) {
                $answer .= "\n".self::OWN_REQUEST_HINT;
            }

            return ['answer' => $answer, 'source' => 'faq'];
        }

        $reply = $this->llm->chat($this->systemPrompt(), $question);
        if ($reply !== null) {
            return ['answer' => $reply, 'source' => 'ai'];
        }

        return ['answer' => "I'm not sure about that one. ".$this->introduction(), 'source' => 'fallback'];
    }

    /**
     * The FAQ entry whose keywords the question hits most; null when none hit.
     *
     * @return array{question: string, answer_html: string, answer_text: string, keywords: list<string>, about_own_request: bool}|null
     */
    private function bestFaq(string $q): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ([...SiteFaq::all(), ...SiteFaq::assistantOnly()] as $entry) {
            $score = collect($entry['keywords'])->filter(fn (string $k): bool => Str::contains($q, $k))->count();

            if ($score > $bestScore) {
                $best = $entry;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function isGreeting(string $q): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|good (morning|afternoon|evening)|kumusta|magandang (umaga|hapon|gabi))\b[\s!.,?]*$/u', $q);
    }

    private function introduction(): string
    {
        return "Hi, I'm Speedy! I can explain how filing and tracking work, list the requirements for a service, "
            .'or check on your request if you give me its tracking number.';
    }

    private function serviceExamples(): string
    {
        return RequestType::where('is_active', true)->orderBy('name')->limit(6)->pluck('name')->implode(', ')
            ?: 'a Business Permit';
    }

    private function systemPrompt(): string
    {
        $faq = collect([...SiteFaq::all(), ...SiteFaq::assistantOnly()])
            ->map(fn (array $e): string => "Q: {$e['question']}\nA: {$e['answer_text']}")
            ->implode("\n\n");

        $services = RequestType::where('is_active', true)->orderBy('name')->pluck('name')->implode(', ');

        // Deliberately short: fewer prompt tokens = faster prefill on CPU.
        return <<<PROMPT
        You are Speedy, the assistant of SPeED TraQR, the City Government of San Pedro, Laguna's request tracking site. Answer ONLY from the FAQ and the services list below. If the answer is not there, say you don't know and suggest emailing the office. If the citizen asks about their own request, ask for its tracking number. Never invent details, dates or fees. Reply in 1-2 short sentences.

        SERVICES: {$services}

        FAQ:
        {$faq}
        PROMPT;
    }
}
