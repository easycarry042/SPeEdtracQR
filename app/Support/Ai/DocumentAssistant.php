<?php

namespace App\Support\Ai;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\RequestType;
use App\Support\CompletionPredictor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * The citizen-facing "where is my document?" assistant.
 *
 * It is a small, tightly-scoped RAG with two public-safe knowledge sources:
 *   1. the facts of the ONE document the citizen is viewing (status, progress,
 *      handler, timeline, predicted completion), and
 *   2. the office's public services catalogue (each request type's requirement
 *      checklist) so it can answer "what do I need for a Business Permit?".
 *
 * It never sees any other citizen's record, so it cannot leak private data, and
 * it is told to answer only from these facts — keeping a self-hosted model from
 * hallucinating. If the LLM is unavailable (or the question maps to a known
 * intent), a deterministic answer is returned from the very same facts, so the
 * feature always works and stays fast.
 */
class DocumentAssistant
{
    public function __construct(
        private readonly LlmProvider $llm,
        private readonly CompletionPredictor $predictor,
    ) {}

    /**
     * @return array{answer:string, source:string}
     */
    public function answer(Document $document, string $question): array
    {
        $facts = $this->facts($document);

        // Requirements / "what do I need" — answered from the public services
        // catalogue (this document's type, or another service named in the
        // question). Checked first so it wins even for a completed document.
        if ($this->asksAboutRequirements($question)) {
            $requirements = $this->resolveRequirements($question, $document);
            if ($requirements !== null) {
                return ['answer' => $this->requirementsAnswer($requirements), 'source' => 'fallback'];
            }
        }

        // Fast path: a completed document, or a common structured question
        // (status / when / who / where), is answered INSTANTLY from the
        // document's own facts. This skips the model round-trip entirely — the
        // single biggest latency win — and the answer is fully auditable.
        if ($facts['data']['is_completed'] || $this->isDeterministicIntent($question)) {
            return ['answer' => $this->ruleBasedAnswer($facts['data'], $question), 'source' => 'fallback'];
        }

        // Only genuinely open-ended questions reach the (slower) local LLM.
        $reply = $this->llm->chat($this->systemPrompt($facts['text']), $question);
        if ($reply !== null) {
            return ['answer' => $reply, 'source' => 'ai'];
        }

        return ['answer' => $this->ruleBasedAnswer($facts['data'], $question), 'source' => 'fallback'];
    }

    /**
     * Whether the question maps to a fact we can answer deterministically and
     * instantly (no LLM needed) — status, timing, handler, or whereabouts.
     */
    private function isDeterministicIntent(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'when', 'how long', 'ready', 'finish', 'done', 'complete', 'eta', 'days', 'time', 'wait', 'soon',
            'who', 'handler', 'handling', 'assigned', 'staff',
            'where', 'status', 'location', 'update', 'progress', 'stage', 'stand',
        ]);
    }

    /** Whether the question is asking what documents/requirements are needed. */
    private function asksAboutRequirements(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'requirement', 'require', 'what do i need', 'need to bring', 'documents needed',
            'what to bring', 'bring', 'checklist', 'prepare', 'submit', 'documents for',
        ]);
    }

    /**
     * Resolve the requirement checklist the question is about: a service named
     * in the question if one matches, otherwise this document's own type.
     *
     * @return array{service:string, is_current:bool, items:array<int, array{label:string, mandatory:bool}>}|null
     */
    private function resolveRequirements(string $question, Document $document): ?array
    {
        $types = RequestType::with('requirements')->get(['id', 'name']);
        if ($types->isEmpty()) {
            return null;
        }

        $q = Str::lower($question);

        // A service explicitly named in the question wins; longest name first so
        // "Mayor's Permit" is preferred over a bare "Permit"-style partial.
        $match = $types
            ->sortByDesc(fn (RequestType $t): int => Str::length($t->name))
            ->first(fn (RequestType $t): bool => Str::contains($q, Str::lower($t->name)));

        // Otherwise, answer for the document the citizen is looking at.
        $match ??= $types->first(
            fn (RequestType $t): bool => Str::lower($t->name) === Str::lower((string) $document->document_type)
        );

        if (! $match) {
            return null;
        }

        return [
            'service' => $match->name,
            'is_current' => Str::lower($match->name) === Str::lower((string) $document->document_type),
            'items' => $match->requirements
                ->map(fn ($r): array => ['label' => $r->label, 'mandatory' => (bool) $r->is_mandatory])
                ->all(),
        ];
    }

    /**
     * @param  array{service:string, is_current:bool, items:array<int, array{label:string, mandatory:bool}>}  $req
     */
    private function requirementsAnswer(array $req): string
    {
        if (empty($req['items'])) {
            return "I don't have a requirements checklist on file for {$req['service']}. Please contact the handling office to confirm what to prepare.";
        }

        $mandatory = array_values(array_filter($req['items'], fn (array $i): bool => $i['mandatory']));
        $optional = array_values(array_filter($req['items'], fn (array $i): bool => ! $i['mandatory']));

        $lead = $req['is_current']
            ? "For your {$req['service']}, you'll typically need:"
            : "For a {$req['service']}, you'll typically need:";

        $lines = array_map(fn (array $i): string => '• '.$i['label'], $mandatory ?: $req['items']);
        $text = $lead."\n".implode("\n", $lines);

        if ($mandatory && $optional) {
            $text .= "\nOptional / if applicable:\n".implode("\n", array_map(fn (array $i): string => '• '.$i['label'], $optional));
        }

        return $text."\nRequirements can vary by case — the handling office confirms the final list.";
    }

    /**
     * Build the grounding context, both as prompt text (for the LLM) and as a
     * structured array (for the rule-based fallback).
     *
     * @return array{text:string, data:array<string,mixed>}
     */
    private function facts(Document $document): array
    {
        $stage = $document->statusEnum();

        // Progress through the linear flow (Pending → … → Completed).
        $flow = collect(DocumentStatus::flow())->map(function (DocumentStatus $s) use ($stage): array {
            $pos = $s->position();
            $cur = $stage->position();
            $state = match (true) {
                $stage === DocumentStatus::Completed => 'done',
                $cur === 0 => 'upcoming', // off-line (Returned)
                $pos < $cur => 'done',
                $pos === $cur => 'current',
                default => 'upcoming',
            };

            return ['name' => $s->label(), 'stage' => $state];
        });

        // Predicted completion, derived from real timestamps of similar past
        // documents. Only surfaced while the document is still in progress.
        $prediction = $this->predictor->predict($document);
        $etaReadyBy = null;
        $etaDays = null;
        if (($prediction['available'] ?? false)
            && $stage !== DocumentStatus::Completed
            && ! empty($prediction['eta'])) {
            $etaReadyBy = $prediction['eta']->format('M d, Y');
            $etaDays = max(1, (int) ceil($prediction['remaining_hours'] / 24));
        }

        $data = [
            'tracking_number' => $document->tracking_number,
            'document_type' => $document->document_type,
            'applicant' => $document->citizen_name,
            'status_label' => $stage->label(),
            'handler' => $document->assignedTo?->name,
            'submitted' => $document->created_at?->format('M d, Y'),
            'last_update' => ($document->status_changed_at ?? $document->updated_at)?->format('M d, Y'),
            'completed_on' => $document->completed_at ? $document->completed_at->format('M d, Y') : null,
            'eta_ready_by' => $etaReadyBy,
            'eta_days' => $etaDays,
            'requirements' => $this->ownRequirementLabels($document),
            'flow' => $flow,
            'is_completed' => $stage === DocumentStatus::Completed,
        ];

        return ['text' => $this->factsToText($data), 'data' => $data];
    }

    /**
     * Requirement labels for THIS document's own type (used to ground the LLM
     * for requirement questions about the document the citizen is viewing).
     *
     * @return array<int, string>
     */
    private function ownRequirementLabels(Document $document): array
    {
        $type = RequestType::with('requirements')
            ->whereRaw('LOWER(name) = ?', [Str::lower((string) $document->document_type)])
            ->first();

        return $type ? $type->requirements->pluck('label')->all() : [];
    }

    private function factsToText(array $d): string
    {
        $lines = [
            "Tracking number: {$d['tracking_number']}",
            "Document type: {$d['document_type']}",
            'Applicant: '.($d['applicant'] ?? 'N/A'),
            "Submitted: {$d['submitted']}",
            "Current status: {$d['status_label']}",
            'Handled by: '.($d['handler'] ?? 'Not yet assigned to a staff member'),
            'Last updated: '.($d['last_update'] ?? 'N/A'),
        ];

        if (! empty($d['requirements'])) {
            $lines[] = "Requirements for {$d['document_type']}: ".implode('; ', $d['requirements']);
        }

        if ($d['flow']->isNotEmpty()) {
            $flow = $d['flow']->map(fn ($s): string => "{$s['name']} ({$s['stage']})")->implode(' > ');
            $lines[] = "Progress: {$flow}";
        }

        if ($d['eta_ready_by']) {
            $lines[] = "Estimated ready by: {$d['eta_ready_by']} (about {$d['eta_days']} day(s) from now, estimated from how long similar past documents took)";
        }

        if ($d['completed_on']) {
            $lines[] = "Completed on: {$d['completed_on']}";
        }

        $lines[] = "Today's date: ".Date::now()->format('M d, Y');

        return implode("\n", $lines);
    }

    private function systemPrompt(string $facts): string
    {
        // Deliberately short: fewer prompt tokens = faster prefill on CPU.
        return <<<PROMPT
        You are a warm, concise assistant for the SPeEdtracQR government document tracker. Answer the citizen using ONLY the facts below. If it is not in the facts, say you don't have that info and suggest contacting the handling office. Never invent details. Reply in 1-2 short sentences.

        FACTS:
        {$facts}
        PROMPT;
    }

    /**
     * Deterministic answer used when no LLM is available. Keyword-aware so it
     * still feels responsive to the citizen's question.
     */
    private function ruleBasedAnswer(array $d, string $question): string
    {
        $tn = $d['tracking_number'];
        $q = Str::lower($question);

        if ($d['is_completed']) {
            return "Good news — document {$tn} is already completed and ready. If you haven't received it yet, please contact the office that handled it.";
        }

        $handler = $d['handler']
            ? "It is being handled by {$d['handler']}."
            : 'It has not been assigned to a staff member yet.';

        $asksWhen = Str::contains($q, ['when', 'how long', 'ready', 'finish', 'done', 'complete', 'eta', 'days', 'time', 'wait', 'soon']);
        if ($asksWhen) {
            if ($d['eta_ready_by']) {
                return "Based on how long similar {$d['document_type']} requests have taken, document {$tn} is likely ready by around {$d['eta_ready_by']} — about {$d['eta_days']} day(s) from now. It is currently {$this->lc($d['status_label'])}. This is an estimate and may change.";
            }

            return "Document {$tn} ({$d['document_type']}) is currently {$this->lc($d['status_label'])}, but I don't have enough history yet to estimate a completion date. {$handler}";
        }

        $asksWho = Str::contains($q, ['who', 'handler', 'handling', 'assigned', 'staff']);
        if ($asksWho) {
            return "Document {$tn} ({$d['document_type']}) is {$this->lc($d['status_label'])}. {$handler}";
        }

        return "Document {$tn} ({$d['document_type']}) is currently {$this->lc($d['status_label'])}. {$handler}";
    }

    private function lc(string $label): string
    {
        return Str::lower($label);
    }
}
