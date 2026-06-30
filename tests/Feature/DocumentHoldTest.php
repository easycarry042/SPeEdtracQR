<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Mail\SlaBreachMail;
use App\Mail\SlaWarningMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DocumentHoldTest extends TestCase
{
    use RefreshDatabase;

    private function assignedDoc(User $staff, int $stageHoursElapsed = 10): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-HOLD-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::InReview->value, // SLA 48h
            'created_by' => $staff->id,
            'assigned_to' => $staff->id,
            'status_changed_at' => now()->subHours($stageHoursElapsed),
        ]);
    }

    public function test_assignee_can_place_on_hold_and_fields_are_set(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->assignedDoc($staff);

        $this->actingAs($staff)
            ->patch(route('documents.status.hold', $doc), [
                'hold_reason' => 'Waiting for the citizen to upload a clearance',
                'blocked_by' => 'citizen',
                'hold_until' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $doc->refresh();
        $this->assertSame(DocumentStatus::OnHold->value, $doc->status);
        $this->assertSame(DocumentStatus::InReview->value, $doc->status_before_hold);
        $this->assertSame('citizen', $doc->blocked_by);
        $this->assertSame($staff->id, $doc->held_by);
        $this->assertNotNull($doc->held_at);
    }

    public function test_hold_requires_a_reason(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->assignedDoc($staff);

        $this->actingAs($staff)
            ->from(route('track.show', $doc->tracking_number))
            ->patch(route('documents.status.hold', $doc), ['blocked_by' => 'internal'])
            ->assertSessionHasErrors('hold_reason');

        $this->assertSame(DocumentStatus::InReview->value, $doc->fresh()->status);
    }

    public function test_unrelated_staff_cannot_hold(): void
    {
        $this->seedRolesAndPermissions();
        $owner = User::factory()->create()->assignRole('staff');
        $other = User::factory()->create()->assignRole('staff');
        $doc = $this->assignedDoc($owner);

        $this->actingAs($other)
            ->patch(route('documents.status.hold', $doc), [
                'hold_reason' => 'x', 'blocked_by' => 'internal',
            ])
            ->assertForbidden();
    }

    public function test_sla_sweep_skips_held_documents(): void
    {
        $this->seedRolesAndPermissions();
        Mail::fake();
        $staff = User::factory()->create(['email' => 'held'.uniqid().'@example.com'])->assignRole('staff');

        // Way past the In Review SLA, but held → must not notify.
        $doc = $this->assignedDoc($staff, stageHoursElapsed: 100);
        $doc->forceFill([
            'status_before_hold' => $doc->status,
            'status' => DocumentStatus::OnHold->value,
            'hold_reason' => 'Blocked',
            'blocked_by' => 'external',
            'held_at' => now(),
            'held_by' => $staff->id,
        ])->save();

        $this->artisan('documents:check-sla')->assertSuccessful();

        Mail::assertNotSent(SlaBreachMail::class);
        Mail::assertNotSent(SlaWarningMail::class);
    }

    public function test_unhold_restores_stage_and_pauses_sla(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        Carbon::setTestNow(Carbon::create(2026, 6, 1, 9));
        $doc = $this->assignedDoc($staff, stageHoursElapsed: 10); // 10h into In Review (SLA 48)
        $this->assertFalse($doc->isOverdue());

        // Hold now, then jump 100h into the future and un-hold.
        $this->actingAs($staff)->patch(route('documents.status.hold', $doc), [
            'hold_reason' => 'Waiting on external office',
            'blocked_by' => 'external',
        ])->assertRedirect();

        Carbon::setTestNow(Carbon::create(2026, 6, 1, 9)->addHours(100));

        $this->actingAs($staff)->patch(route('documents.status.unhold', $doc))->assertRedirect();

        $doc->refresh();
        $this->assertSame(DocumentStatus::InReview->value, $doc->status);
        // The 100h hold window was excluded: still only ~10h into the stage, not overdue.
        $this->assertFalse($doc->isOverdue());
        $this->assertEqualsWithDelta(10, $doc->status_changed_at->diffInHours(now()), 1);

        Carbon::setTestNow();
    }
}
