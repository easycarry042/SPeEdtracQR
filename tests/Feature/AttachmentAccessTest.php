<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttachment(User $creator, ?User $assignee = null): DocumentAttachment
    {
        Storage::fake('local');

        $document = Document::create([
            'tracking_number' => 'SPD-ATTACH-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_progress',
            'created_by' => $creator->id,
            'assigned_to' => $assignee?->id,
        ]);

        $path = UploadedFile::fake()->image('doc.jpg')->store('document-attachments', 'local');

        return DocumentAttachment::create([
            'document_id' => $document->id,
            'file_path' => $path,
            'sort_order' => 0,
        ]);
    }

    public function test_assigned_staff_can_view_attachment(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $creator = User::factory()->create()->assignRole('staff');
        $assignee = User::factory()->create()->assignRole('staff');
        $attachment = $this->makeAttachment($creator, $assignee);

        $this->actingAs($assignee)
            ->get(route('attachments.show', $attachment))
            ->assertOk();
    }

    public function test_unrelated_staff_is_forbidden(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $creator = User::factory()->create()->assignRole('staff');
        $assignee = User::factory()->create()->assignRole('staff');
        $attachment = $this->makeAttachment($creator, $assignee);

        $outsider = User::factory()->create()->assignRole('staff');

        $this->actingAs($outsider)
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_creator_can_view_attachment_when_assigned_elsewhere(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $creator = User::factory()->create()->assignRole('staff');
        $assignee = User::factory()->create()->assignRole('staff');
        $attachment = $this->makeAttachment($creator, $assignee);

        $this->actingAs($creator)
            ->get(route('attachments.show', $attachment))
            ->assertOk();
    }

    public function test_guests_cannot_view_attachments(): void
    {
        $creator = User::factory()->create();
        $attachment = $this->makeAttachment($creator);

        $this->get(route('attachments.show', $attachment))
            ->assertRedirect(route('login'));
    }
}
