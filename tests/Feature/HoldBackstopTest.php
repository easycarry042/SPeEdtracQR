<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Mail\HoldOverdueMail;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A hold pauses the SLA clock on purpose, so documents:check-sla and isOverdue()
 * both ignore held documents. These tests cover the backstop that keeps a parked
 * document from hiding forever: documents:check-holds.
 */
class HoldBackstopTest extends TestCase
{
    use RefreshDatabase;

    /** A document on hold, with the given hold anchors. */
    private function heldDoc(User $staff, ?string $holdUntil, Carbon $heldAt): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-HOLDX-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::OnHold->value,
            'status_before_hold' => DocumentStatus::InReview->value,
            'created_by' => $staff->id,
            'assigned_to' => $staff->id,
            'status_changed_at' => $heldAt,
            'hold_reason' => 'Waiting on an external clearance',
            'blocked_by' => 'external',
            'hold_until' => $holdUntil,
            'held_at' => $heldAt,
            'held_by' => $staff->id,
        ]);
    }

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create(['email' => 'held'.uniqid().'@example.com'])->assignRole('staff');
    }

    public function test_hold_past_its_hold_until_date_is_chased(): void
    {
        Mail::fake();
        $staff = $this->staff();

        // Staff said "resume on the 1st"; it is now the 6th.
        $doc = $this->heldDoc($staff, now()->subDays(5)->toDateString(), now()->subDays(10));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertQueued(HoldOverdueMail::class, 1);
        $this->assertNotNull($doc->fresh()->hold_reminder_sent_at);
    }

    public function test_hold_inside_its_hold_until_date_is_left_alone(): void
    {
        Mail::fake();
        $staff = $this->staff();

        $doc = $this->heldDoc($staff, now()->addDays(4)->toDateString(), now()->subDays(2));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull($doc->fresh()->hold_reminder_sent_at);
        $this->assertFalse($doc->fresh()->isHoldOverdue());
    }

    public function test_open_ended_hold_is_chased_once_it_goes_stale(): void
    {
        Mail::fake();
        config(['tracking.holds.stale_after_days' => 14]);
        $staff = $this->staff();

        // No hold_until at all — the staleness window is the only deadline.
        $doc = $this->heldDoc($staff, null, now()->subDays(20));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertQueued(HoldOverdueMail::class, 1);
        $this->assertSame(6, $doc->fresh()->holdOverdueDays());
    }

    public function test_open_ended_hold_inside_the_stale_window_is_left_alone(): void
    {
        Mail::fake();
        config(['tracking.holds.stale_after_days' => 14]);
        $staff = $this->staff();

        $this->heldDoc($staff, null, now()->subDays(3));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_chase_is_not_repeated_until_the_renag_window_passes(): void
    {
        Mail::fake();
        config(['tracking.holds.remind_every_days' => 7]);
        $staff = $this->staff();

        $doc = $this->heldDoc($staff, now()->subDays(5)->toDateString(), now()->subDays(10));

        $this->artisan('documents:check-holds')->assertSuccessful();
        Mail::assertQueued(HoldOverdueMail::class, 1);

        // Same day: must not re-send.
        $this->artisan('documents:check-holds')->assertSuccessful();
        Mail::assertQueued(HoldOverdueMail::class, 1);

        // Past the re-nag window and still held: chase again.
        $doc->forceFill(['hold_reminder_sent_at' => now()->subDays(8)])->save();
        $this->artisan('documents:check-holds')->assertSuccessful();
        Mail::assertQueued(HoldOverdueMail::class, 2);
    }

    public function test_stalled_hold_escalates_to_the_department_supervisor(): void
    {
        Mail::fake();
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create(['email' => 'owner@example.com'])->assignRole('staff');
        $supervisor = User::factory()->create([
            'email' => 'boss@example.com',
            'is_active' => true,
        ])->assignRole('Supervisor');

        $doc = $this->heldDoc($staff, now()->subDays(3)->toDateString(), now()->subDays(9));

        $this->artisan('documents:check-holds')->assertSuccessful();

        // One copy to the assignee, one escalation copy to the supervisor.
        Mail::assertQueued(HoldOverdueMail::class, 2);
        Mail::assertQueued(HoldOverdueMail::class, fn (HoldOverdueMail $mail): bool => $mail->isEscalation === false
            && $mail->hasTo($staff->email));
        Mail::assertQueued(HoldOverdueMail::class, fn (HoldOverdueMail $mail): bool => $mail->isEscalation === true
            && $mail->hasTo($supervisor->email));

        $this->assertNotNull($doc->fresh()->hold_reminder_sent_at);
    }

    public function test_escalation_skips_a_supervisor_from_another_department(): void
    {
        Mail::fake();
        $this->seedRolesAndPermissions();

        $handling = Department::factory()->create();
        $other = Department::factory()->create();

        $staff = User::factory()->create([
            'email' => 'owner2@example.com',
            'department_id' => $handling->id,
        ])->assignRole('staff');

        $ownBoss = User::factory()->create([
            'email' => 'ownboss@example.com',
            'department_id' => $handling->id,
            'is_active' => true,
        ])->assignRole('Supervisor');

        $foreignBoss = User::factory()->create([
            'email' => 'foreignboss@example.com',
            'department_id' => $other->id,
            'is_active' => true,
        ])->assignRole('Supervisor');

        $doc = $this->heldDoc($staff, now()->subDays(3)->toDateString(), now()->subDays(9));
        $doc->forceFill(['department_id' => $handling->id])->save();

        $this->artisan('documents:check-holds')->assertSuccessful();

        // Assignee + the handling department's supervisor only.
        Mail::assertQueued(HoldOverdueMail::class, 2);
        Mail::assertQueued(HoldOverdueMail::class, fn (HoldOverdueMail $mail): bool => $mail->hasTo($ownBoss->email));
        Mail::assertNotQueued(HoldOverdueMail::class, fn (HoldOverdueMail $mail): bool => $mail->hasTo($foreignBoss->email));
    }

    public function test_stall_with_nobody_to_notify_is_logged_not_silently_dropped(): void
    {
        Mail::fake();
        $this->seedRolesAndPermissions();
        $creator = User::factory()->create()->assignRole('staff');

        // Unassigned and no supervisors exist — there is no mailbox to chase.
        $doc = $this->heldDoc($creator, now()->subDays(6)->toDateString(), now()->subDays(12));
        $doc->forceFill(['assigned_to' => null])->save();

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertNothingQueued();
        // The marker stays null so a later run retries once a recipient exists.
        $this->assertNull($doc->fresh()->hold_reminder_sent_at);
        // A hold_until is valid through the END of that day, so 6 days ago reads
        // as 5 full days past due.
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $doc->id,
            'subject_type' => Document::class,
            'description' => 'Hold overdue by 5 day(s) — no assignee or supervisor mailbox to notify',
        ]);
    }

    /**
     * Spatie's role() scope throws on an unseeded database. A crashed sweep is
     * the silent failure this command exists to prevent, so it must still chase
     * the assignee with no Supervisor role present.
     */
    public function test_sweep_survives_an_unseeded_database_with_no_supervisor_role(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['email' => 'lonely@example.com']);

        $doc = $this->heldDoc($staff, now()->subDays(4)->toDateString(), now()->subDays(9));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertQueued(HoldOverdueMail::class, 1);
        $this->assertNotNull($doc->fresh()->hold_reminder_sent_at);
    }

    public function test_sweep_ignores_documents_that_are_not_on_hold(): void
    {
        Mail::fake();
        $staff = $this->staff();

        $doc = $this->heldDoc($staff, now()->subDays(30)->toDateString(), now()->subDays(40));
        // Resumed: the hold anchors may linger in history, but status rules.
        $doc->forceFill(['status' => DocumentStatus::InReview->value])->save();

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertFalse($doc->fresh()->isHoldOverdue());
        $this->assertNull($doc->fresh()->holdDueAt());
    }

    public function test_sweep_can_be_disabled_by_config(): void
    {
        Mail::fake();
        config(['tracking.holds.enabled' => false]);
        $staff = $this->staff();

        $this->heldDoc($staff, now()->subDays(5)->toDateString(), now()->subDays(10));

        $this->artisan('documents:check-holds')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_unholding_clears_the_reminder_marker(): void
    {
        $staff = $this->staff();
        $doc = $this->heldDoc($staff, now()->subDays(5)->toDateString(), now()->subDays(10));
        $doc->forceFill(['hold_reminder_sent_at' => now()->subDay()])->save();

        $this->actingAs($staff)
            ->patch(route('documents.status.unhold', $doc))
            ->assertRedirect();

        $doc->refresh();
        $this->assertSame(DocumentStatus::InReview->value, $doc->status);
        $this->assertNull($doc->hold_reminder_sent_at);
    }

    public function test_re_holding_clears_a_previous_reminder_marker(): void
    {
        $staff = $this->staff();
        $doc = $this->heldDoc($staff, now()->subDays(5)->toDateString(), now()->subDays(10));
        $doc->forceFill([
            'status' => DocumentStatus::InReview->value,
            'hold_reminder_sent_at' => now()->subDay(),
        ])->save();

        $this->actingAs($staff)->patch(route('documents.status.hold', $doc), [
            'hold_reason' => 'Waiting on the citizen again',
            'blocked_by' => 'citizen',
            'hold_until' => now()->addDays(5)->toDateString(),
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame(DocumentStatus::OnHold->value, $doc->status);
        $this->assertNull($doc->hold_reminder_sent_at);
        $this->assertFalse($doc->isHoldOverdue());
    }

    public function test_stalled_hold_appears_on_the_admin_dashboard(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $staff = User::factory()->create()->assignRole('staff');

        $doc = $this->heldDoc($staff, now()->subDays(4)->toDateString(), now()->subDays(9));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Parked and past due')
            ->assertSee($doc->tracking_number);
    }

    public function test_admin_dashboard_shows_empty_state_when_no_hold_is_stalled(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');
        $staff = User::factory()->create()->assignRole('staff');

        $this->heldDoc($staff, now()->addDays(5)->toDateString(), now()->subDay());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No stalled holds — every parked request is still inside its hold date.', false);
    }
}
