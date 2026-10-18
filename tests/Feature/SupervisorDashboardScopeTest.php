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
}
