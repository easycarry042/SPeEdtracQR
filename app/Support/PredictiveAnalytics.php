<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentScan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Self-hosted predictive analytics for document flow.
 *
 * Learns from historical scan timings (no external service, no paid API) to:
 *   - predict a document's completion date,
 *   - forecast which departments are becoming bottlenecks,
 *   - flag documents moving abnormally slowly (anomalies).
 *
 * It is deliberately statistical rather than a black-box model: every number is
 * explainable to a citizen or an auditor, and it degrades gracefully when there
 * is little history (falling back to each department's configured SLA). The
 * public surface is small so it can later be swapped for a trained ML model.
 */
class PredictiveAnalytics
{
    /** Department SLA is an upper bound; documents typically finish well before it. */
    private const SLA_FALLBACK_FACTOR = 0.6;

    /** Minimum samples before we trust an IQR-based anomaly threshold over the SLA. */
    private const MIN_SAMPLES_FOR_IQR = 4;

    /** @var Collection<int, array{department_id:int, document_type:?string, hours:float, at:Carbon}>|null */
    private ?Collection $samples = null;

    /**
     * Every completed "stay" at a department: the hours between an IN scan and the
     * document's next scan (the OUT that hands it onward). This is the raw training
     * data for all predictions below.
     */
    public function dwellSamples(): Collection
    {
        if ($this->samples !== null) {
            return $this->samples;
        }

        $types = Document::withTrashed()->pluck('document_type', 'id');

        $scans = DocumentScan::query()
            ->whereNotNull('scanned_at')
            ->orderBy('document_id')
            ->orderBy('scanned_at')
            ->get(['document_id', 'department_id', 'action', 'scanned_at']);

        $samples = collect();

        foreach ($scans->groupBy('document_id') as $documentId => $docScans) {
            $list = $docScans->values();

            for ($i = 0; $i < $list->count() - 1; $i++) {
                $scan = $list[$i];
                if ($scan->action !== 'in') {
                    continue;
                }

                $next = $list[$i + 1];
                $hours = abs($scan->scanned_at->diffInMinutes($next->scanned_at)) / 60;

                if ($hours <= 0) {
                    continue;
                }

                $samples->push([
                    'department_id' => (int) $scan->department_id,
                    'document_type' => $types[$documentId] ?? null,
                    'hours' => round($hours, 3),
                    'at' => $scan->scanned_at,
                ]);
            }
        }

        return $this->samples = $samples;
    }

    /**
     * Hours a document of $type typically spends at $departmentId, by median.
     * Falls back from (department + type) → (department only) → null.
     */
    public function typicalDwellHours(int $departmentId, ?string $type = null): ?float
    {
        $atDept = $this->dwellSamples()->where('department_id', $departmentId);

        $scoped = $type
            ? $atDept->where('document_type', $type)
            : $atDept;

        if ($scoped->isEmpty()) {
            $scoped = $atDept; // relax the type filter before giving up
        }

        if ($scoped->isEmpty()) {
            return null;
        }

        return self::percentile($scoped->pluck('hours')->all(), 50);
    }

    public function sampleCount(int $departmentId, ?string $type = null): int
    {
        $atDept = $this->dwellSamples()->where('department_id', $departmentId);
        $scoped = $type ? $atDept->where('document_type', $type) : $atDept;

        return ($scoped->isEmpty() ? $atDept : $scoped)->count();
    }

    private function slaFallbackHours(?int $slaHours): ?float
    {
        return $slaHours ? $slaHours * self::SLA_FALLBACK_FACTOR : null;
    }

    /**
     * Predict when a document will be completed, based on the median time similar
     * documents have spent at each remaining department on its route.
     *
     * @return array{available:bool, eta:?Carbon, confidence:string, based_on:int, remaining_hours:float}
     */
    public function predictCompletion(Document $document): array
    {
        if ($document->status === 'completed') {
            return [
                'available' => true,
                'eta' => $document->completed_at,
                'confidence' => 'actual',
                'based_on' => 0,
                'remaining_hours' => 0,
            ];
        }

        $chain = $document->getRoutingChain();
        if ($chain->isEmpty()) {
            return $this->unavailablePrediction();
        }

        $departments = $chain->keyBy('id');
        $ids = $chain->pluck('id')->values();

        $currentId = (int) $document->current_department_id;
        $startIndex = $ids->search($currentId);
        if ($startIndex === false) {
            $startIndex = 0; // not yet checked in — assume the whole route remains
        }

        $lastInElapsed = $this->hoursAtCurrentDepartment($document, $currentId);

        $remainingHours = 0.0;
        $totalSamples = 0;
        $usedFallback = false;

        foreach ($ids->slice($startIndex)->values() as $offset => $deptId) {
            $type = $document->document_type;
            $typical = $this->typicalDwellHours($deptId, $type);

            if ($typical === null) {
                $typical = $this->slaFallbackHours($departments[$deptId]->sla_hours ?? null) ?? 0.0;
                $usedFallback = true;
            } else {
                $totalSamples += $this->sampleCount($deptId, $type);
            }

            if ($offset === 0 && $lastInElapsed !== null) {
                // Already partway through the current stop.
                $remainingHours += max(0, $typical - $lastInElapsed);
            } else {
                $remainingHours += $typical;
            }
        }

        return [
            'available' => true,
            'eta' => Carbon::now()->addMinutes((int) round($remainingHours * 60)),
            'confidence' => $this->confidenceFor($totalSamples, $usedFallback),
            'based_on' => $totalSamples,
            'remaining_hours' => round($remainingHours, 2),
        ];
    }

    /**
     * Per-department flow health: average/median dwell vs SLA, recent trend, and
     * how many documents are queued there right now. Drives bottleneck forecasting.
     *
     * @param  array<int>|null  $departmentIds  limit to these departments (e.g. for a scoped user)
     * @return Collection<int, array<string, mixed>>
     */
    public function bottlenecks(?array $departmentIds = null): Collection
    {
        $departments = Department::query()
            ->when($departmentIds, fn ($q) => $q->whereIn('id', $departmentIds))
            ->get();

        $currentLoads = Document::query()
            ->where('status', 'in_transit')
            ->whereNotNull('current_department_id')
            ->selectRaw('current_department_id, COUNT(*) as total')
            ->groupBy('current_department_id')
            ->pluck('total', 'current_department_id');

        return $departments->map(function (Department $dept) use ($currentLoads) {
            $samples = $this->dwellSamples()
                ->where('department_id', $dept->id)
                ->sortBy(fn ($s) => $s['at']?->getTimestamp() ?? 0)
                ->values();

            $hours = $samples->pluck('hours');
            $median = self::percentile($hours->all(), 50);
            $avg = $hours->avg();
            $load = (int) ($currentLoads[$dept->id] ?? 0);
            $sla = $dept->sla_hours;

            $reference = $median ?? $this->slaFallbackHours($sla);
            $ratio = ($reference !== null && $sla) ? round($reference / $sla, 2) : null;

            return [
                'department' => $dept,
                'samples_count' => $samples->count(),
                'avg_hours' => $avg !== null ? round($avg, 1) : null,
                'median_hours' => $median !== null ? round($median, 1) : null,
                'sla_hours' => $sla,
                'ratio' => $ratio,
                'trend' => $this->trendOf($hours->all()),
                'current_load' => $load,
                'level' => $this->bottleneckLevel($ratio, $load),
            ];
        })
            ->sortByDesc(fn ($row) => [$this->levelRank($row['level']), $row['ratio'] ?? 0, $row['current_load']])
            ->values();
    }

    /**
     * If this in-transit document is stuck at its current department far longer
     * than peers, describe the anomaly; otherwise null.
     *
     * @return array{type:string, elapsed_hours:float, expected_hours:?float, threshold_hours:float, over_by_hours:float, severity:string}|null
     */
    public function detectAnomaly(Document $document): ?array
    {
        if ($document->status === 'completed' || ! $document->current_department_id) {
            return null;
        }

        $currentId = (int) $document->current_department_id;
        $elapsed = $this->hoursAtCurrentDepartment($document, $currentId);
        if ($elapsed === null) {
            return null;
        }

        $type = $document->document_type;
        $hours = $this->dwellSamples()
            ->where('department_id', $currentId)
            ->when($type, fn ($c) => $c->where('document_type', $type))
            ->pluck('hours')
            ->all();

        $sla = optional($document->currentDepartment)->sla_hours
            ?? optional(Department::find($currentId))->sla_hours;

        if (count($hours) >= self::MIN_SAMPLES_FOR_IQR) {
            $q1 = self::percentile($hours, 25);
            $q3 = self::percentile($hours, 75);
            $iqr = $q3 - $q1;
            $threshold = $q3 + 1.5 * $iqr;
            $expected = self::percentile($hours, 50);
            // Never flag below the SLA — that is the contractual normal.
            if ($sla) {
                $threshold = max($threshold, $sla);
            }
        } elseif ($sla) {
            $threshold = (float) $sla;
            $expected = $this->slaFallbackHours($sla);
        } else {
            return null; // no basis to judge
        }

        if ($elapsed <= $threshold) {
            return null;
        }

        $overBy = $elapsed - $threshold;
        $severity = ($sla && $elapsed > 1.5 * max($threshold, $sla)) ? 'high' : 'medium';

        return [
            'type' => 'slow',
            'elapsed_hours' => round($elapsed, 1),
            'expected_hours' => $expected !== null ? round($expected, 1) : null,
            'threshold_hours' => round($threshold, 1),
            'over_by_hours' => round($overBy, 1),
            'severity' => $severity,
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────

    private function unavailablePrediction(): array
    {
        return [
            'available' => false,
            'eta' => null,
            'confidence' => 'none',
            'based_on' => 0,
            'remaining_hours' => 0,
        ];
    }

    private function hoursAtCurrentDepartment(Document $document, int $currentId): ?float
    {
        if (! $currentId) {
            return null;
        }

        $lastIn = $document->scans()
            ->where('action', 'in')
            ->where('department_id', $currentId)
            ->latest('scanned_at')
            ->first();

        if (! $lastIn || ! $lastIn->scanned_at) {
            return null;
        }

        return abs($lastIn->scanned_at->diffInMinutes(Carbon::now())) / 60;
    }

    private function confidenceFor(int $samples, bool $usedFallback): string
    {
        if ($samples === 0) {
            return 'estimate';
        }
        if ($samples >= 25 && ! $usedFallback) {
            return 'high';
        }
        if ($samples >= 8) {
            return 'medium';
        }

        return 'low';
    }

    /** @param  array<float>  $hours  ordered oldest → newest */
    private function trendOf(array $hours): string
    {
        $n = count($hours);
        if ($n < 4) {
            return 'flat';
        }

        $half = (int) floor($n / 2);
        $older = array_slice($hours, 0, $half);
        $recent = array_slice($hours, $half);

        $olderAvg = array_sum($older) / count($older);
        $recentAvg = array_sum($recent) / count($recent);

        if ($olderAvg <= 0) {
            return 'flat';
        }

        $change = ($recentAvg - $olderAvg) / $olderAvg;

        return match (true) {
            $change >= 0.15 => 'up',
            $change <= -0.15 => 'down',
            default => 'flat',
        };
    }

    private function bottleneckLevel(?float $ratio, int $load): string
    {
        if ($ratio === null) {
            return 'unknown';
        }
        if ($ratio >= 1.0 || ($ratio >= 0.85 && $load >= 3)) {
            return 'critical';
        }
        if ($ratio >= 0.75 || $load >= 3) {
            return 'warning';
        }

        return 'ok';
    }

    private function levelRank(string $level): int
    {
        return match ($level) {
            'critical' => 3,
            'warning' => 2,
            'ok' => 1,
            default => 0,
        };
    }

    /** Linear-interpolated percentile. @param  array<float>  $values */
    public static function percentile(array $values, float $p): ?float
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $n = count($values);
        if ($n === 1) {
            return (float) $values[0];
        }

        $rank = ($p / 100) * ($n - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);

        if ($low === $high) {
            return (float) $values[$low];
        }

        return $values[$low] + ($values[$high] - $values[$low]) * ($rank - $low);
    }
}
