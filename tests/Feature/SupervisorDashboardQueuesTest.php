<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The supervisor dashboard absorbed the Assignments page: Unassigned, In
 * Progress and Completed tabs list the office's citizen requests, and the
 * Review modal assigns and reassigns.
 */
class SupervisorDashboardQueuesTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private Department $mayor;

    private User $head;

    private User $paolo;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
        $this->head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $this->paolo = User::factory()->create(['name' => 'Paolo Mendoza', 'department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        $this->ana = User::factory()->create(['name' => 'Ana Cruz', 'department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
    }

    private function request(string $tracking, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => $tracking,
            'document_type' => 'Chairs Borrowing',
            'status' => 'pending',
            'department_id' => $this->gso->id,
            'status_changed_at' => now(),
        ], $attributes));
    }

    public function test_the_sidebar_has_no_assignments_page(): void
    {
        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('<span class="nav-text whitespace-nowrap">Assignments</span>', false)
            ->assertSee('Completed <span class="st-tab-count"', false);
    }

    public function test_the_completed_tab_pages_through_the_offices_whole_history(): void
    {
        $this->request('SPD-DONE-NEW', ['status' => 'completed', 'assigned_to' => $this->paolo->id, 'completed_at' => now()]);
        $this->request('SPD-DONE-OLD', ['status' => 'completed', 'assigned_to' => $this->paolo->id, 'completed_at' => now()->subDays(120)]);
        $this->request('SPD-DONE-OM', ['status' => 'completed', 'department_id' => $this->mayor->id, 'completed_at' => now()]);

        $this->actingAs($this->head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Completed <span class="st-tab-count">2</span>', false);

        $tracking = collect($this->actingAs($this->head)->getJson(route('history.data'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->json('data'))->pluck('tracking_number');

        // No 90-day cut-off any more, and never another office's.
        $this->assertSame(['SPD-DONE-NEW', 'SPD-DONE-OLD'], $tracking->all());
    }

    public function test_a_supervisor_reassigns_in_progress_work_from_the_modal(): void
    {
        $document = $this->request('SPD-MOVE', ['status' => 'in_progress', 'assigned_to' => $this->paolo->id, 'accepted_at' => now()]);

        $this->actingAs($this->head)
            ->patchJson(route('admin.assignments.assign', $document), ['assigned_to' => $this->ana->id])
            ->assertOk()
            ->assertJson([
                'message' => 'Assigned SPD-MOVE to Ana Cruz.',
                'request' => ['assigned_to' => $this->ana->id, 'assigned_staff' => 'Ana Cruz', 'needs_triage' => true],
            ]);

        $document->refresh();
        $this->assertSame($this->ana->id, $document->assigned_to);
        $this->assertNull($document->accepted_at, 'The new assignee accepts it first.');
        $this->assertSame('in_progress', $document->status, 'Reassigning never moves the stage.');
    }

    public function test_a_supervisor_cannot_reassign_another_offices_request(): void
    {
        $theirs = $this->request('SPD-THEIRS', ['department_id' => $this->mayor->id, 'status' => 'in_progress']);

        $this->actingAs($this->head)
            ->patchJson(route('admin.assignments.assign', $theirs), ['assigned_to' => $this->ana->id])
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->assigned_to);
    }

    public function test_a_supervisor_cannot_reassign_to_another_offices_staff(): void
    {
        $outsider = User::factory()->create(['department_id' => $this->mayor->id, 'is_active' => true])->assignRole('staff');
        $document = $this->request('SPD-STAY', ['status' => 'in_progress', 'assigned_to' => $this->paolo->id]);

        $this->actingAs($this->head)
            ->patchJson(route('admin.assignments.assign', $document), ['assigned_to' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }
}
