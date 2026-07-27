<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Support\CompletionPredictor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompletionPredictorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze the clock so the day math is exact and assertable.
        Carbon::setTestNow('2026-07-27 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Seed a completed document whose turnaround (created → completed) is a
     * known number of hours.
     */
    private function seedCompleted(string $type, int $turnaroundHours, int $completedDaysAgo = 5): void
    {
        $completedAt = now()->subDays($completedDaysAgo);

        $doc = Document::create([
            'tracking_number' => 'SPD-DONE-'.uniqid(),
            'document_type' => $type,
            'status' => 'completed',
        ]);

        $doc->forceFill([
            'created_at' => $completedAt->copy()->subHours($turnaroundHours),
            'completed_at' => $completedAt,
        ])->save();
    }

    private function makeInProgress(string $type, ?Carbon $createdAt = null): Document
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-LIVE-'.uniqid(),
            'document_type' => $type,
            'status' => 'in_progress',
            'status_changed_at' => now(),
        ]);

        if ($createdAt) {
            $doc->forceFill(['created_at' => $createdAt])->save();
        }

        return $doc->fresh();
    }

    public function test_predicts_remaining_time_from_median_turnaround(): void
    {
        // Three Business Permits that each took 120h (5 days).
        foreach (range(1, 3) as $i) {
            $this->seedCompleted('Business Permit', 120);
        }

        // A fresh in-progress permit (elapsed ≈ 0h).
        $doc = $this->makeInProgress('Business Permit', now());

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertTrue($prediction['available']);
        $this->assertSame(3, $prediction['based_on']);
        $this->assertSame('medium', $prediction['confidence']);
        // Remaining = median(120) − elapsed(0) = 120h.
        $this->assertEqualsWithDelta(120.0, $prediction['remaining_hours'], 0.5);
        $this->assertTrue($prediction['eta']->equalTo(now()->copy()->addHours(120)));
    }

    public function test_median_ignores_outliers(): void
    {
        // Durations 48, 120, 120, 480 → median = (120 + 120) / 2 = 120h.
        $this->seedCompleted('Cedula', 48);
        $this->seedCompleted('Cedula', 120);
        $this->seedCompleted('Cedula', 120);
        $this->seedCompleted('Cedula', 480);

        $doc = $this->makeInProgress('Cedula', now());

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertEqualsWithDelta(120.0, $prediction['remaining_hours'], 0.5);
    }

    public function test_subtracts_time_already_elapsed(): void
    {
        foreach (range(1, 3) as $i) {
            $this->seedCompleted('Clearance', 120); // typical total 5 days
        }

        // This document was created 2 days (48h) ago → 3 days should remain.
        $doc = $this->makeInProgress('Clearance', now()->subHours(48));

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertEqualsWithDelta(72.0, $prediction['remaining_hours'], 0.5);
        $this->assertTrue($prediction['eta']->equalTo(now()->copy()->addHours(72)));
    }

    public function test_falls_back_to_sla_budget_without_history(): void
    {
        // No completed documents exist yet.
        $doc = $this->makeInProgress('Barangay Permit', now());

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertTrue($prediction['available']);
        $this->assertSame(0, $prediction['based_on']);
        // In Progress(72) + In Review(48) + Approved(24) = 144h × 0.6 factor.
        $this->assertEqualsWithDelta(86.4, $prediction['remaining_hours'], 0.5);
        $this->assertTrue($prediction['eta']->isFuture());
    }

    public function test_never_predicts_a_past_eta_for_overdue_documents(): void
    {
        foreach (range(1, 3) as $i) {
            $this->seedCompleted('Permit', 120); // typical 5 days
        }

        // Created 10 days ago — well past the typical turnaround.
        $doc = $this->makeInProgress('Permit', now()->subDays(10));

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertTrue($prediction['available']);
        $this->assertTrue($prediction['eta']->greaterThan(now()));
        $this->assertGreaterThanOrEqual(2.0, $prediction['remaining_hours']);
    }

    public function test_completed_document_reports_actual_date(): void
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-FIN-1',
            'document_type' => 'Cedula',
            'status' => 'completed',
        ]);
        $doc->forceFill(['completed_at' => now()->subDay()])->save();

        $prediction = (new CompletionPredictor)->predict($doc->fresh());

        $this->assertSame('actual', $prediction['confidence']);
        $this->assertTrue($prediction['eta']->equalTo(now()->subDay()));
    }

    public function test_denied_document_has_no_prediction(): void
    {
        $doc = Document::create([
            'tracking_number' => 'SPD-NO-1',
            'document_type' => 'Cedula',
            'status' => 'denied',
        ]);

        $prediction = (new CompletionPredictor)->predict($doc);

        $this->assertFalse($prediction['available']);
        $this->assertNull($prediction['eta']);
    }
}
