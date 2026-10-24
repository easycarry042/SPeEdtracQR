<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Support\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff open each uploaded requirement before they can mark it checked, the
 * Review modal shows where each one stands, and Process (In Review →
 * Approved) names the files still to check.
 */
class RequirementOpenAndCheckTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Document, 2: DocumentRequirement} */
    private function ticketWithUpload(array $attributes = []): array
    {
        Storage::fake('local');
        Storage::disk('local')->put('requirements/letter.pdf', '%PDF-1.4 test');

        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = Document::create(array_merge([
            'tracking_number' => 'SPD-OPEN-'.strtoupper(uniqid()),
            'document_type' => 'Tables Borrowing',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'status' => 'in_review',
            'assigned_to' => $staff->id,
            'accepted_at' => now(),
            'claim_date' => now()->addDays(3)->startOfDay(),
        ], $attributes));
        $req = $doc->requirements()->create([
            'label' => 'Letter of Request addressed to the Mayor',
            'is_mandatory' => true,
            'uploaded_file_path' => 'requirements/letter.pdf',
        ]);

        return [$staff, $doc, $req];
    }

    public function test_an_uploaded_file_cannot_be_marked_checked_before_it_is_opened(): void
    {
        [$staff, $doc, $req] = $this->ticketWithUpload();

        $this->actingAs($staff)
            ->postJson(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_status' => 'Open "Letter of Request addressed to the Mayor" first, then mark it checked.']);

        $this->actingAs($staff)
            ->post(route('documents.requirements.toggle', [$doc, $req]))
            ->assertSessionHasErrors('review_status');

        $this->assertNull($req->fresh()->verified_at);
    }

    public function test_opening_the_file_is_recorded_and_unlocks_the_check(): void
    {
        [$staff, $doc, $req] = $this->ticketWithUpload();

        // A thumbnail load is not a reviewer reading the file.
        $this->actingAs($staff)->get(route('documents.requirements.file', [$doc, $req]))->assertOk();
        $this->assertNull($req->fresh()->opened_at);

        $this->actingAs($staff)->get(route('documents.requirements.file', [$doc, $req]).'?open=1')->assertOk();
        $req->refresh();
        $this->assertNotNull($req->opened_at);
        $this->assertSame($staff->id, $req->opened_by);

        $this->actingAs($staff)
            ->postJson(route('documents.requirements.review', [$doc, $req]), ['review_status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('requirement.verified', true)
            ->assertJsonPath('requirement.review_status', 'approved');

        $this->assertSame($staff->id, $req->fresh()->verified_by);
    }

    public function test_sending_a_file_back_needs_no_open_and_returns_the_row(): void
    {
        Mail::fake();
        [$staff, $doc, $req] = $this->ticketWithUpload();

        $this->actingAs($staff)
            ->postJson(route('documents.requirements.review', [$doc, $req]), [
                'review_status' => 'needs_revision',
                'review_comment' => 'Please sign the letter.',
            ])
            ->assertOk()
            ->assertJsonPath('requirement.review_status', 'needs_revision')
            ->assertJsonPath('requirement.review_comment', 'Please sign the letter.');
    }

    public function test_a_reupload_must_be_opened_again(): void
    {
        [$staff, $doc, $req] = $this->ticketWithUpload();
        $req->update(['opened_at' => now(), 'opened_by' => $staff->id, 'review_status' => 'needs_revision', 'review_comment' => 'Unsigned.']);

        $this->post(route('track.requirement-reupload', [$doc->tracking_number, $req]), [
            'file' => UploadedFile::fake()->create('letter-signed.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $req->refresh();
        $this->assertNull($req->opened_at);
        $this->assertFalse($req->hasBeenOpened());
    }

    public function test_a_requirement_with_nothing_uploaded_can_be_checked_at_the_counter(): void
    {
        [$staff, $doc] = $this->ticketWithUpload();
        $original = $doc->requirements()->create(['label' => 'Valid ID', 'is_mandatory' => true]);

        $this->actingAs($staff)
            ->postJson(route('documents.requirements.review', [$doc, $original]), ['review_status' => 'approved'])
            ->assertOk();

        $this->assertNotNull($original->fresh()->verified_at);
    }

    public function test_process_names_the_files_still_to_check(): void
    {
        [$staff, $doc] = $this->ticketWithUpload();

        $this->actingAs($staff)
            ->patchJson(route('documents.status.advance', $doc), [
                'expected_status' => 'in_review',
                'note' => 'Reviewed the request.',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['Requirement not met: All required documents checked (still to check: Letter of Request addressed to the Mayor).']);

        $this->assertSame('in_review', $doc->fresh()->status);
    }

    public function test_modal_payload_shows_where_each_file_stands(): void
    {
        [$staff, $doc, $req] = $this->ticketWithUpload();
        $this->actingAs($staff);

        $row = RequestReview::forModal($doc)['requirements'][0];
        $this->assertFalse($row['opened']);
        $this->assertFalse($row['verified']);
        $this->assertStringEndsWith('?open=1', $row['open_url']);
        $this->assertSame(route('documents.requirements.review', [$doc, $req]), $row['review_url']);

        $req->update(['opened_at' => now(), 'opened_by' => $staff->id, 'verified_at' => now(), 'verified_by' => $staff->id]);

        $row = RequestReview::forModal($doc->fresh())['requirements'][0];
        $this->assertTrue($row['opened']);
        $this->assertStringStartsWith('Opened by '.$staff->name, $row['opened_label']);
        $this->assertStringStartsWith('Checked by '.$staff->name, $row['verified_label']);
    }
}
