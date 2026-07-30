<?php

namespace Tests\Feature;

use App\Mail\StatusUpdated;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Header-bell notifications: new tickets ping supervisors, assignments ping
 * the assignee, declines ping supervisors; open/read-all manage the feed.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function doc(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-'.now()->format('Ymd').'-'.substr(strtoupper(uniqid()), -6),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_public_ticket_submission_notifies_supervisors(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        Notification::fake();

        $this->post(route('public.request.store'), [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'citizen_email' => 'jane@example.com',
            'consent' => '1',
        ])->assertOk();

        Notification::assertSentTo(
            $supervisor,
            DocumentEvent::class,
            fn (DocumentEvent $n) => $n->event === 'new_ticket',
        );
    }

    public function test_supervisor_assignment_notifies_the_assignee(): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::create(['name' => 'Records', 'code' => 'REC', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $doc = $this->doc(['department_id' => $dept->id]);

        $this->actingAs($supervisor)->post(route('documents.assign-approve', $doc), [
            'assigned_to' => $staff->id,
        ]);

        $notification = $staff->fresh()->unreadNotifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('assigned', data_get($notification->data, 'event'));
        $this->assertSame($doc->tracking_number, data_get($notification->data, 'tracking'));
    }

    public function test_decline_notifies_supervisors_but_not_the_actor(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->doc([
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => null,
        ]);

        $this->actingAs($staff)->post(route('documents.assignment.decline', $doc), [
            'reason' => 'Not my area.',
        ]);

        // A decline sends its own dedicated ping — and only that one, so the
        // supervisor doesn't also get a generic "now Pending" status notice.
        $events = $supervisor->fresh()->unreadNotifications->pluck('data.event');
        $this->assertSame(['declined'], $events->all());
        $this->assertSame(0, $staff->fresh()->unreadNotifications->count());
    }

    public function test_every_status_move_notifies_supervisors_and_the_citizen(): void
    {
        $this->seedRolesAndPermissions();
        Mail::fake();

        $dept = Department::create(['name' => 'Records', 'code' => 'REC', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $doc = $this->doc([
            'department_id' => $dept->id,
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
            'citizen_email' => 'jane@example.com',
            'notify_citizen' => true,
        ]);

        $this->actingAs($staff)->patchJson(route('documents.status.advance', $doc), [
            'expected_status' => 'in_progress',
            'note' => 'Verified the submitted requirements against the originals.',
        ])->assertOk();

        // Oversight hears about it on the bell…
        $notification = $supervisor->fresh()->unreadNotifications
            ->firstWhere('data.event', 'status_changed');
        $this->assertNotNull($notification);
        $this->assertSame($doc->tracking_number, data_get($notification->data, 'tracking'));

        // …the actor does not get pinged about their own action…
        $this->assertNull($staff->fresh()->unreadNotifications->firstWhere('data.event', 'status_changed'));

        // …and the citizen is emailed about the new stage (StatusUpdated is queued).
        Mail::assertQueued(StatusUpdated::class);
    }

    public function test_holding_a_request_also_notifies_supervisors(): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::create(['name' => 'Assessor', 'code' => 'ASR', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $doc = $this->doc([
            'department_id' => $dept->id,
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($staff)->patchJson(route('documents.status.hold', $doc), [
            'hold_reason' => 'Waiting on the barangay clearance.',
            'blocked_by' => 'citizen',
        ])->assertOk();

        $this->assertNotNull(
            $supervisor->fresh()->unreadNotifications->firstWhere('data.event', 'status_changed')
        );
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->doc();

        $staff->notify(DocumentEvent::assigned($doc, 'A Supervisor'));
        $notification = $staff->fresh()->unreadNotifications->first();

        $this->actingAs($staff)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('staff.dashboard'));

        $this->assertSame(0, $staff->fresh()->unreadNotifications->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $other = User::factory()->create()->assignRole('staff');
        $doc = $this->doc();

        $staff->notify(DocumentEvent::assigned($doc, 'A Supervisor'));
        $notification = $staff->fresh()->unreadNotifications->first();

        $this->actingAs($other)
            ->get(route('notifications.open', $notification->id))
            ->assertNotFound();
    }

    public function test_mark_all_as_read_clears_the_bell(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->doc();

        $staff->notify(DocumentEvent::assigned($doc, 'A Supervisor'));
        $staff->notify(DocumentEvent::newTicket($doc));
        $this->assertSame(2, $staff->fresh()->unreadNotifications->count());

        $this->actingAs($staff)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $staff->fresh()->unreadNotifications->count());
    }
}
