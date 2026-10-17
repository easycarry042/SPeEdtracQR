<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_super_admin_sees_the_analytics_command_center(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('City of San Pedro · Command center');
    }

    public function test_command_center_accepts_filter_params(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard', ['range' => 90]))
            ->assertOk();
    }

    public function test_invalid_range_is_rejected(): void
    {
        $this->seedRolesAndPermissions();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('admin.dashboard', ['range' => 999]))
            ->assertSessionHasErrors('range');
    }

    public function test_non_super_admin_is_redirected_away_from_the_command_center(): void
    {
        $this->seedRolesAndPermissions();

        // EnsureHasPermission returns a 403 Access Denied page for authenticated
        // users lacking "manage system".
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_super_admin_analytics_redirects_to_the_dashboard(): void
    {
        $this->seedRolesAndPermissions();

        // Analytics is folded into the command center for super admins.
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(route('analytics'))
            ->assertRedirect(route('admin.dashboard'));
    }

    /**
     * The frame's tile row splits the open work: requests nobody has started
     * (Pending) against everything else still moving (In Progress). Before the
     * redesign both of those were one "In Progress" count.
     */
    public function test_the_tile_row_splits_pending_from_in_progress(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->userWithRole('super_admin');

        foreach (['pending', 'pending', 'pending'] as $i => $status) {
            Document::create([
                'tracking_number' => 'SPD-PEND-'.$i,
                'document_type' => 'Business Permit',
                'status' => $status,
                'created_by' => $admin->id,
                'status_changed_at' => now(),
            ]);
        }

        Document::create([
            'tracking_number' => 'SPD-MOVING-1',
            'document_type' => 'Business Permit',
            'status' => 'in_review',
            'created_by' => $admin->id,
            'status_changed_at' => now(),
        ]);

        $kpis = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->viewData('kpis');

        $this->assertSame(3, $kpis['pending']['value']);
        // In Review is open work that has been picked up — not pending.
        $this->assertSame(1, $kpis['in_progress']['value']);
        $this->assertSame(4, $kpis['active']['value'], 'the all-open count the charts read must not change');
    }

    public function test_department_volume_is_read_against_the_busiest_office(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->userWithRole('super_admin');

        $busiest = Department::create(['name' => 'Mayors Office', 'code' => 'MO', 'is_active' => true]);
        $quiet = Department::create(['name' => 'Comelec Office', 'code' => 'COM', 'is_active' => true]);

        foreach (range(1, 4) as $i) {
            Document::create([
                'tracking_number' => 'SPD-MO-'.$i,
                'document_type' => 'Business Permit',
                'department_id' => $busiest->id,
                'status' => 'pending',
                'created_by' => $admin->id,
            ]);
        }

        Document::create([
            'tracking_number' => 'SPD-COM-1',
            'document_type' => 'Cedula',
            'department_id' => $quiet->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $volume = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Document Volume by Department')
            ->viewData('departmentVolume');

        $this->assertSame(['Mayors Office', 'Comelec Office'], $volume->pluck('label')->all());
        $this->assertSame([100, 25], $volume->pluck('pct')->all());
        $this->assertTrue($volume->first()['busiest']);
    }

    public function test_the_status_breakdown_splits_every_request_three_ways(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->userWithRole('super_admin');

        $statuses = ['pending', 'pending', 'in_progress', 'on_hold', 'completed'];
        foreach ($statuses as $i => $status) {
            Document::create([
                'tracking_number' => 'SPD-SPLIT-'.$i,
                'document_type' => 'Business Permit',
                'status' => $status,
                'created_by' => $admin->id,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);
        }

        $summary = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Overall Request Status Breakdown')
            ->viewData('statusSummary');

        $this->assertSame(5, $summary['total']);
        $this->assertSame(
            ['Completed' => 1, 'In-Progress' => 2, 'Pending Review' => 2],
            collect($summary['slices'])->pluck('value', 'label')->all(),
            'a held request is still open work, so the three slices must add back to the total'
        );
    }

    public function test_recent_activity_names_who_changed_a_request_and_to_what(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->userWithRole('super_admin');
        $staff = User::factory()->create(['name' => 'Rachel Obeda'])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-AUDIT-01',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $staff->id,
            'status_changed_at' => now(),
        ]);

        $this->actingAs($staff);
        $document->update(['status' => 'in_review']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $latest = $response->viewData('recentActivity')->first();
        $this->assertSame('Rachel Obeda', $latest['user']);
        $this->assertSame('Staff', $latest['role']);
        $this->assertSame('Status Updated', $latest['action']);
        $this->assertSame('SPD-AUDIT-01', $latest['subject']);
        $this->assertSame('→ In Review', $latest['detail']);

        $response->assertSee('Recent Activity');
    }

    public function test_super_admin_can_reassign_an_at_risk_document(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->userWithRole('super_admin');
        $staff = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-ATRISK-01',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $admin->id,
            'status_changed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.assignments.assign', $document), ['assigned_to' => $staff->id])
            ->assertRedirect();

        $this->assertSame($staff->id, $document->fresh()->assigned_to);
    }
}
