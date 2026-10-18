<?php

namespace Tests\Unit\Support;

use App\Models\Document;
use App\Support\SlaWatch;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The at-risk arithmetic, on unsaved models so no database is needed.
 *
 * "At risk" has to mean one thing: the staff dashboard lists it and the hourly
 * SLA sweep emails about it, and a request that is amber on one must not be
 * green on the other.
 */
class SlaWatchTest extends TestCase
{
    /** In Review carries a 48h budget (DocumentStatus::slaHours). */
    private function inReview(int $hoursAgo): Document
    {
        return new Document([
            'tracking_number' => 'SPD-SLA-'.$hoursAgo,
            'document_type' => 'Business Permit',
            'status' => 'in_review',
            'status_changed_at' => now()->subHours($hoursAgo),
        ]);
    }

    public function test_it_measures_the_stage_budget_from_the_last_status_change(): void
    {
        $document = SlaWatch::annotate($this->inReview(36));

        $this->assertNotNull($document);
        $this->assertSame(48, $document->sla_budget_hours);
        $this->assertSame(36, $document->sla_elapsed_hours);
        $this->assertSame(12, $document->sla_remaining_hours);
        $this->assertFalse($document->sla_overdue);
        $this->assertSame(75, SlaWatch::percentUsed($document));
    }

    public function test_it_reports_an_overrun_rather_than_a_negative_remainder(): void
    {
        $document = SlaWatch::annotate($this->inReview(60));

        $this->assertTrue($document->sla_overdue);
        $this->assertSame(0, $document->sla_remaining_hours);
        // The bar caps; the overrun is reported in hours beside it.
        $this->assertSame(100, SlaWatch::percentUsed($document));
    }

    /** A stage with no budget has no standing to report — not a zeroed one. */
    public function test_stages_without_an_sla_are_not_measured(): void
    {
        foreach (['completed', 'returned', 'on_hold', 'denied'] as $status) {
            $document = $this->inReview(999);
            $document->status = $status;

            $this->assertNull(
                SlaWatch::annotate($document),
                $status.' has no SLA budget, so it cannot be at risk'
            );
        }
    }

    public function test_at_risk_starts_at_three_quarters_of_the_budget(): void
    {
        // 35h of 48h is 73% — under the line; 36h is exactly 75%.
        $atRisk = SlaWatch::atRisk(new Collection([
            $this->inReview(35),
            $this->inReview(36),
        ]));

        $this->assertCount(1, $atRisk);
        $this->assertSame(36, $atRisk->first()->sla_elapsed_hours);
    }

    /**
     * Ordering by hours left put every overdue request at zero and so left the
     * worst offenders in whatever order they were queried in.
     */
    public function test_it_orders_overdue_requests_by_how_far_over_they_are(): void
    {
        $atRisk = SlaWatch::atRisk(new Collection([
            $this->inReview(40),
            $this->inReview(90),
            $this->inReview(50),
        ]));

        $this->assertSame([90, 50, 40], $atRisk->pluck('sla_elapsed_hours')->all());
        // All three past a 48h budget, so hours-left alone could not rank them.
        $this->assertSame([0, 0, 8], $atRisk->pluck('sla_remaining_hours')->all());
    }

    /**
     * Stages carry different budgets (Pending 24h, In Progress 72h), so the
     * ranking has to compare time-to-breach and not the budget each request
     * happens to have been given. An overdue Pending outranks an In Progress
     * with hours still to run, however much of its own budget that one has
     * already spent.
     */
    public function test_it_ranks_across_stages_by_time_to_breach(): void
    {
        $overduePending = new Document([
            'tracking_number' => 'SPD-SLA-PENDING',
            'document_type' => 'Cedula',
            'status' => 'pending',              // 24h budget, 30h in → 6h over
            'status_changed_at' => now()->subHours(30),
        ]);

        $tightInProgress = new Document([
            'tracking_number' => 'SPD-SLA-PROGRESS',
            'document_type' => 'Cedula',
            'status' => 'in_progress',          // 72h budget, 66h in → 6h left
            'status_changed_at' => now()->subHours(66),
        ]);

        $atRisk = SlaWatch::atRisk(new Collection([$tightInProgress, $overduePending]));

        $this->assertSame(
            ['SPD-SLA-PENDING', 'SPD-SLA-PROGRESS'],
            $atRisk->pluck('tracking_number')->all()
        );
        $this->assertSame([true, false], $atRisk->pluck('sla_overdue')->all());
    }
}
