<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\RequestReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requirement uploads live on document_requirements, not document_attachments,
 * so the review modal payload has to expose them separately — otherwise a
 * reviewer opens the modal and sees no evidence at all.
 */
class ReviewModalRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function documentWithRequirements(): Document
    {
        $document = Document::create([
            'tracking_number' => 'SPD-20260728-W44EAQ',
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Francisco Consorte',
            'status' => 'pending',
        ]);

        $document->requirements()->create([
            'label' => 'Barangay Clearance',
            'is_mandatory' => true,
            'uploaded_file_path' => 'requirements/clearance.jpg',
        ]);

        $document->requirements()->create([
            'label' => 'Valid ID',
            'is_mandatory' => false,
        ]);

        return $document;
    }

    public function test_modal_payload_lists_requirement_uploads(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAs(User::factory()->create()->assignRole('super_admin'));

        $payload = RequestReview::forModal($this->documentWithRequirements());

        $this->assertCount(2, $payload['requirements']);

        [$uploaded, $missing] = $payload['requirements'];

        $this->assertSame('Barangay Clearance', $uploaded['label']);
        $this->assertTrue($uploaded['has_file']);
        $this->assertTrue($uploaded['is_mandatory']);
        $this->assertTrue($uploaded['is_image']);
        $this->assertStringContainsString('/requirements/', $uploaded['url']);

        // A requirement with nothing uploaded still lists, so the gap is visible.
        $this->assertSame('Valid ID', $missing['label']);
        $this->assertFalse($missing['has_file']);
        $this->assertNull($missing['url']);
    }

    public function test_requirement_files_are_served_through_the_authorized_route(): void
    {
        $document = $this->documentWithRequirements();
        $requirement = $document->requirements()->first();

        $this->seedRolesAndPermissions();
        $this->actingAs(User::factory()->create()->assignRole('super_admin'));

        $payload = RequestReview::forModal($document);

        // Never a raw storage path — the stream checks department access.
        $this->assertSame(
            route('documents.requirements.file', [$document, $requirement]),
            $payload['requirements'][0]['url']
        );
    }

    public function test_supervisor_review_modal_renders_the_requirements_section(): void
    {
        $this->seedRolesAndPermissions();
        $this->documentWithRequirements();

        $this->actingAs(User::factory()->create()->assignRole('Supervisor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requirements for this request')
            ->assertSee('active.requirements', false);
    }

    public function test_staff_review_modal_shares_the_same_requirements_section(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $document = $this->documentWithRequirements();
        $document->update(['assigned_to' => $staff->id, 'status' => 'in_progress']);

        $this->actingAs($staff)
            ->get(route('staff.profile', ['user' => $staff->id, 'tab' => 'assigned']))
            ->assertOk()
            ->assertSee('Requirements for this request');
    }
}
