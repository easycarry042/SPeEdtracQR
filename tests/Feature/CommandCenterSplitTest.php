<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The command center reads citizen requests and internal (office-to-office)
 * requests on separate sides. Counted together, INT- requests sat in the
 * citizen overdue list, judged against citizen stage clocks.
 */
class CommandCenterSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $gso;

    private Department $mayor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');
        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true, 'internal_step_hours' => 48]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
    }

    private function dashboard(array $query = []): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('admin.dashboard', $query))->assertOk();
    }

    private function citizenRequest(string $tracking, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => 'Chairs Borrowing',
            'status' => 'pending',
            'department_id' => $this->gso->id,
            'status_changed_at' => now()->subDays(3),
        ], $attributes));
    }

    /** An internal request sitting at $at since $hoursAgo. */
    private function internalRequest(string $tracking, Department $at, int $hoursAgo, array $attributes = []): Document
    {
        $document = Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => 'Internal Request',
            'purpose' => 'Folding tables for the fair',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $this->mayor->id,
            'status_changed_at' => now()->subHours($hoursAgo),
        ], $attributes));

        $document->requestSteps()->create([
            'step_order' => 1,
            'department_id' => $at->id,
            'action' => 'Review and action',
            'status' => RequestStep::STATUS_CURRENT,
            'started_at' => now()->subHours($hoursAgo),
        ]);

        return $document;
    }

    // ── the citizen side ─────────────────────────────────────────────────

    public function test_internal_requests_stay_off_the_citizen_side(): void
    {
        $this->citizenRequest('SPD-CIT-1');
        $this->internalRequest('INT-LATE-1', $this->gso, 400);

        $response = $this->dashboard();

        $this->assertSame(1, $response->viewData('kpis')['total']['value']);
        $this->assertSame(1, $response->viewData('kpis')['overdue']['value']);
        $response->assertSee('SPD-CIT-1')->assertDontSee('INT-LATE-1');
        $this->assertNotContains('Internal Request', $response->viewData('documentTypes')->all());
    }

    public function test_the_page_offers_both_sides(): void
    {
        $this->dashboard()
            ->assertSee('Citizen requests')
            ->assertSee('Internal requests')
            ->assertSee('Requests filed');
    }

    public function test_a_request_that_reached_no_office_is_called_out(): void
    {
        $this->citizenRequest('SPD-LOST-1', ['department_id' => null, 'document_type' => 'Business Permit']);
        $this->citizenRequest('SPD-DONE-1', ['department_id' => null, 'status' => 'completed']);

        $response = $this->dashboard();

        $this->assertSame(['SPD-LOST-1'], $response->viewData('unrouted')->pluck('tracking_number')->all());
        $response->assertSee('1 request reached no office')
            ->assertSee(route('admin.request-types.index'), false);
    }

    public function test_offices_with_a_citizen_service_are_listed_even_when_idle(): void
    {
        RequestType::create(['name' => 'Business Permit', 'kind' => 'document', 'department_id' => $this->mayor->id, 'is_active' => true]);
        $this->citizenRequest('SPD-GSO-1');

        $volume = $this->dashboard()->viewData('departmentVolume');

        $this->assertSame(['General Services Office', 'Office of the Mayor'], $volume->pluck('label')->all());
        $this->assertSame([1, 0], $volume->pluck('value')->all());
    }

    public function test_staff_workload_lists_idle_staff_but_not_super_admins(): void
    {
        $busy = User::factory()->create(['name' => 'Busy Bee', 'is_active' => true])->assignRole('staff');
        User::factory()->create(['name' => 'Idle Ida', 'is_active' => true])->assignRole('staff');
        $this->citizenRequest('SPD-ASSIGNED-1', ['assigned_to' => $busy->id, 'status' => 'in_progress']);

        $names = $this->dashboard()->viewData('staffWorkload')->pluck('name')->all();

        $this->assertSame(['Busy Bee', 'Idle Ida'], $names);
        $this->assertNotContains($this->admin->name, $names);
    }

    public function test_the_average_says_how_many_requests_it_stands_on(): void
    {
        $this->citizenRequest('SPD-FAST-1', [
            'status' => 'completed',
            'created_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->dashboard()->assertSee('From 1 completed');
    }

    public function test_the_deadline_donut_is_gone(): void
    {
        $this->dashboard()->assertDontSee('Open Requests vs. Deadline');
    }

    // ── the internal side ────────────────────────────────────────────────

    public function test_the_internal_side_counts_only_internal_requests(): void
    {
        $this->citizenRequest('SPD-CIT-1');
        $this->internalRequest('INT-ONE-1', $this->gso, 5);
        $this->internalRequest('INT-BACK-1', $this->gso, 5, ['status' => 'returned']);

        $response = $this->dashboard(['view' => 'internal']);
        $kpis = $response->viewData('internalKpis');

        $this->assertSame(2, $kpis['filed']);
        $this->assertSame(1, $kpis['moving']);
        $this->assertSame(1, $kpis['returned']);
        $response->assertSee('Where they')->assertDontSee('SPD-CIT-1');
    }

    public function test_overdue_at_a_step_follows_the_offices_own_turnaround(): void
    {
        // GSO allows 48 hours; the Mayor's Office has the 72-hour default.
        $this->internalRequest('INT-GSO-LATE', $this->gso, 50);
        $this->internalRequest('INT-OM-FINE', $this->mayor, 50);

        $response = $this->dashboard(['view' => 'internal']);

        $this->assertSame(1, $response->viewData('internalKpis')['overdue']);
        $late = $response->viewData('internalWaiting')->firstWhere('overdue', true);
        $this->assertSame('INT-GSO-LATE', $late['document']->tracking_number);
    }

    public function test_a_returned_request_waits_on_the_filing_office_without_a_deadline(): void
    {
        $document = $this->internalRequest('INT-RET-1', $this->gso, 500, ['status' => 'returned']);
        $document->requestSteps()->update(['status' => RequestStep::STATUS_RETURNED, 'acted_at' => now()->subHours(500)]);

        $row = $this->dashboard(['view' => 'internal'])->viewData('internalWaiting')->first();

        $this->assertTrue($row['returned']);
        $this->assertFalse($row['overdue']);
        $this->assertSame('Office of the Mayor', $row['at']);
    }

    public function test_office_turnaround_averages_closed_hops(): void
    {
        $document = $this->internalRequest('INT-DONE-1', $this->gso, 10);
        $document->requestSteps()->update([
            'status' => RequestStep::STATUS_APPROVED,
            'started_at' => now()->subHours(10),
            'acted_at' => now()->subHours(4),
        ]);

        $row = $this->dashboard(['view' => 'internal'])->viewData('officeTurnaround')->first();

        $this->assertSame('General Services Office', $row['label']);
        $this->assertSame(6.0, $row['avg_hours']);
        $this->assertFalse($row['over']);
    }

    public function test_the_internal_export_reads_the_internal_side(): void
    {
        $this->internalRequest('INT-CSV-1', $this->gso, 5);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.dashboard.export', ['view' => 'internal']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Internal requests export', $csv);
        $this->assertStringContainsString('Moving through offices', $csv);
    }

    public function test_an_unknown_side_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard', ['view' => 'everything']))
            ->assertSessionHasErrors('view');
    }
}
