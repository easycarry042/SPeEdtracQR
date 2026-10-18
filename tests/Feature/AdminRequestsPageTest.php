<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The super admin's Requests page: every citizen request and where it
 * stands, read-only, with Notify. It is also where their request links land —
 * they used to bounce back to the dashboard, so clicking one did nothing.
 */
class AdminRequestsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $gso;

    private Document $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');
        $this->gso = Department::create(['name' => 'General Services Office', 'code' => 'GSO', 'is_active' => true]);
        $this->request = Document::create([
            'tracking_number' => 'SPD-20261017-REQ234',
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
            'department_id' => $this->gso->id,
            'status_changed_at' => now()->subDays(3),
        ]);
    }

    public function test_it_lists_every_citizen_request_and_no_internal_one(): void
    {
        Document::create([
            'tracking_number' => 'INT-20261017-NOTME1',
            'document_type' => 'Internal Request',
            'status' => 'pending',
            'origin' => Document::ORIGIN_INTERNAL,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.requests.index'))->assertOk();

        $tracking = $response->viewData('requests')->pluck('tracking_number')->all();
        $this->assertSame(['SPD-20261017-REQ234'], $tracking);

        $row = $response->viewData('requests')->first();
        $this->assertSame('General Services Office', $row['department']);
        $this->assertNotNull($row['over_sla'], 'three days at Pending is past its stage time');
        $this->assertSame(route('admin.nudge', $this->request), $row['nudge_url']);
    }

    public function test_it_uses_the_review_modal_read_only_with_notify(): void
    {
        $this->actingAs($this->admin)->get(route('admin.requests.index'))
            ->assertOk()
            ->assertSee('Requests')
            // The modal's oversight panel, in place of assign / advance.
            ->assertSee('The stage is theirs to advance')
            ->assertSee('admin-notify', false)
            ->assertDontSee('Assign to Staff')
            ->assertDontSee('Approving…');
    }

    public function test_only_super_admins_reach_it(): void
    {
        $supervisor = User::factory()->create(['department_id' => $this->gso->id])->assignRole('Supervisor');

        $this->actingAs($supervisor)->get(route('admin.requests.index'))->assertForbidden();
    }

    public function test_the_sidebar_has_a_requests_tab(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.requests.index').'"', false);
    }

    public function test_a_tracking_link_opens_the_request_here(): void
    {
        $this->actingAs($this->admin)
            ->get(route('track.show', $this->request->tracking_number))
            ->assertRedirect(route('admin.requests.index', ['open' => $this->request->tracking_number]));

        $this->actingAs($this->admin)
            ->get(route('admin.requests.index', ['open' => strtolower($this->request->tracking_number)]))
            ->assertOk()
            ->assertViewHas('openTracking', 'SPD-20261017-REQ234');
    }

    public function test_the_command_centers_lists_link_here(): void
    {
        $unrouted = Document::create([
            'tracking_number' => 'SPD-20261017-LOST23',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'status_changed_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.requests.index', ['open' => $unrouted->tracking_number]), false)
            ->assertSee(route('admin.requests.index', ['open' => $this->request->tracking_number]), false)
            // One shared dialog, not the old inline card.
            ->assertSee('admin-notify', false)
            ->assertDontSee('admin-modal-card', false);
    }

    public function test_notifying_about_an_unassigned_request_reaches_only_its_office(): void
    {
        Notification::fake();
        $gsoHead = User::factory()->create(['department_id' => $this->gso->id, 'is_active' => true])->assignRole('Supervisor');
        $other = Department::create(['name' => 'Office of the Mayor', 'code' => 'OM', 'is_active' => true]);
        $otherHead = User::factory()->create(['department_id' => $other->id, 'is_active' => true])->assignRole('Supervisor');

        $this->actingAs($this->admin)
            ->from(route('admin.requests.index'))
            ->post(route('admin.nudge', $this->request), ['note' => 'Still waiting.'])
            ->assertRedirect(route('admin.requests.index'))
            ->assertSessionHas('status', 'Notified the General Services Office supervisors about SPD-20261017-REQ234.');

        Notification::assertSentTo($gsoHead, DocumentEvent::class);
        Notification::assertNotSentTo($otherHead, DocumentEvent::class);
    }

    public function test_the_dashboard_says_the_notification_went_out(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['status' => 'Notified Paolo Mendoza about SPD-20261017-REQ234.'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Notified Paolo Mendoza about SPD-20261017-REQ234.');
    }
}
