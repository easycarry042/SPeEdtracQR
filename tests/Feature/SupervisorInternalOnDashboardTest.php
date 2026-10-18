<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supervisors work internal requests from their dashboard: the separate
 * Internal page was one more stop for the same people.
 */
class SupervisorInternalOnDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private Department $mayor;

    private User $supervisor;

    private Document $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
        $this->supervisor = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');

        $this->request = Document::create([
            'tracking_number' => 'INT-20261018-GSO234',
            'document_type' => 'Internal Request',
            'purpose' => 'Folding tables for the fiesta',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $this->mayor->id,
        ]);
        $this->request->requestSteps()->create([
            'step_order' => 1,
            'department_id' => $this->gso->id,
            'action' => 'Review and action',
            'status' => RequestStep::STATUS_CURRENT,
            'started_at' => now(),
        ]);
    }

    public function test_the_dashboard_lists_what_awaits_the_office(): void
    {
        $response = $this->actingAs($this->supervisor)->get(route('dashboard'))->assertOk();

        $response->assertSee('Internal requests')
            ->assertSee('Awaiting my office')
            ->assertSee('Filed by my office')
            ->assertSee('INT-20261018-GSO234')
            ->assertSee('Folding tables for the fiesta')
            // The request modal comes with it.
            ->assertSee('internalRequestModal', false);
    }

    public function test_closing_the_request_modal_keeps_its_box_for_the_next_one(): void
    {
        // Destroying the box itself dropped its x-ref, so every request opened
        // after the first failed: "Cannot set properties of undefined
        // (setting 'innerHTML')".
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('destroyTree(box)', false)
            ->assertSee('[...box.children].forEach((child) => window.Alpine?.destroyTree(child))', false);
    }

    public function test_supervisors_have_no_separate_internal_tab(): void
    {
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('<span class="nav-text whitespace-nowrap">Internal</span>', false);
    }

    public function test_staff_and_super_admins_keep_the_internal_page(): void
    {
        $staff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        $admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');

        $this->actingAs($staff)->get(route('requests.index'))->assertOk()->assertSee('INT-20261018-GSO234');
        $this->actingAs($admin)->get(route('requests.index'))->assertOk()->assertSee('INT-20261018-GSO234');
    }

    public function test_old_internal_links_land_on_the_dashboard_with_the_request_open(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('requests.index', ['open' => $this->request->id]))
            ->assertRedirect(route('dashboard', ['open' => $this->request->id]));
    }

    public function test_a_decision_made_in_the_modal_comes_back_to_the_dashboard_with_its_message(): void
    {
        // A soft copy needs no folder scan, so the decision goes straight through.
        $this->request->update(['has_hard_copy' => false]);

        $this->actingAs($this->supervisor)
            ->post(route('requests.steps.return', $this->request), [
                'remarks' => 'Please attach the event programme.',
                'from' => 'internal',
            ])
            ->assertRedirect(route('requests.index', ['open' => $this->request->id]));

        $this->get(route('requests.index', ['open' => $this->request->id]))
            ->assertRedirect(route('dashboard', ['open' => $this->request->id]));

        $this->get(route('dashboard', ['open' => $this->request->id]))
            ->assertOk()
            ->assertSee('Request returned to the filing office for revision.');
    }

    public function test_the_sla_slip_is_gone(): void
    {
        Document::create([
            'tracking_number' => 'SPD-LATE-1',
            'document_type' => 'Lei Making',
            'status' => 'pending',
            'department_id' => $this->gso->id,
            'status_changed_at' => now()->subDays(20),
        ]);

        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('h overdue');
    }
}
