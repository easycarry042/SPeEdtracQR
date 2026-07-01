<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_upload_multiple_attachments_to_a_document(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();

        $user = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-IMG01',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('documents.attachments.store', $document), [
            'attachments' => [
                UploadedFile::fake()->image('page-1.jpg'),
                UploadedFile::fake()->image('page-2.jpg'),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('attachments.0.url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonPath('attachments.1.url', fn ($url) => is_string($url) && $url !== '');

        $this->assertEquals(2, $document->attachments()->count());
    }

    public function test_pdf_and_word_files_are_accepted(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-DOC01',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->actingAs($user)->postJson(route('documents.attachments.store', $document), [
            'attachments' => [
                UploadedFile::fake()->create('form.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('letter.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ])->assertOk();

        $this->assertEquals(2, $document->attachments()->count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-BAD01',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);

        $this->actingAs($user)->postJson(route('documents.attachments.store', $document), [
            'attachments' => [
                UploadedFile::fake()->create('script.exe', 50, 'application/octet-stream'),
            ],
        ])->assertStatus(422);

        $this->assertEquals(0, $document->attachments()->count());
    }

    public function test_assignee_can_delete_an_attachment(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-DEL01',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
        ]);
        $path = UploadedFile::fake()->image('page.jpg')->store('document-attachments', 'local');
        $attachment = $document->attachments()->create(['file_path' => $path, 'sort_order' => 0]);

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect();

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_unrelated_staff_cannot_delete_an_attachment(): void
    {
        Storage::fake('local');
        $this->seedRolesAndPermissions();
        $owner = User::factory()->create()->assignRole('staff');
        $other = User::factory()->create()->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-DEL02',
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
        ]);
        $path = UploadedFile::fake()->image('page.jpg')->store('document-attachments', 'local');
        $attachment = $document->attachments()->create(['file_path' => $path, 'sort_order' => 0]);

        $this->actingAs($other)
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();

        $this->assertModelExists($attachment);
    }
}
