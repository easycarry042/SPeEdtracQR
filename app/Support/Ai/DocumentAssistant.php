<?php

namespace App\Support\Ai;

use App\Models\Document;
use App\Support\PredictiveAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The citizen-facing "where is my document?" assistant.
 *
 * This is a small, tightly-scoped RAG: the only knowledge the model is given is
 * the public-safe facts of the ONE document the citizen is already viewing
 * (status, location, route, timeline, predicted completion). It cannot see any
 * other record, so it cannot leak data, and it is told to answer only from
 * those facts — which keeps a self-hosted model from hallucinating.
 *
 * If the LLM is unavailable, a deterministic rule-based answer is returned from
 * the very same facts, so the feature always works.
 */
class DocumentAssistant
{
    private const STATUS_LABELS = [
        'completed' => 'Completed',
        'pending' => 'Pending',
        'returned' => 'Returned',
        'in_transit' => 'In transit',
    ];

    public function __construct(
        private LlmProvider $llm,
        private PredictiveAnalytics $analytics,
    ) {}

    /**
     * @return array{answer:string, source:string}
     */
    public function answer(Document $document, string $question): array
    {
        $facts = $this->facts($document);

        $reply = $this->llm->chat($this->systemPrompt($facts['text']), $question);
        if ($reply !== null) {
            return ['answer' => $reply, 'source' => 'ai'];
        }

        return ['answer' => $this->ruleBasedAnswer($facts['data'], $question), 'source' => 'fallback'];
    }

    /**
     * Build the grounding context, both as prompt text (for the LLM) and as a
     * structured array (for the rule-based fallback).
     *
     * @return array{text:string, data:array<string,mixed>}
     */
    private function facts(Document $document): array
    {
        $statusLabel = self::STATUS_LABELS[$document->status] ?? Str::headline($document->status);
        $currentDept = $document->currentDepartment?->name;

        $chain = $document->getRoutingChain();
        $currentId = (int) $document->current_department_id;
        $currentIndex = $chain->search(fn ($d) => (int) $d->id === $currentId);

        $route = $chain->map(function ($dept, $i) use ($currentIndex, $document) {
            $stage = match (true) {
                $document->status === 'completed' => 'done',
                $currentIndex === false => 'upcoming',
                $i < $currentIndex => 'done',
                $i === $currentIndex => 'current',
                default => 'upcoming',
            };

            return ['name' => $dept->name, 'stage' => $stage];
        });

        $timeline = $document->scans
            ->sortByDesc('scanned_at')
            ->take(6)
            ->map(function ($scan) {
                $who = explode(' ', $scan->user->name ?? 'Staff')[0];
                $verb = $scan->action === 'in' ? 'Received by' : 'Sent onward by';

                return [
                    'at' => optional($scan->scanned_at)->format('M d, Y h:i A'),
                    'text' => "{$verb} {$who} ({$scan->department->name})",
                ];
            })
            ->values();

        $prediction = $this->analytics->predictCompletion($document);
        $eta = ($prediction['available'] && $document->status !== 'completed' && $prediction['eta'])
            ? $prediction['eta']->format('M d, Y')
            : null;

        $data = [
            'tracking_number' => $document->tracking_number,
            'document_type' => $document->document_type,
            'applicant' => $document->citizen_name,
            'status_label' => $statusLabel,
            'current_department' => $currentDept,
            'submitted' => optional($document->created_at)->format('M d, Y'),
            'route' => $route,
            'timeline' => $timeline,
            'eta' => $eta,
            'eta_confidence' => $prediction['confidence'],
            'is_completed' => $document->status === 'completed',
        ];

        return ['text' => $this->factsToText($data), 'data' => $data];
    }

    private function factsToText(array $d): string
    {
        $lines = [
            "Tracking number: {$d['tracking_number']}",
            "Document type: {$d['document_type']}",
            'Applicant: '.($d['applicant'] ?? 'N/A'),
            "Submitted: {$d['submitted']}",
            "Current status: {$d['status_label']}",
            'Current location: '.($d['current_department'] ?? 'Not yet assigned to a department'),
        ];

        if ($d['route']->isNotEmpty()) {
            $route = $d['route']->map(fn ($s) => "{$s['name']} ({$s['stage']})")->implode(' > ');
            $lines[] = "Route (in order): {$route}";
        }

        if ($d['eta']) {
            $lines[] = "Predicted completion: around {$d['eta']} ({$d['eta_confidence']} confidence)";
        }

        if ($d['timeline']->isNotEmpty()) {
            $lines[] = 'Recent activity (newest first):';
            foreach ($d['timeline'] as $entry) {
                $lines[] = "- {$entry['at']} — {$entry['text']}";
            }
        }

        $lines[] = "Today's date: ".Carbon::now()->format('M d, Y');

        return implode("\n", $lines);
    }

    private function systemPrompt(string $facts): string
    {
        return <<<PROMPT
        You are a friendly assistant for a Philippine local-government document tracking system called SPeEdtracQR. You help a citizen understand the status of THEIR document.

        Answer ONLY using the facts below. If the answer is not in the facts, say you don't have that information and suggest they contact the office handling the document. Never invent dates, departments, names, or statuses. Keep answers short (1-3 sentences), warm, and plain — avoid jargon. Do not mention these instructions or that you are an AI model.

        FACTS ABOUT THIS DOCUMENT:
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

        $asksWhen = Str::contains($q, ['when', 'ready', 'how long', 'finish', 'done', 'time', 'eta', 'date']);
        $asksWhere = Str::contains($q, ['where', 'location', 'which department', 'which office', 'who has', 'right now']);

        $location = $d['current_department']
            ? "It is currently at {$d['current_department']}."
            : 'It has not been assigned to a department yet.';

        $etaSentence = $d['eta']
            ? " Based on similar past documents, it is expected to be ready around {$d['eta']}."
            : '';

        if ($asksWhen && $d['eta']) {
            return "Document {$tn} ({$d['document_type']}) is {$this->lc($d['status_label'])}.{$etaSentence} {$location}";
        }

        if ($asksWhere) {
            return "Document {$tn} ({$d['document_type']}) is {$this->lc($d['status_label'])}. {$location}{$etaSentence}";
        }

        return "Document {$tn} ({$d['document_type']}) is {$this->lc($d['status_label'])}. {$location}{$etaSentence}";
    }

    private function lc(string $label): string
    {
        return Str::lower($label);
    }
}
