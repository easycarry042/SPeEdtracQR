<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    private Department $tourism;

    private Department $mayor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->tourism = Department::factory()->create(['name' => 'Tourism Office', 'code' => 'TRSM']);
        $this->mayor = Department::factory()->create(['name' => 'Office of the Mayor', 'code' => 'OM']);
    }

    private function makeInternal(string $tracking, string $purpose, string $status, ?Department $currentDept): Document
    {
        $document = Document::create([
            'tracking_number' => $tracking,
            'document_type' => 'Procurement Request',
            'purpose' => $purpose,
            'status' => $status,
            'status_changed_at' => now(),
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $this->tourism->id,
        ]);

        if ($currentDept) {
            $document->requestSteps()->create([
                'step_order' => 1,
                'department_id' => $currentDept->id,
                'action' => 'Approve request',
                'status' => RequestStep::STATUS_CURRENT,
                'started_at' => now()->subHours(3),
            ]);
        }

        return $document;
    }

    public function test_department_supervisor_sees_office_scoped_tabs(): void
    {
        $this->makeInternal('SPD-TEST-INB001', 'Chairs for the lobby', DocumentStatus::Pending->value, $this->mayor);
        $this->makeInternal('SPD-TEST-INB002', 'Completed banner order', DocumentStatus::Completed->value, null);

        $mayorSupervisor = User::factory()
            ->create(['department_id' => $this->mayor->id])
            ->assignRole('Supervisor');

        $this->actingAs($mayorSupervisor)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Awaiting my office')
            ->assertSee('Chairs for the lobby')
            ->assertSee('SPD-TEST-INB001');
    }

    public function test_filed_tab_shows_the_requesting_offices_active_requests(): void
    {
        $this->makeInternal('SPD-TEST-INB003', 'Vase for reception', DocumentStatus::Pending->value, $this->mayor);

        $tourismSupervisor = User::factory()
            ->create(['department_id' => $this->tourism->id])
            ->assignRole('Supervisor');

        $this->actingAs($tourismSupervisor)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Filed by my office')
            ->assertSee('Vase for reception');
    }

    public function test_super_admin_sees_the_org_wide_view(): void
    {
        $this->makeInternal('SPD-TEST-INB004', 'Sound system', DocumentStatus::Pending->value, $this->mayor);
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Sound system')
            ->assertDontSee('Awaiting my office');
    }

    public function test_staff_cannot_open_the_inbox(): void
    {
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('requests.index'))->assertForbidden();
    }

    public function test_search_filters_by_tracking_number_and_purpose(): void
    {
        $this->makeInternal('SPD-TEST-INB005', 'Chairs for the lobby', DocumentStatus::Pending->value, $this->mayor);
        $this->makeInternal('SPD-TEST-INB006', 'Projector rental', DocumentStatus::Pending->value, $this->mayor);

        $mayorSupervisor = User::factory()
            ->create(['department_id' => $this->mayor->id])
            ->assignRole('Supervisor');

        $this->actingAs($mayorSupervisor)->get(route('requests.index', ['q' => 'Projector']))
            ->assertOk()
            ->assertSee('Projector rental')
            ->assertDontSee('Chairs for the lobby');
    }

    public function test_dashboard_banner_counts_requests_awaiting_the_supervisors_office(): void
    {
        $this->makeInternal('SPD-TEST-INB007', 'Chairs for the lobby', DocumentStatus::Pending->value, $this->mayor);

        $mayorSupervisor = User::factory()
            ->create(['department_id' => $this->mayor->id])
            ->assignRole('Supervisor');

        $this->actingAs($mayorSupervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 internal request awaiting your office');
    }

    public function test_internal_requests_stay_out_of_the_external_triage_table(): void
    {
        $this->makeInternal('SPD-TEST-INB008', 'Chairs for the lobby', DocumentStatus::Pending->value, $this->mayor);

        $admin = User::factory()->create()->assignRole('super_admin');

        // The admin dashboard triage table lists external Pending+unassigned work;
        // the internal request must not leak into it.
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $mayorSupervisor = User::factory()
            ->create(['department_id' => $this->mayor->id])
            ->assignRole('Supervisor');

        $this->actingAs($mayorSupervisor)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pendingRequests', fn ($pending) => $pending->doesntContain(
                fn ($doc) => $doc->tracking_number === 'SPD-TEST-INB008',
            ));
    }
}
