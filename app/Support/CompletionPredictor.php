<?php

namespace App\Support;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Self-hosted predictive completion-time estimator.
 *
 * Every number is derived from real database timestamps so it is fully
 * explainable to a citizen or an auditor — there is no black-box model:
 *
 *   1. It learns the typical end-to-end turnaround (created_at → completed_at)
 *      of past COMPLETED documents of the same document_type, using the MEDIAN
 *      so a few slow outliers do not skew the estimate.
 *   2. remaining_hours = typical_total − hours already elapsed for THIS document.
 *   3. When there is no history yet, it falls back to the sum of each remaining
 *      stage's configured SLA budget (see DocumentStatus::slaHours()).
 *
 * Confidence scales with how many similar past documents were available.
 */
class CompletionPredictor
{
    /** Samples needed before the median is treated as a confident estimate. */
    private const HIGH_CONFIDENCE_SAMPLES = 8;

    private const MEDIUM_CONFIDENCE_SAMPLES = 3;

    /** SLA budgets are an upper bound; documents usually finish well before them. */
    private const SLA_FALLBACK_FACTOR = 0.6;

    /** Never predict an ETA in the past — keep at least this much runway. */
    private const MIN_REMAINING_HOURS = 2;

    /**
     * @return array{available:bool, eta:?Carbon, confidence:string, based_on:int, remaining_hours:float}
     */
    public function predict(Document $document): array
    {
        $stage = $document->statusEnum();

        // Already finished — the "estimate" is simply the recorded fact.
        if ($stage === DocumentStatus::Completed) {
            return [
                'available' => true,
                'eta' => $document->completed_at,
                'confidence' => 'actual',
                'based_on' => 0,
                'remaining_hours' => 0.0,
            ];
        }

        // Denied documents never complete, so there is nothing to predict.
        if ($stage === DocumentStatus::Denied) {
            return $this->unavailable();
        }

        $samples = $this->historicalTurnaroundHours($document->document_type);
        $basedOn = $samples->count();

        if ($basedOn > 0) {
            $typicalTotalHours = $this->median($samples->all());
            $anchor = $document->created_at ?? $document->status_changed_at;
            $elapsedHours = $anchor ? $anchor->diffInHours(now()) : 0;
            $remainingHours = $typicalTotalHours - $elapsedHours;
            $confidence = match (true) {
                $basedOn >= self::HIGH_CONFIDENCE_SAMPLES => 'high',
                $basedOn >= self::MEDIUM_CONFIDENCE_SAMPLES => 'medium',
                default => 'low',
            };
        } else {
            // No history: budget the remaining stages from their configured SLAs.
            $remainingHours = $this->slaBudgetHours($stage);
            $confidence = 'low';

            if ($remainingHours === null) {
                return $this->unavailable();
            }
        }

        $remainingHours = max($remainingHours, self::MIN_REMAINING_HOURS);

        return [
            'available' => true,
            'eta' => now()->copy()->addHours((int) round($remainingHours)),
            'confidence' => $confidence,
            'based_on' => $basedOn,
            'remaining_hours' => round($remainingHours, 1),
        ];
    }

    /**
     * Turnaround hours (created_at → completed_at) of past completed documents,
     * preferring the same document_type and relaxing to all types when the type
     * has no history yet.
     *
     * @return Collection<int, float>
     */
    private function historicalTurnaroundHours(?string $type): Collection
    {
        $completed = Document::query()
            ->where('status', DocumentStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->whereNotNull('created_at')
            ->get(['document_type', 'created_at', 'completed_at']);

        $toHours = fn (Collection $docs): Collection => $docs
            ->map(fn (Document $d): float => (float) $d->created_at->diffInHours($d->completed_at))
            ->filter(fn (float $h): bool => $h > 0)
            ->values();

        $typed = $toHours($completed->where('document_type', $type));

        return $typed->isNotEmpty() ? $typed : $toHours($completed);
    }

    /**
     * Sum of the SLA budgets of the current stage and every forward stage still
     * ahead of it, discounted by the fallback factor. Null when no stage ahead
     * has an SLA (nothing to base a fallback on).
     */
    private function slaBudgetHours(DocumentStatus $stage): ?float
    {
        $flow = DocumentStatus::flow();
        $index = array_search($stage, $flow, true);
        $index = $index === false ? 0 : $index;

        $sum = 0;
        $hasBudget = false;

        foreach (array_slice($flow, $index) as $ahead) {
            $sla = $ahead->slaHours();
            if ($sla !== null) {
                $sum += $sla;
                $hasBudget = true;
            }
        }

        return $hasBudget ? $sum * self::SLA_FALLBACK_FACTOR : null;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * @return array{available:bool, eta:?Carbon, confidence:string, based_on:int, remaining_hours:float}
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'eta' => null,
            'confidence' => 'none',
            'based_on' => 0,
            'remaining_hours' => 0.0,
        ];
    }
}
