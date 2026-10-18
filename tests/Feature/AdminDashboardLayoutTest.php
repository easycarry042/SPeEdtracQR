<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What the command center shows, and in what order: the work that needs doing
 * comes before the charts, duplicates are gone, and nothing ranks or reports
 * on too little to mean anything.
 */
class AdminDashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->admin = User::factory()->create(['name' => 'Ada Admin'])->assignRole('super_admin');
    }

    private function dashboard(): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_the_page_reads_vitals_then_what_is_late_then_charts_then_history(): void
    {
        Document::create([
            'tracking_number' => 'SPD-ORDER-1',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $this->admin->id,
            'status_changed_at' => now()->subDays(3),
        ]);

        $this->dashboard()->assertSeeInOrder([
            'Requests filed',
            'Overdue',
            'Where requests are now',
            'Needs Attention',
            'Requests Filed vs. Completed',
            'Recent Activity',
        ]);
    }

    public function test_the_heatmap_is_gone(): void
    {
        // Twenty-six weeks of mostly empty squares, repeating the weekly
        // chart above it.
        $response = $this->dashboard()->assertDontSee('System throughput');

        $this->assertArrayNotHasKey('heatmap', $response->original->getData());
    }

    public function test_recent_activity_shows_work_not_sign_ins(): void
    {
        activity('auth')->causedBy($this->admin)->withProperties(['ip' => '127.0.0.1'])->log('User logged in');

        $this->actingAs($this->admin);
        Document::create([
            'tracking_number' => 'SPD-WORK-1',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $activity = $this->dashboard()->viewData('recentActivity');

        $this->assertNotContains('Signed In', $activity->pluck('action')->all());
        $this->assertContains('Request Created', $activity->pluck('action')->all());
    }

    public function test_activity_dates_read_unambiguously(): void
    {
        // "26-10-18" could be the 26th of October or 18 October 2026.
        Date::setTestNow('2026-10-18 18:50:00');
        $this->actingAs($this->admin);
        Document::create([
            'tracking_number' => 'SPD-DATE-1',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $this->dashboard()
            ->assertSee('Oct 18, 6:50 PM')
            ->assertDontSee('26-10-18');
    }

    public function test_staff_are_not_ranked_by_speed(): void
    {
        // A speed leaderboard rewards rushing a request through instead of
        // checking it, so "Fastest staff" was removed rather than tuned.
        $response = $this->dashboard()->assertDontSee('Fastest staff');

        $this->assertArrayNotHasKey('fastestStaff', $response->original->getData());
    }

    public function test_every_kpi_is_one_of_the_shared_tiles(): void
    {
        // Avg. Processing and Overdue used to be small white cards in a second
        // row under the panels, a second style of KPI on the same page.
        $this->dashboard()
            ->assertDontSee('an-kpis-rates', false)
            ->assertSeeInOrder(['Requests filed', 'Pending', 'Being worked on', 'Completed', 'Avg. Processing', 'Overdue'])
            ->assertSee('stat-avg-time.svg', false)
            ->assertSee('stat-overdue.svg', false);
    }

    public function test_every_panel_wears_the_same_card(): void
    {
        $content = $this->dashboard()->getContent();

        $this->assertStringNotContainsString('class="panel', $content);
        $this->assertStringNotContainsString('class="ph"', $content);
    }
}
