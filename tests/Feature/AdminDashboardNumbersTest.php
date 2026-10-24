<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The command center's numbers have to agree with each other. On one screen
 * it used to report the same overdue work as 15 ("At Risk" tile), 0 (the
 * breakdown's "at-risk"), 12 (the attention table, which counted its own
 * capped rows) and 94% — beside an on-time rate of "100% · Healthy" — and its
 * four tiles mixed the selected range with all-time counts.
 */
class AdminDashboardNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->admin = User::factory()->create()->assignRole('super_admin');
    }

    private function document(string $tracking, string $status, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => 'Business Permit',
            'status' => $status,
            'created_by' => $this->admin->id,
            'status_changed_at' => now(),
        ], $attributes));
    }

    /** A pending request whose stage clock started $hours ago (Pending allows 24h). */
    private function pendingFor(string $tracking, float $hours): Document
    {
        return $this->document($tracking, 'pending', ['status_changed_at' => now()->subMinutes((int) round($hours * 60))]);
    }

    private function dashboard(array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard', $query))->assertOk();
    }

    public function test_every_overdue_figure_on_the_page_is_the_same_number(): void
    {
        foreach (range(1, 14) as $i) {
            $this->pendingFor('SPD-LATE-'.$i, 30);   // past the 24h Pending budget
        }
        $this->pendingFor('SPD-CLOSE-1', 20);       // at risk: past 75% of it
        $this->pendingFor('SPD-FRESH-1', 1);        // on time

        $response = $this->dashboard();

        $this->assertSame(14, $response->viewData('kpis')['overdue']['value']);
        $this->assertSame(1, $response->viewData('kpis')['at_risk']['value']);
        $this->assertCount(14, $response->viewData('atRisk'), 'the attention list is no longer capped before it is counted');

        // The table still shows only the worst rows, but states the real total.
        $response->assertSee('14 overdue · showing the 12 most overdue', false);
        $response->assertSee('+1 at risk');
    }

    public function test_the_export_uses_the_dashboards_words(): void
    {
        $this->pendingFor('SPD-CSV-LATE', 30);
        $this->pendingFor('SPD-CSV-CLOSE', 20);

        $csv = $this->actingAs($this->admin)->get(route('admin.dashboard.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Overdue,1', $csv);
        $this->assertStringContainsString('"At risk (not yet overdue)",1', $csv);
    }

    public function test_the_tiles_add_up_to_the_total_for_the_selected_range(): void
    {
        $this->document('SPD-NEW-P', 'pending');
        $this->document('SPD-NEW-R', 'in_review');
        $this->document('SPD-NEW-C', 'completed', ['completed_at' => now()]);
        $this->document('SPD-NEW-D', 'denied');

        // Filed before the 30-day window: it used to count in Pending (an
        // all-time number) while Total (the window) left it out.
        Date::setTestNow(now()->subDays(60));
        $this->document('SPD-OLD-P', 'pending');
        Date::setTestNow();

        $kpis = $this->dashboard()->viewData('kpis');

        $this->assertSame(4, $kpis['total']['value']);
        $this->assertSame(1, $kpis['pending']['value']);
        $this->assertSame(1, $kpis['in_progress']['value']);
        $this->assertSame(1, $kpis['completed']['value']);
        $this->assertSame(1, $kpis['denied']['value']);
        $this->assertSame(
            $kpis['total']['value'],
            $kpis['pending']['value'] + $kpis['in_progress']['value'] + $kpis['completed']['value'] + $kpis['denied']['value'],
        );

        // The overdue count is still "open right now", whenever filed.
        $this->assertSame(1, $kpis['overdue']['value']);
    }

    public function test_the_tile_row_says_what_it_counts(): void
    {
        $this->document('SPD-DEN-1', 'denied');

        $this->dashboard(['range' => 7])
            ->assertSee('Requests filed in the last 7 days, by where they are now')
            ->assertSee('1 denied')
            ->assertSee('Being worked on');
    }

    public function test_the_on_time_rate_and_its_health_pill_are_gone(): void
    {
        // Its 85% / 70% "healthy" cut-offs had no source, and it judged only
        // finished work — it read "100% · Healthy" beside fifteen overdue
        // requests. The Overdue tile answers the question it was asking.
        $response = $this->dashboard()
            ->assertDontSee('On-Time Rate')
            ->assertDontSee('HEALTHY');

        $this->assertArrayNotHasKey('on_time', $response->viewData('kpis'));
    }

    public function test_an_average_with_nothing_to_compare_to_shows_no_change(): void
    {
        // The previous window had no completions, so there is no average to
        // compare with; treating it as 0 used to print an arrow anyway.
        $quick = $this->document('SPD-QUICK-2', 'pending');
        $quick->update(['status' => 'completed', 'completed_at' => now(), 'status_changed_at' => now()]);

        $this->assertNull($this->dashboard()->viewData('kpis')['avg_processing']['delta']);
    }

    public function test_short_and_long_spans_are_readable(): void
    {
        $this->pendingFor('SPD-LONG-1', 24 + 482.4);   // 482.4h over its budget

        Date::setTestNow(now()->subMinutes(53));
        $quick = $this->document('SPD-QUICK-3', 'pending');
        Date::setTestNow();
        $quick->update(['status' => 'completed', 'completed_at' => now(), 'status_changed_at' => now()]);

        $this->dashboard()
            ->assertSee('+20d 2h')
            ->assertDontSee('482.4h')
            // Avg. Processing — it used to round 53 minutes down to "0d".
            ->assertSee('53m')
            ->assertDontSee('>0d<', false);
    }

    public function test_office_volume_prints_counts_for_the_selected_range(): void
    {
        $office = Department::create(['name' => 'Treasury Office', 'code' => 'TRE', 'is_active' => true]);

        foreach (range(1, 3) as $i) {
            $this->document('SPD-TRE-'.$i, 'pending', ['department_id' => $office->id]);
        }

        Date::setTestNow(now()->subDays(60));
        $this->document('SPD-TRE-OLD', 'pending', ['department_id' => $office->id]);
        Date::setTestNow();

        $response = $this->dashboard();

        $this->assertSame(3, $response->viewData('departmentVolume')->first()['value']);
        $response->assertSee('<span class="cc-bar-value">3</span>', false)
            ->assertDontSee('Share of the busiest office');
    }
}
