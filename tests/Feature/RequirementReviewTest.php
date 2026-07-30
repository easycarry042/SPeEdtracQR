<?php

namespace Tests\Feature;

use App\Mail\RequirementRevisionRequested;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequirementReviewTest extends TestCase
{
    use RefreshDatabase;

    private function assignedTicket(): array
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = Document::create([
            'tracking_number' => 'SPD-REQ-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'citizen_email' => 'maria@example.com',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'accepted_at' => now(),
        ]);
        $req = $doc->requirements()->create([
            'label' => 'Barangay Clearance',
            'is_mandatory' => true,
        ]);

        return [$staff, $doc, $req];
    }

    public function test_staff_marking_needs_revision_emails_the_citizen(): void
    {
        Mail::fake();
        [$staff, $doc, $req] = $this->assignedTicket();

        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), [
                'review_status' => 'needs_revision',
                'review_comment' => 'The clearance is expired — please upload a current one.',
            ])
            ->assertRedirect();

        $req->refresh();
        $this->assertSame('needs_revision', $req->review_status);
        $this->assertSame('The clearance is expired — please upload a current one.', $req->review_comment);

        Mail::assertQueued(RequirementRevisionRequested::class, fn ($m) => $m->hasTo('maria@example.com'));
    }

    public function test_review_requires_a_comment_when_returning(): void
    {
        [$staff, $doc, $req] = $this->assignedTicket();

        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'needs_revision'])
            ->assertSessionHasErrors('review_comment');

        $this->assertSame('pending', $req->fresh()->review_status);
    }

    public function test_citizen_can_reupload_only_a_flagged_requirement_and_notifies_staff(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$staff, $doc, $req] = $this->assignedTicket();
        $req->update(['review_status' => 'needs_revision', 'review_comment' => 'Expired.']);

        $this->post(route('track.requirement-reupload', [$doc->tracking_number, $req]), [
            'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $req->refresh();
        $this->assertSame('pending', $req->review_status);
        $this->assertNotNull($req->uploaded_file_path);

        Notification::assertSentTo($staff, DocumentEvent::class);
    }

    public function test_approving_a_requirement_also_verifies_the_original(): void
    {
        [$staff, $doc, $req] = $this->assignedTicket();

        // One button now: Approve records the verification the document's own
        // approval gate checks for.
        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'approved'])
            ->assertRedirect();

        $req->refresh();
        $this->assertSame('approved', $req->review_status);
        $this->assertNotNull($req->verified_at);
        $this->assertSame($staff->id, $req->verified_by);
    }

    public function test_returning_an_approved_requirement_withdraws_its_verification(): void
    {
        Mail::fake();
        [$staff, $doc, $req] = $this->assignedTicket();

        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'approved'])
            ->assertRedirect();
        $this->assertNotNull($req->fresh()->verified_at);

        // Otherwise a withdrawn approval would leave the document advanceable.
        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), [
                'review_status' => 'needs_revision',
                'review_comment' => 'Wrong document attached.',
            ])
            ->assertRedirect();

        $req->refresh();
        $this->assertNull($req->verified_at);
        $this->assertNull($req->verified_by);
    }

    public function test_an_approved_requirement_no_longer_offers_review_actions(): void
    {
        [$staff, $doc, $req] = $this->assignedTicket();

        // Still open for review: all three decisions are on offer.
        $this->actingAs($staff)
            ->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertSee('Approve &amp; verify original', false)
            ->assertSee('Needs revision')
            ->assertSee('Reject');

        $this->actingAs($staff)
            ->post(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'approved'])
            ->assertRedirect();

        // Settled: the row shows the outcome instead of the buttons.
        $this->actingAs($staff)
            ->get(route('track.show', $doc->tracking_number))
            ->assertOk()
            ->assertDontSee('Approve &amp; verify original', false)
            ->assertDontSee('Needs revision')
            ->assertSee('Original verified');
    }

    public function test_citizen_cannot_reupload_a_requirement_not_awaiting_revision(): void
    {
        Storage::fake('local');
        [$staff, $doc, $req] = $this->assignedTicket();
        // Still pending review — not returned for revision.

        $this->post(route('track.requirement-reupload', [$doc->tracking_number, $req]), [
            'file' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->assertNull($req->fresh()->uploaded_file_path);
    }
}
