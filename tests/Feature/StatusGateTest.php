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

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame('pending', $doc->fresh()->status);

        $doc->forceFill(['accepted_at' => now()])->save();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'pending'])
            ->assertOk();

        $this->assertSame('in_progress', $doc->fresh()->status);
    }

    public function test_advance_to_in_review_requires_an_attachment_or_work_note(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff);

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
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'in_progress'])
            ->assertOk();

        $this->assertSame('in_review', $doc->fresh()->status);
    }

    public function test_advance_to_approved_requires_a_review_note(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff, ['status' => 'in_review']);

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), ['expected_status' => 'in_review'])
            ->assertUnprocessable();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'note' => 'All requirements verified; clearances valid.',
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
                'note' => 'Looks fine.',
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
            // Advance gate panel with the →In Review checklist, unmet — a
            // typed note can satisfy it, and the panel says so.
            ->assertSee('Confirm — move to In Review')
            ->assertSee('An attachment or work note on file')
            ->assertSee('or add a note below')
            ->assertSee('Add a work note above');
    }

    public function test_a_transition_note_satisfies_the_work_evidence_gate(): void
    {
        $staff = $this->staff();
        $doc = $this->doc($staff); // in_progress, nothing on file

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_progress',
                'note' => 'Verified the submitted documents; started processing.',
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertSame('in_review', $doc->status);

        // The note was persisted as a real staff work note on the file.
        $this->assertTrue(
            $doc->comments()->where('author_type', 'staff')
                ->where('body', 'Verified the submitted documents; started processing.')
                ->exists()
        );
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
