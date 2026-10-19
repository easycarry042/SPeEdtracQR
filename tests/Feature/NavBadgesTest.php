<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RequestStep;
use App\Models\User;
use App\Support\NavBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counts on the sidebar's Dashboard and Internal rows: requests that have
 * come to this person and wait on them.
 */
class NavBadgesTest extends TestCase
{
    use RefreshDatabase;

    private Department $gso;

    private Department $mayor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->mayor = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
    }

    private function internalAt(Department $office, array $attributes = [], ?User $recipient = null): Document
    {
        static $n = 0;
        $document = Document::create(array_merge([
            'tracking_number' => sprintf('INT-20261019-BDG%03d', ++$n),
            'document_type' => 'Internal Request',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
            'requesting_department_id' => $this->mayor->id,
        ], $attributes));
        $document->requestSteps()->create([
            'step_order' => 1,
            'department_id' => $office->id,
            'recipient_id' => $recipient?->id,
            'action' => 'Review and action',
            'status' => RequestStep::STATUS_CURRENT,
            'started_at' => now()->subMinute(),
        ]);

        return $document;
    }

    public function test_a_supervisor_counts_new_citizen_requests_and_internal_ones_at_their_office(): void
    {
        $head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');

        Document::create(['tracking_number' => 'SPD-BDG-1', 'document_type' => 'Chairs Borrowing', 'status' => 'pending', 'department_id' => $this->gso->id]);
        Document::create(['tracking_number' => 'SPD-BDG-2', 'document_type' => 'Business Permit', 'status' => 'pending', 'department_id' => $this->mayor->id]);
        $this->internalAt($this->gso);
        $this->internalAt($this->mayor);

        $this->assertSame(['home' => 1, 'internal' => 1], NavBadges::for($head));
    }

    public function test_assigning_a_request_takes_it_off_the_supervisors_count(): void
    {
        $head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        Document::create(['tracking_number' => 'SPD-BDG-3', 'document_type' => 'Chairs Borrowing', 'status' => 'pending', 'department_id' => $this->gso->id, 'assigned_to' => $staff->id]);

        $this->assertSame(0, NavBadges::for($head)['home']);
        // …and onto the staff member's, until they accept it.
        $this->assertSame(1, NavBadges::for($staff)['home']);
    }

    public function test_staff_count_soft_copies_sent_to_them_and_folders_nobody_has_scanned_in(): void
    {
        $staff = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');
        $colleague = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('staff');

        $this->internalAt($this->gso, ['has_hard_copy' => false], $staff);       // sent to them: counts
        $this->internalAt($this->gso, ['has_hard_copy' => false], $colleague);   // sent to someone else: no
        $folder = $this->internalAt($this->gso);                                 // paper not scanned in: counts

        $this->assertSame(2, NavBadges::for($staff)['internal']);

        $folder->custodyEvents()->create(['user_id' => $colleague->id, 'capture_method' => 'scan']);
        $this->assertSame(1, NavBadges::for($staff)['internal']);
    }

    public function test_super_admins_get_no_counts(): void
    {
        $admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');
        Document::create(['tracking_number' => 'SPD-BDG-4', 'document_type' => 'Chairs Borrowing', 'status' => 'pending']);

        $this->assertSame(['home' => 0, 'internal' => 0], NavBadges::for($admin));
    }

    public function test_the_counts_are_served_for_the_pages_poller(): void
    {
        $head = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $this->internalAt($this->gso);

        $this->actingAs($head)->getJson(route('nav.badges'))
            ->assertOk()
            ->assertExactJson(['home' => 0, 'internal' => 1]);

        $this->actingAs($head)->get(route('dashboard'))
            ->assertOk()
            ->assertSee("new CustomEvent('nav-badges'", false);
    }
}
