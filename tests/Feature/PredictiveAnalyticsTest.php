<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Support\PredictiveAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PredictiveAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Department $deptA;

    private Department $deptB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 6, 1, 9));

        $this->deptA = Department::create(['name' => 'Intake', 'sla_hours' => 4, 'email' => 'a@example.com']);
        $this->deptB = Department::create(['name' => 'Review', 'sla_hours' => 10, 'email' => 'b@example.com']);
        $this->user = User::factory()->create(['department_id' => $this->deptA->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A completed doc that spent $aHours at Intake then $bHours at Review. */
    private function completedDoc(float $aHours, float $bHours, Carbon $start): Document
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'completed',
            'current_department_id' => $this->deptB->id,
            'created_by' => $this->user->id,
        ]);
        $doc->syncRouteSteps([$this->deptA->id, $this->deptB->id]);

        $inA = $start->copy();
        $outA = $inA->copy()->addMinutes((int) ($aHours * 60));
        $inB = $outA->copy()->addMinutes(30);
        $outB = $inB->copy()->addMinutes((int) ($bHours * 60));

        foreach ([[$this->deptA->id, 'in', $inA], [$this->deptA->id, 'out', $outA],
            [$this->deptB->id, 'in', $inB], [$this->deptB->id, 'out', $outB]] as [$dept, $action, $at]) {
            $doc->scans()->create([
                'scanned_by' => $this->user->id,
                'department_id' => $dept,
                'action' => $action,
                'scanned_at' => $at,
            ]);
        }

        $doc->update(['completed_at' => $outB]);

        return $doc;
    }

    private function inTransitDoc(int $deptId, int $hoursAgo): Document
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $deptId,
            'created_by' => $this->user->id,
        ]);
        $doc->syncRouteSteps([$this->deptA->id, $this->deptB->id]);
        $doc->scans()->create([
            'scanned_by' => $this->user->id,
            'department_id' => $deptId,
            'action' => 'in',
            'scanned_at' => now()->subHours($hoursAgo),
        ]);

        return $doc;
    }

    public function test_percentile_interpolates(): void
    {
        $this->assertEqualsWithDelta(2.0, PredictiveAnalytics::percentile([1, 2, 3], 50), 0.001);
        $this->assertEqualsWithDelta(1.5, PredictiveAnalytics::percentile([1, 2], 50), 0.001);
        $this->assertNull(PredictiveAnalytics::percentile([], 50));
    }

    public function test_typical_dwell_is_median_of_history(): void
    {
        $start = now()->subDays(20);
        foreach ([2, 2, 2, 8] as $a) {
            $this->completedDoc($a, 6, $start->copy());
            $start->addDay();
        }

        $pa = new PredictiveAnalytics;
        // median of [2,2,2,8] = 2
        $this->assertEqualsWithDelta(2.0, $pa->typicalDwellHours($this->deptA->id, 'Business Permit'), 0.01);
        $this->assertSame(4, $pa->sampleCount($this->deptA->id, 'Business Permit'));
    }

    public function test_predicted_completion_sums_remaining_route(): void
    {
        $start = now()->subDays(20);
        for ($i = 0; $i < 5; $i++) {
            $this->completedDoc(2, 6, $start->copy());
            $start->addDay();
        }

        // Fresh document just checked in at Intake → expect ~2h (rest of Intake) + 6h (Review).
        $doc = $this->inTransitDoc($this->deptA->id, 0);
        $prediction = (new PredictiveAnalytics)->predictCompletion($doc);

        $this->assertTrue($prediction['available']);
        $this->assertEqualsWithDelta(8.0, $prediction['remaining_hours'], 0.5);
        $this->assertEqualsWithDelta(now()->addHours(8)->timestamp, $prediction['eta']->timestamp, 1800);
        $this->assertSame(10, $prediction['based_on']);
        $this->assertSame('medium', $prediction['confidence']);
    }

    public function test_anomaly_flags_stuck_document_and_ignores_normal_one(): void
    {
        $start = now()->subDays(20);
        for ($i = 0; $i < 5; $i++) {
            $this->completedDoc(2, 6, $start->copy());
            $start->addDay();
        }
        $pa = new PredictiveAnalytics;

        $stuck = $this->inTransitDoc($this->deptB->id, 100); // normal Review dwell ~6h, SLA 10h
        $anomaly = $pa->detectAnomaly($stuck);
        $this->assertNotNull($anomaly);
        $this->assertSame('slow', $anomaly['type']);
        $this->assertSame('high', $anomaly['severity']);

        $normal = $this->inTransitDoc($this->deptB->id, 3);
        $this->assertNull($pa->detectAnomaly($normal));
    }

    public function test_bottlenecks_rank_by_pressure(): void
    {
        $start = now()->subDays(20);
        for ($i = 0; $i < 5; $i++) {
            // Review eats most of its 10h SLA (9h); Intake stays light (1h of 4h).
            $this->completedDoc(1, 9, $start->copy());
            $start->addDay();
        }

        $rows = (new PredictiveAnalytics)->bottlenecks();
        $top = $rows->first();
        $this->assertSame($this->deptB->id, $top['department']->id);
        $this->assertGreaterThanOrEqual(0.85, $top['ratio']);
    }
}
