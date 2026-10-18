<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A department head reads their own office and nobody else's.
 *
 * AssignmentScope::applyDocumentScope used to ask canViewAll(), which is true
 * for a Supervisor, and then returned the query unscoped — so the General
 * Services head opened Analytics, Look Up, History or the Services report and
 * read Treasury's and Engineering's work alongside their own. The Services
 * report had no department filter at all.
 */
class DepartmentDataScopeTest extends TestCase
{
    use RefreshDatabase;

    private Department $mine;

    private Department $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->mine = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $this->theirs = Department::create(['name' => 'Treasury', 'code' => 'TRSY', 'is_active' => true]);
    }

    private function departmentHead(Department $dept): User
    {
        return User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
    }

    private function request(Department $dept, string $tracking, string $type, string $status = 'completed'): Document
    {
        return Document::create([
            'tracking_number' => $tracking,
            'document_type' => $type,
            'citizen_name' => 'Juan Dela Cruz',
            'department_id' => $dept->id,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    private function type(Department $dept, string $name): RequestType
    {
        return RequestType::create([
            'name' => $name,
            'kind' => RequestType::KIND_DOCUMENT,
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
    }

    public function test_analytics_counts_only_this_departments_work(): void
    {
        $this->request($this->mine, 'SPD-GSO-1', 'Chairs Borrowing');
        $this->request($this->theirs, 'SPD-TRSY-1', 'Tax Clearance');
        $this->request($this->theirs, 'SPD-TRSY-2', 'Tax Clearance');

        $response = $this->actingAs($this->departmentHead($this->mine))->get(route('analytics'))->assertOk();

        $types = collect($response->viewData('byType'))->pluck('label');

        $this->assertContains('Chairs Borrowing', $types);
        $this->assertNotContains('Tax Clearance', $types, "Another office's request types must not appear.");
    }

    public function test_analytics_staff_ranking_does_not_list_other_offices(): void
    {
        $mineStaff = User::factory()->create(['name' => 'Ana Cruz', 'department_id' => $this->mine->id])->assignRole('staff');
        $theirStaff = User::factory()->create(['name' => 'Ben Reyes', 'department_id' => $this->theirs->id])->assignRole('staff');

        $this->request($this->mine, 'SPD-GSO-2', 'Chairs Borrowing')->update(['assigned_to' => $mineStaff->id]);
        $this->request($this->theirs, 'SPD-TRSY-3', 'Tax Clearance')->update(['assigned_to' => $theirStaff->id]);

        $response = $this->actingAs($this->departmentHead($this->mine))->get(route('analytics'))->assertOk();

        $names = collect($response->viewData('topStaff'))->pluck('name');

        $this->assertContains('Ana Cruz', $names);
        $this->assertNotContains('Ben Reyes', $names, "Another office's staff must not be ranked here.");
    }

    public function test_the_services_report_lists_only_this_departments_request_types(): void
    {
        $this->type($this->mine, 'Chairs Borrowing');
        $this->type($this->theirs, 'Tax Clearance');

        $response = $this->actingAs($this->departmentHead($this->mine))->get(route('reports.services'))->assertOk();

        $names = $response->viewData('volumeByType')->pluck('name');

        $this->assertContains('Chairs Borrowing', $names);
        $this->assertNotContains('Tax Clearance', $names, "Another office's services must not appear.");
    }

    public function test_history_shows_only_this_departments_completed_work(): void
    {
        $this->request($this->mine, 'SPD-GSO-DONE', 'Chairs Borrowing');
        $this->request($this->theirs, 'SPD-TRSY-DONE', 'Tax Clearance');

        $this->actingAs($this->departmentHead($this->mine))
            ->get(route('history'))
            ->assertOk()
            ->assertSee('SPD-GSO-DONE')
            ->assertDontSee('SPD-TRSY-DONE');
    }

    /** Intake nobody has routed yet is every triager's to pick up. */
    public function test_unrouted_intake_stays_visible_to_every_department_head(): void
    {
        Document::create([
            'tracking_number' => 'SPD-UNROUTED',
            'document_type' => 'Lei Making',
            'citizen_name' => 'Juan Dela Cruz',
            'department_id' => null,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        foreach ([$this->mine, $this->theirs] as $dept) {
            $this->actingAs($this->departmentHead($dept))
                ->get(route('history'))
                ->assertOk()
                ->assertSee('SPD-UNROUTED');
        }
    }

    public function test_a_super_admin_still_reads_every_office(): void
    {
        $this->request($this->mine, 'SPD-GSO-3', 'Chairs Borrowing');
        $this->request($this->theirs, 'SPD-TRSY-4', 'Tax Clearance');

        $this->actingAs(User::factory()->create()->assignRole('super_admin'))
            ->get(route('history'))
            ->assertOk()
            ->assertSee('SPD-GSO-3')
            ->assertSee('SPD-TRSY-4');
    }
}
