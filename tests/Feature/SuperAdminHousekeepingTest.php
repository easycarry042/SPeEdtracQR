<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\Resource;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The super admin's pages after the review: one sidebar entry per job, the
 * audit log readable, and the bell kept for problems.
 */
class SuperAdminHousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $gso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');
        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
    }

    // ── the sidebar ──────────────────────────────────────────────────────

    public function test_the_sidebar_drops_the_duplicate_pages_and_groups_setup(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('>Staff</span>', false)
            ->assertDontSee('Services report')
            ->assertSeeInOrder(['Dashboard', 'Users', 'Internal Requests', 'Resources', 'Audit Log', 'Setup', 'Departments', 'Route Templates', 'Request Types']);
    }

    // ── Users carries the staff directory's workload ─────────────────────

    public function test_users_shows_each_persons_office_and_workload(): void
    {
        $staff = User::factory()->create(['name' => 'Paolo Mendoza', 'department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        Document::create(['tracking_number' => 'SPD-W-1', 'document_type' => 'Lei Making', 'status' => 'in_progress', 'assigned_to' => $staff->id, 'status_changed_at' => now()]);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();

        $this->assertSame(1, $response->viewData('load')[$staff->id]['assigned']);
        $response->assertSee('Workload')->assertSee('GSO')->assertSee('open');
    }

    // ── Setup pages ──────────────────────────────────────────────────────

    public function test_request_types_name_their_office_and_flag_the_ones_without(): void
    {
        RequestType::create(['name' => 'Chairs Borrowing', 'kind' => 'equipment', 'department_id' => $this->gso->id, 'is_active' => true]);
        RequestType::create(['name' => 'Orphan Permit', 'kind' => 'document', 'department_id' => null, 'is_active' => true]);

        $this->actingAs($this->admin)->get(route('admin.request-types.index'))
            ->assertOk()
            ->assertSee('Handling office')
            ->assertSee('General Services Office')
            ->assertSee('No office — requests go unrouted');
    }

    public function test_departments_show_supervisor_services_and_turnaround(): void
    {
        User::factory()->create(['name' => 'Jose Reyes', 'department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        Department::create(['name' => 'Bids and Awards Committee', 'code' => 'BAC', 'is_active' => true]);
        RequestType::create(['name' => 'Chairs Borrowing', 'kind' => 'equipment', 'department_id' => $this->gso->id, 'is_active' => true]);

        $this->actingAs($this->admin)->get(route('admin.departments.index'))
            ->assertOk()
            ->assertSee('Jose Reyes')
            ->assertSee('No supervisor')
            ->assertSee('None (internal only)')
            ->assertSee('3d')
            ->assertDontSee('Offices available for internal request routing.');
    }

    public function test_an_offices_turnaround_can_be_changed(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.departments.update', $this->gso), ['name' => 'General Services Office', 'code' => 'GSO', 'internal_step_hours' => 24])
            ->assertRedirect();

        $this->assertSame(24, $this->gso->fresh()->internalStepHours());

        $this->actingAs($this->admin)
            ->put(route('admin.departments.update', $this->gso), ['name' => 'General Services Office', 'code' => 'GSO', 'internal_step_hours' => 0])
            ->assertSessionHasErrors('internal_step_hours');
    }

    public function test_resources_show_what_is_waiting_and_what_is_ahead(): void
    {
        $court = Resource::create(['name' => 'Covered Court', 'is_active' => true]);
        $booking = fn (string $status, int $days) => Booking::create([
            'document_id' => Document::create([
                'tracking_number' => 'SPD-BK-'.$status.$days,
                'document_type' => 'Covered Court Reservation',
                'status' => 'pending',
            ])->id,
            'resource_id' => $court->id,
            'status' => $status,
            'starts_at' => now()->addDays($days),
            'ends_at' => now()->addDays($days)->addHours(3),
        ]);
        $booking(Booking::STATUS_PENDING, 2);
        $booking(Booking::STATUS_APPROVED, 5);
        $booking(Booking::STATUS_APPROVED, -9);

        // The super admin's Resources is the shared page: the court's pending
        // request is on the lending desk, its next reservation on the table.
        $this->actingAs($this->admin)->get(route('admin.resources.index'))
            ->assertRedirect(route('inventory.index'));

        $page = $this->actingAs($this->admin)->get(route('inventory.index'))->assertOk()->assertSee('Lending desk');
        $this->assertCount(1, $page->viewData('desk')['approve']);
        $this->assertNotNull($page->viewData('facilities')->firstWhere('name', 'Covered Court')['next']);
    }

    // ── the audit log ────────────────────────────────────────────────────

    public function test_a_sign_in_is_logged_once(): void
    {
        event(new Login('web', $this->admin, false));

        $this->assertSame(1, Activity::where('log_name', 'auth')->where('causer_id', $this->admin->id)->count());
    }

    public function test_the_audit_log_names_requests_by_tracking_number_and_hides_empty_saves(): void
    {
        $document = Document::create(['tracking_number' => 'SPD-AUDIT-1', 'document_type' => 'Lei Making', 'status' => 'pending']);
        $document->update(['status' => 'in_progress']);
        // An old empty row, as MariaDB stores it.
        Activity::create([
            'log_name' => 'default',
            'description' => 'updated',
            'event' => 'updated',
            'subject_type' => Document::class,
            'subject_id' => $document->id,
            'properties' => ['old' => [], 'attributes' => []],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.audit-log.index'))->assertOk();

        $response->assertSee('SPD-AUDIT-1')
            ->assertSee('Status Updated')
            ->assertDontSee('(Document #'.$document->id.')', false);
        $this->assertSame(0, $response->viewData('logs')->getCollection()
            ->filter(fn (Activity $a): bool => $a->event === 'updated' && empty($a->properties['attributes'] ?? []))
            ->count());
    }

    public function test_a_save_that_changes_nothing_logged_writes_no_row(): void
    {
        $document = Document::create(['tracking_number' => 'SPD-QUIET-1', 'document_type' => 'Lei Making', 'status' => 'pending']);
        $before = Activity::count();

        // A column the activity log does not watch.
        $document->update(['qr_code_path' => 'qrcodes/x.png']);

        $this->assertSame($before, Activity::count());
    }

    // ── the bell ─────────────────────────────────────────────────────────

    public function test_a_routed_request_goes_to_that_offices_supervisors_only(): void
    {
        $head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');

        $recipients = DocumentEvent::departmentTriagers($this->gso->id);

        $this->assertSame([$head->id], $recipients->pluck('id')->all());
    }

    public function test_an_office_with_no_supervisor_escalates_to_super_admins(): void
    {
        $recipients = DocumentEvent::departmentTriagers($this->gso->id);

        $this->assertContains($this->admin->id, $recipients->pluck('id')->all());
        $this->assertTrue($recipients->every(fn (User $u): bool => $u->can('manage system')));
    }

    public function test_an_unrouted_request_still_reaches_every_triager_and_the_super_admins(): void
    {
        $head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');

        $ids = DocumentEvent::departmentTriagers(null)->pluck('id')->all();

        $this->assertContains($head->id, $ids);
        $this->assertContains($this->admin->id, $ids);
    }
}
