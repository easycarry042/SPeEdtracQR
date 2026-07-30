<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The built-in PDF editor: staff place their registered e-signature (and text)
 * on an attached PDF. The citizen's original must survive untouched — a records
 * office has to be able to show what it actually received.
 */
class PdfEditorTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create(['is_active' => true])->assignRole('staff');
        $document = Document::create([
            'tracking_number' => 'SPD-PDF-1',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'status' => 'in_progress',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);

        Storage::disk('local')->put('document-attachments/original.pdf', "%PDF-1.4\noriginal bytes\n%%EOF");
        $attachment = $document->attachments()->create([
            'file_path' => 'document-attachments/original.pdf',
            'uploaded_by' => $staff->id,
        ]);

        return [$staff, $document, $attachment];
    }

    private function editedPdf(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'edited').'.pdf';
        file_put_contents($path, "%PDF-1.7\nsigned bytes\n%%EOF");

        return new UploadedFile($path, 'edited.pdf', 'application/pdf', null, true);
    }

    public function test_the_assignee_can_open_a_pdf_in_the_editor(): void
    {
        [$staff, , $attachment] = $this->scenario();

        $this->actingAs($staff)
            ->get(route('attachments.edit', $attachment))
            ->assertOk()
            ->assertSee('Edit &amp; sign document', false)
            ->assertSee('Place signature')
            ->assertSee('pdf-editor', false);
    }

    public function test_saving_creates_a_new_version_and_keeps_the_original(): void
    {
        [$staff, $document, $attachment] = $this->scenario();

        $this->actingAs($staff)
            ->post(route('attachments.edit.store', $attachment), ['pdf' => $this->editedPdf()])
            ->assertOk()
            ->assertJsonStructure(['message', 'attachment' => ['id', 'url'], 'redirect']);

        // Two attachments now: the untouched original plus the signed copy.
        $this->assertSame(2, $document->attachments()->count());
        $this->assertSame(
            "%PDF-1.4\noriginal bytes\n%%EOF",
            Storage::disk('local')->get($attachment->file_path),
            'The original attachment must never be modified.'
        );

        $signed = $document->attachments()->latest('id')->first();
        $this->assertStringContainsString('signed-v', $signed->file_path);
        $this->assertStringContainsString('signed bytes', Storage::disk('local')->get($signed->file_path));
    }

    public function test_a_file_that_is_not_really_a_pdf_is_refused(): void
    {
        [$staff, $document, $attachment] = $this->scenario();

        $path = tempnam(sys_get_temp_dir(), 'fake').'.pdf';
        file_put_contents($path, "<?php echo 'not a pdf';");

        $this->actingAs($staff)
            ->post(route('attachments.edit.store', $attachment), [
                'pdf' => new UploadedFile($path, 'edited.pdf', 'application/pdf', null, true),
            ])
            ->assertStatus(302);

        $this->assertSame(1, $document->attachments()->count());
    }

    public function test_non_pdf_attachments_do_not_open_in_the_editor(): void
    {
        [$staff, $document] = $this->scenario();

        Storage::disk('local')->put('document-attachments/photo.jpg', 'not-a-pdf');
        $image = $document->attachments()->create([
            'file_path' => 'document-attachments/photo.jpg',
            'uploaded_by' => $staff->id,
        ]);

        $this->actingAs($staff)->get(route('attachments.edit', $image))->assertNotFound();
    }

    public function test_staff_cannot_edit_a_document_that_is_not_theirs(): void
    {
        [, , $attachment] = $this->scenario();
        $this->seedRolesAndPermissions();
        $intruder = User::factory()->create(['is_active' => true])->assignRole('staff');

        $this->actingAs($intruder)->get(route('attachments.edit', $attachment))->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('attachments.edit.store', $attachment), ['pdf' => $this->editedPdf()])
            ->assertForbidden();
    }

    public function test_guests_cannot_reach_the_editor(): void
    {
        [, , $attachment] = $this->scenario();

        $this->get(route('attachments.edit', $attachment))->assertRedirect(route('login'));
    }

    public function test_a_supervisor_may_sign_any_document_in_scope(): void
    {
        [, $document, $attachment] = $this->scenario();
        $supervisor = User::factory()->create(['is_active' => true])->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('attachments.edit.store', $attachment), ['pdf' => $this->editedPdf()])
            ->assertOk();

        $this->assertSame(2, $document->attachments()->count());
    }

    public function test_the_editor_warns_when_there_is_no_registered_signature(): void
    {
        [$staff, , $attachment] = $this->scenario();

        $this->actingAs($staff)
            ->get(route('attachments.edit', $attachment))
            ->assertOk()
            ->assertSee('no registered e-signature');

        $staff->update(['signature_path' => 'signatures/user-'.$staff->id.'.png']);

        $this->actingAs($staff->fresh())
            ->get(route('attachments.edit', $attachment))
            ->assertOk()
            ->assertDontSee('no registered e-signature');
    }

    public function test_editing_is_recorded_on_the_document_feed(): void
    {
        [$staff, $document, $attachment] = $this->scenario();

        $this->actingAs($staff)
            ->post(route('attachments.edit.store', $attachment), ['pdf' => $this->editedPdf()])
            ->assertOk();

        $this->assertTrue(
            $document->comments()->where('body', 'like', '%Edited PDF saved by%')->exists(),
            'The signed copy should be visible in the document history.'
        );
    }
}
