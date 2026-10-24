<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A supervisor's Reports: their office on the command-center page, citizen
 * and internal requests on separate sides. It replaced Analytics and the
 * Services report, whose numbers disagreed with the dashboard because they
 * counted every office's internal requests as this office's.
 */
class SupervisorReportsTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private Department $mayor;

    private User $head;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
        $this->head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $this->staff = User::factory()->create(['name' => 'Paolo Mendoza', 'department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
    }

    private function citizen(Department $office, string $tracking, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => $office->is($this->gso) ? 'Chairs Borrowing' : 'Business Permit',
            'status' => 'pending',
            'department_id' => $office->id,
            'status_changed_at' => now()->subDays(3),
        ], $attributes));
    }

    private function internal(string $tracking, Department $filedBy, Department $at): Document
    {
        $document = Document::create([
            'tracking_number' => $tracking,
            'document_type' => 'Internal Request',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $filedBy->id,
        ]);
        $document->requestSteps()->create([
            'step_order' => 1, 'department_id' => $at->id, 'action' => 'Review and action',
            'status' => RequestStep::STATUS_CURRENT, 'started_at' => now()->subHour(),
        ]);

        return $document;
    }

    public function test_the_citizen_side_counts_only_this_office(): void
    {
        $this->citizen($this->gso, 'SPD-GSO-1');
        $this->citizen($this->mayor, 'SPD-OM-1');
        $this->citizen($this->mayor, 'SPD-OM-2');
        $this->internal('INT-20261019-OTH234', $this->mayor, $this->mayor);

        $response = $this->actingAs($this->head)->get(route('reports.index'))->assertOk();

        $this->assertSame(1, $response->viewData('kpis')['total']['value']);
        $response->assertSee('General Services Office · Reports')
            ->assertSee('SPD-GSO-1')
            ->assertDontSee('SPD-OM-1');
    }

    public function test_the_internal_side_counts_what_the_office_filed_or_handles(): void
    {
        $this->internal('INT-20261019-FIL234', $this->gso, $this->mayor);   // filed by GSO
        $this->internal('INT-20261019-ATG234', $this->mayor, $this->gso);   // at GSO
        $this->internal('INT-20261019-NON234', $this->mayor, $this->mayor); // nothing to do with GSO

        $response = $this->actingAs($this->head)->get(route('reports.index', ['view' => 'internal']))->assertOk();

        $this->assertSame(2, $response->viewData('internalKpis')['filed']);
        $response->assertSee('INT-20261019-FIL234')
            ->assertSee('INT-20261019-ATG234')
            ->assertDontSee('INT-20261019-NON234');
    }

    public function test_every_link_is_one_a_supervisor_can_open(): void
    {
        $this->citizen($this->gso, 'SPD-GSO-LATE');

        $this->actingAs($this->head)->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('track.show', 'SPD-GSO-LATE'), false)
            ->assertSee(route('reports.export').'?view=citizen', false)
            ->assertDontSee(route('admin.requests.index'), false)
            ->assertDontSee(route('admin.audit-log.index'), false)
            ->assertDontSee('Requests by Office');
    }

    public function test_a_supervisor_can_nudge_about_their_own_office_only(): void
    {
        Notification::fake();
        $ours = $this->citizen($this->gso, 'SPD-GSO-2', ['assigned_to' => $this->staff->id, 'status' => 'in_progress']);
        $theirs = $this->citizen($this->mayor, 'SPD-OM-3');

        $this->actingAs($this->head)
            ->from(route('reports.index'))
            ->post(route('reports.nudge', $ours), ['note' => 'Please wrap this up.'])
            ->assertRedirect(route('reports.index'))
            ->assertSessionHas('status', 'Notified Paolo Mendoza about SPD-GSO-2.');
        Notification::assertSentTo($this->staff, DocumentEvent::class);

        $this->actingAs($this->head)->post(route('reports.nudge', $theirs))->assertForbidden();
    }

    public function test_the_export_is_this_offices(): void
    {
        $this->citizen($this->gso, 'SPD-GSO-3');

        $csv = $this->actingAs($this->head)->get(route('reports.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString('General Services Office', $csv);
    }

    public function test_one_reports_tab_replaces_analytics_and_services(): void
    {
        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('reports.index').'"', false)
            ->assertSee('<span class="nav-text whitespace-nowrap">Reports</span>', false)
            ->assertDontSee('<span class="nav-text whitespace-nowrap">Analytics</span>', false)
            ->assertDontSee('<span class="nav-text whitespace-nowrap">Services</span>', false);

        $this->actingAs($this->head)->get(route('analytics'))->assertRedirect(route('reports.index'));
    }

    public function test_the_dashboard_carries_a_one_line_glance_at_the_last_30_days(): void
    {
        $this->citizen($this->gso, 'SPD-GSO-4');

        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Last 30 days')
            ->assertSee('Open reports →');
    }
}
