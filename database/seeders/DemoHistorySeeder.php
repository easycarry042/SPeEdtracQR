<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentRouteStep;
use App\Models\DocumentScan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeds realistic historical document flow so the PredictiveAnalytics engine has
 * something to learn from in a demo (the real DB only has a handful of records).
 *
 * Re-runnable: every record it creates is tagged with remarks = DEMO_SEED and
 * wiped on the next run. NOT wired into DatabaseSeeder — run explicitly:
 *
 *     php artisan db:seed --class=DemoHistorySeeder
 */
class DemoHistorySeeder extends Seeder
{
    private const MARKER = 'DEMO_SEED';

    private const COMPLETED_COUNT = 48;

    /** Typical (median) hours a document spends at each department. */
    private array $baseline = [1 => 1.0, 2 => 30, 3 => 50, 4 => 14, 5 => 6];

    /** Department chain per document type (matches RoutingRuleSeeder). */
    private array $chains = [
        'Business Permit' => [1, 2, 3, 4, 5],
        'Barangay Clearance' => [1, 4, 5],
    ];

    private array $citizens = [
        'Maria Santos', 'Jose Rivera', 'Ana Cruz', 'Pedro Reyes', 'Liza Bautista',
        'Mark dela Cruz', 'Grace Lim', 'Ramon Aquino', 'Cecilia Tan', 'Noel Garcia',
    ];

    public function run(): void
    {
        $this->clearPrevious();

        $users = User::pluck('id');
        if ($users->isEmpty()) {
            $this->command?->warn('No users found — run UserSeeder/TeamUsersSeeder first.');

            return;
        }
        $creator = $users->first();

        for ($i = 0; $i < self::COMPLETED_COUNT; $i++) {
            $type = $i % 3 === 0 ? 'Barangay Clearance' : 'Business Permit';
            $chain = $this->chains[$type];

            // 0 (oldest) → 1 (newest); drives the Engineering bottleneck trend.
            $recency = $i / (self::COMPLETED_COUNT - 1);
            $daysAgo = (int) round(90 - $recency * 85); // 90 → 5 days ago

            $cursor = Carbon::now()->subDays($daysAgo)->setTime(8 + mt_rand(0, 3), mt_rand(0, 59));
            $firstIn = $cursor->copy();

            $doc = $this->makeDocument($type, $this->citizens[$i % count($this->citizens)], $creator, $firstIn);
            $doc->syncRouteSteps($chain);

            $lastOut = $cursor->copy();
            foreach ($chain as $deptId) {
                $dwellMin = (int) round($this->dwellFor($deptId, $recency) * 60);
                $inAt = $cursor->copy();
                $outAt = $cursor->copy()->addMinutes($dwellMin);

                $this->scan($doc, $deptId, 'in', $inAt, $users);
                $this->scan($doc, $deptId, 'out', $outAt, $users);

                $lastOut = $outAt;
                $cursor = $outAt->copy()->addMinutes(mt_rand(10, 90)); // transfer gap
            }

            $this->finalize($doc, [
                'status' => 'completed',
                'current_department_id' => end($chain),
                'completed_at' => $lastOut,
            ], $firstIn);
        }

        $this->seedLiveDocuments($creator, $users);

        $this->command?->info('Seeded '.self::COMPLETED_COUNT.' completed + 4 in-transit demo documents (incl. 1 anomaly).');
    }

    /** A few documents still in flight — including one deliberately stuck for the anomaly demo. */
    private function seedLiveDocuments($creator, $users): void
    {
        // [type, current department, hours already spent at current department]
        $live = [
            ['Business Permit', 2, 4],     // normal — fresh at Accounting
            ['Business Permit', 3, 32],    // normal — mid-stay at Engineering
            ['Business Permit', 3, 150],   // ANOMALY — stuck at Engineering (SLA 72h)
            ['Barangay Clearance', 4, 6],  // normal — at Mayors Office
        ];

        foreach ($live as [$type, $currentDept, $hoursAt]) {
            $chain = $this->chains[$type];
            $idx = array_search($currentDept, $chain);
            $inAt = Carbon::now()->subHours($hoursAt);

            // Walk the already-completed stops backwards from the current IN.
            $t = $inAt->copy();
            $priorScans = [];
            foreach (array_reverse(array_slice($chain, 0, $idx)) as $deptId) {
                $outAt = $t->copy()->subMinutes(mt_rand(10, 90));
                $inPrev = $outAt->copy()->subMinutes((int) round($this->dwellFor($deptId, 1.0) * 60));
                $priorScans[] = [$deptId, 'out', $outAt];
                $priorScans[] = [$deptId, 'in', $inPrev];
                $t = $inPrev->copy();
            }
            $firstIn = $t->copy();

            $doc = $this->makeDocument($type, 'Live Demo Citizen', $creator, $firstIn);
            $doc->syncRouteSteps($chain);

            foreach (array_reverse($priorScans) as [$deptId, $action, $when]) {
                $this->scan($doc, $deptId, $action, $when, $users);
            }
            $this->scan($doc, $currentDept, 'in', $inAt, $users);

            $this->finalize($doc, [
                'status' => 'in_transit',
                'current_department_id' => $currentDept,
            ], $firstIn);
        }
    }

    private function dwellFor(int $deptId, float $recency): float
    {
        $dwell = $this->baseline[$deptId] * (mt_rand(60, 140) / 100); // ±40% noise

        if ($deptId === 3) {
            $dwell *= 1 + $recency * 0.6; // Engineering degrades over time → bottleneck
        }

        return max(0.1, $dwell);
    }

    private function makeDocument(string $type, string $citizen, $creatorId, Carbon $createdAt): Document
    {
        return Document::create([
            'tracking_number' => $this->trackingNumber($createdAt),
            'document_type' => $type,
            'citizen_name' => $citizen,
            'citizen_contact' => '09'.mt_rand(100000000, 999999999),
            'purpose' => 'Demo seeded record',
            'status' => 'in_transit',
            'created_by' => $creatorId,
            'remarks' => self::MARKER,
        ]);
    }

    private function scan(Document $doc, int $deptId, string $action, Carbon $at, $users): void
    {
        // Only scanned_at matters to the analytics engine; it is fillable so it is set exactly.
        DocumentScan::create([
            'document_id' => $doc->id,
            'department_id' => $deptId,
            'scanned_by' => $users->random(),
            'action' => $action,
            'scanned_at' => $at,
            'remarks' => self::MARKER,
        ]);
    }

    /** Apply final state and backdate created_at/updated_at (not mass-assignable). */
    private function finalize(Document $doc, array $attributes, Carbon $createdAt): void
    {
        $doc->fill($attributes);
        $doc->created_at = $createdAt;
        $doc->updated_at = $attributes['completed_at'] ?? $createdAt;
        $doc->timestamps = false;
        $doc->saveQuietly();
        $doc->timestamps = true;
    }

    private function trackingNumber(Carbon $date): string
    {
        do {
            $candidate = 'SPD-'.$date->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Document::withTrashed()->where('tracking_number', $candidate)->exists());

        return $candidate;
    }

    private function clearPrevious(): void
    {
        $ids = Document::withTrashed()->where('remarks', self::MARKER)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        DocumentScan::whereIn('document_id', $ids)->delete();
        DocumentRouteStep::whereIn('document_id', $ids)->delete();
        Document::withTrashed()->whereIn('id', $ids)->forceDelete();
    }
}
