<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hard stage gates on manual status progression: per-stage requirements,
 * required notes/reasons, and the optimistic-concurrency guard.
 */
class StatusGateTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function doc(User $staff, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-GATE-'.strtoupper(uniqid()),
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
            'created_by' => $staff->id,
        ], $attributes));
    }

    public function test_advance_to_in_progress_requires_an_accepted_assignment(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'pending', 'accepted_at' => null]);

        $claim = now()->addWeek()->toDateString();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'pending',
                'claim_date' => $claim,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $doc->fresh()->status);

        $doc->forceFill(['accepted_at' => now()])->save();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'pending',
                'claim_date' => $claim,
            ])
            ->assertOk();

        $this->assertSame('in_progress', $doc->fresh()->status);
    }

    public function test_advance_to_in_review_requires_an_attachment_or_work_note(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);
        $claim = now()->addWeek()->toDateString();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'in_progress'])
            ->assertUnprocessable();

        // A staff work note satisfies the gate.
        DocumentComment::create([
            'document_id' => $doc->id,
            'author_id' => $staff->id,
            'author_type' => 'staff',
            'body' => 'Verified the submitted requirements.',
            'visibility' => 'internal',
        ]);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_progress',
                'claim_date' => $claim,
            ])
            ->assertOk();

        $this->assertSame('in_review', $doc->fresh()->status);
    }

    public function test_advancing_requires_a_claiming_date_and_records_it(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);

        // The claiming date replaced the free-text review note.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'in_review'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('claim_date');

        // A day already past cannot be promised to the citizen.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => now()->subDay()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('claim_date');

        $claim = now()->addWeek()->startOfDay();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => $claim->toDateString(),
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertSame('approved', $doc->status);
        $this->assertSame($claim->toDateString(), $doc->claim_date->toDateString());
    }

    public function test_advance_to_approved_accepts_an_optional_note_alongside_the_date(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'in_review'])
            ->assertUnprocessable();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => now()->addWeek()->toDateString(),
                'note' => 'All requirements verified; clearances valid.',
            ])
            ->assertOk();

        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_approval_is_blocked_until_mandatory_requirements_are_verified(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);
        $req = $doc->requirements()->create(['label' => 'Barangay Clearance', 'is_mandatory' => true]);

        // A claiming date is set, but the mandatory requirement isn't verified → blocked.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('in_review', $doc->fresh()->status);

        // Staff verifies the requirement (records who/when).
        $this->actingAs($staff)
            ->post(route('documents.requirements.toggle', [$doc, $req]))
            ->assertRedirect();
        $this->assertNotNull($req->fresh()->verified_at);
        $this->assertSame($staff->id, $req->fresh()->verified_by);

        // Now approval goes through.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertOk();
        $this->assertSame('approved', $doc->fresh()->status);
    }

    public function test_stale_expected_status_is_refused(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);

        // The page was rendered while the document was still In Progress.
        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_progress',
                'claim_date' => now()->addWeek()->toDateString(),
            ])
            ->assertUnprocessable();

        $this->assertSame('in_review', $doc->fresh()->status);
    }

    public function test_moving_back_requires_a_reason(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.revert', $doc), ['expected_status' => 'in_review'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($staff)
            ->patchJson(route('documents.status.revert', $doc), [
                'expected_status' => 'in_review',
                'reason' => 'Advanced by mistake.',
            ])
            ->assertOk();

        $this->assertSame('in_progress', $doc->fresh()->status);
    }

    public function test_track_page_renders_the_gate_panel_with_checklist(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff); // in_progress, no attachments/notes yet

        $this->actingAs($staff)
            ->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            // Advance dialog with the →In Review checklist, unmet — setting the
            // claiming date records the step, and the dialog says so.
            ->assertSee('Confirm — move to In Review')
            ->assertSee('An attachment or work note on file')
            ->assertSee('Claiming date (required)')
            ->assertSee('setting a claiming date below records this step')
            // Return for revision / On hold moved to the review modal.
            ->assertDontSee('Return for revision')
            ->assertDontSee('On hold');
    }

    public function test_the_claiming_date_satisfies_the_work_evidence_gate(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff); // in_progress, nothing on file
        $claim = now()->addWeek();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_progress',
                'claim_date' => $claim->toDateString(),
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertSame('in_review', $doc->status);
        $this->assertSame($claim->toDateString(), $doc->claim_date->toDateString());

        // The date is written to the file as a real staff work note, so the
        // audit trail still records why the stage moved.
        $this->assertTrue(
            $doc->comments()->where('author_type', 'staff')
                ->where('body', 'Claiming date set to '.$claim->format('M d, Y').'.')
                ->exists()
        );
    }

    public function test_the_claiming_date_reaches_the_citizen_tracking_page(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);
        $claim = now()->addDays(10);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'claim_date' => $claim->toDateString(),
            ])
            ->assertOk();

        // The citizen sees the promised day on their public tracking page.
        $this->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee($claim->format('l, M d, Y'));
    }

    public function test_return_for_revision_requires_a_reason(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.set', $doc), [
                'expected_status' => 'in_progress',
                'status' => 'returned',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($staff)
            ->patchJson(route('documents.status.set', $doc), [
                'expected_status' => 'in_progress',
                'status' => 'returned',
                'reason' => 'The uploaded clearance is expired.',
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertSame('returned', $doc->status);
        $this->assertSame('The uploaded clearance is expired.', $doc->remarks);
    }
}
