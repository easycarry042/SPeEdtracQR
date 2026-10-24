<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_is_scoped_to_their_department(): void
    {
        $this->seedRolesAndPermissions();

        $mine = Department::create(['name' => 'Business Permits', 'code' => 'BPLO', 'is_active' => true]);
        $other = Department::create(['name' => 'Treasury', 'code' => 'TRE', 'is_active' => true]);

        $supervisor = User::factory()->create(['department_id' => $mine->id])->assignRole('Supervisor');
        $myStaff = User::factory()->create(['name' => 'Ana Reyes', 'department_id' => $mine->id])->assignRole('staff');
        $otherStaff = User::factory()->create(['name' => 'Ben Cruz', 'department_id' => $other->id])->assignRole('staff');

        // A ticket handled by my department vs one in another department.
        Document::create(['tracking_number' => 'SPD-MINE-1', 'document_type' => 'Business Permit', 'department_id' => $mine->id, 'assigned_to' => $myStaff->id, 'status' => 'in_progress']);
        Document::create(['tracking_number' => 'SPD-OTHER-1', 'document_type' => 'Cedula', 'department_id' => $other->id, 'assigned_to' => $otherStaff->id, 'status' => 'in_progress']);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        // The workload board lists my department's staff, not another department's.
        $response->assertSee('Ana Reyes');
        $response->assertDontSee('Ben Cruz');
        // Department context banner is shown.
        $response->assertSee('Business Permits');
    }

    /**
     * The frame's tile row walks the queue from one end to the other: nobody
     * has started it, nobody owns it, it is moving, it is done. Before this
     * the row led with a plain total and labelled all open work "Pending".
     */
    public function test_the_tile_row_walks_the_queue_from_pending_to_completed(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');

        $make = function (string $suffix, string $status, ?int $assignee) use ($dept): void {
            Document::create([
                'tracking_number' => 'SPD-TILE-'.$suffix,
                'document_type' => 'Chairs Borrowing',
                'department_id' => $dept->id,
                'status' => $status,
                'assigned_to' => $assignee,
            ]);
        };

        // Two pending: one nobody owns yet, one already handed to a staffer.
        $make('A', 'pending', null);
        $make('B', 'pending', $staff->id);
        // Open work that has been picked up, and one finished.
        $make('C', 'in_review', $staff->id);
        $make('D', 'completed', $staff->id);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        $this->assertSame(2, $response->viewData('pendingOnly'));
        $this->assertSame(1, $response->viewData('unassigned'), 'Only the pending request nobody owns is unassigned.');
        $this->assertSame(1, $response->viewData('inProgress'), 'In Review is open work that has been started.');
        $this->assertSame(1, $response->viewData('completed'));

        $response->assertSee('Unassigned')->assertSee('In Progress');
    }

    /**
     * A department head's queue is their own office by definition, so the
     * frame leaves the column out. An org-wide admin reads the same table
     * across every office and would be lost without it.
     */
    public function test_the_queue_names_a_department_only_when_it_can_vary(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->assertFalse(
            $this->actingAs($supervisor)->get(route('dashboard'))->assertOk()->viewData('spansDepartments'),
        );
        $this->assertTrue(
            $this->actingAs($admin)->get(route('dashboard'))->assertOk()->viewData('spansDepartments'),
        );
    }

    /**
     * The Look Up desk used to be the only place a supervisor could see work in
     * flight: their dashboard listed unassigned intake only, so a request
     * disappeared from this screen the moment it was assigned and did not come
     * back until it was completed. The desk is gone, so the queue grew a second
     * tab instead.
     */
    public function test_the_dashboard_lists_work_in_progress_not_only_unassigned_intake(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'Business Permits', 'code' => 'BPLO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['name' => 'Ana Reyes', 'department_id' => $dept->id])->assignRole('staff');

        Document::create(['tracking_number' => 'SPD-UNASSIGNED', 'document_type' => 'Business Permit', 'department_id' => $dept->id, 'status' => 'pending']);
        Document::create(['tracking_number' => 'SPD-INFLIGHT', 'document_type' => 'Cedula', 'department_id' => $dept->id, 'assigned_to' => $staff->id, 'status' => 'in_progress']);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        $this->assertSame(['SPD-UNASSIGNED'], $response->viewData('pendingPayload')->pluck('tracking_number')->all());
        $this->assertSame(['SPD-INFLIGHT'], $response->viewData('activePayload')->pluck('tracking_number')->all());

        // Both queues are named on the page, and both rows reach the modal.
        $response->assertSee('In Progress');
        $response->assertSee('Unassigned');
        $response->assertSee('SPD-INFLIGHT');
    }

    /** In-flight work stays inside the viewer's own office. */
    public function test_the_in_progress_queue_is_department_scoped(): void
    {
        $this->seedRolesAndPermissions();

        $mine = Department::create(['name' => 'Business Permits', 'code' => 'BPLO', 'is_active' => true]);
        $other = Department::create(['name' => 'Treasury', 'code' => 'TRE', 'is_active' => true]);

        $supervisor = User::factory()->create(['department_id' => $mine->id])->assignRole('Supervisor');
        $mineStaff = User::factory()->create(['department_id' => $mine->id])->assignRole('staff');
        $otherStaff = User::factory()->create(['department_id' => $other->id])->assignRole('staff');

        Document::create(['tracking_number' => 'SPD-MINE-WIP', 'document_type' => 'Business Permit', 'department_id' => $mine->id, 'assigned_to' => $mineStaff->id, 'status' => 'in_review']);
        Document::create(['tracking_number' => 'SPD-THEIRS-WIP', 'document_type' => 'Cedula', 'department_id' => $other->id, 'assigned_to' => $otherStaff->id, 'status' => 'in_review']);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        $this->assertSame(['SPD-MINE-WIP'], $response->viewData('activePayload')->pluck('tracking_number')->all());
        $response->assertDontSee('SPD-THEIRS-WIP');
    }

    /**
     * The merged modal carries the Look Up desk's panels now, so the payload it
     * is driven from has to include what they render.
     */
    public function test_the_modal_payload_carries_the_logs_and_the_conversation(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'Business Permits', 'code' => 'BPLO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');

        Document::create(['tracking_number' => 'SPD-PANELS', 'document_type' => 'Business Permit', 'department_id' => $dept->id, 'status' => 'pending']);

        $row = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk()
            ->viewData('pendingPayload')->first();

        foreach (['logs', 'messages', 'can_message', 'claim_date', 'requested_claim_date', 'claim_on'] as $key) {
            $this->assertArrayHasKey($key, $row, "the merged modal needs {$key}");
        }
        $this->assertIsArray($row['logs']);
        $this->assertIsArray($row['messages']);
    }
}
