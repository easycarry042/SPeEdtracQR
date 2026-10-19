<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supervisors handle internal requests on their own Internal tab — receiving
 * them by QR and deciding them there — not as a section of the dashboard.
 * Both rows carry a count when a request comes to them.
 */
class SupervisorInternalTabTest extends TestCase
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

    public function test_supervisors_have_their_own_internal_tab(): void
    {
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('requests.index').'"', false)
            ->assertSee('<span class="nav-text whitespace-nowrap">Internal</span>', false);

        $this->actingAs($this->supervisor)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Awaiting my office')
            ->assertSee('INT-20261018-GSO234')
            ->assertSee('internalRequestModal', false);
    }

    public function test_the_dashboard_no_longer_carries_the_internal_queues(): void
    {
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Filed by my office')
            ->assertDontSee('INT-20261018-GSO234');
    }

    public function test_a_decision_made_in_the_modal_comes_back_to_the_internal_page(): void
    {
        // Received first: nothing is decided until someone at the office has it.
        $this->request->update(['has_hard_copy' => false]);
        $this->request->custodyEvents()->create(['user_id' => $this->supervisor->id, 'capture_method' => 'image']);

        $this->actingAs($this->supervisor)
            ->post(route('requests.steps.return', $this->request), [
                'remarks' => 'Please attach the event programme.',
                'from' => 'internal',
            ])
            ->assertRedirect(route('requests.index', ['open' => $this->request->id]));

        $this->get(route('requests.index', ['open' => $this->request->id]))
            ->assertOk()
            ->assertSee('Request returned to the filing office for revision.');
    }

    public function test_closing_the_request_modal_keeps_its_box_for_the_next_one(): void
    {
        // Destroying the box itself dropped its x-ref, so every request opened
        // after the first failed: "Cannot set properties of undefined
        // (setting 'innerHTML')".
        $this->actingAs($this->supervisor)->get(route('requests.index'))
            ->assertOk()
            ->assertDontSee('destroyTree(box)', false)
            ->assertSee('[...box.children].forEach((child) => window.Alpine?.destroyTree(child))', false);
    }

    public function test_the_sla_slip_stays_gone(): void
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
