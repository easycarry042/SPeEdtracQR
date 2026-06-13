<?php

namespace Tests\Feature;

use App\Models\Department;
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

    private function makeAttachment(Department $dept): DocumentAttachment
    {
        Storage::fake('local');

        $creator = User::factory()->create(['department_id' => $dept->id]);

        $document = Document::create([
            'tracking_number' => 'SPD-ATTACH-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);
        $document->syncRouteSteps([$dept->id]);

        $path = UploadedFile::fake()->image('doc.jpg')->store('document-attachments', 'local');

        return DocumentAttachment::create([
            'document_id' => $document->id,
            'file_path' => $path,
            'department_id' => $dept->id,
            'sort_order' => 0,
        ]);
    }

    public function test_staff_in_the_documents_department_can_view_attachment(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $attachment = $this->makeAttachment($dept);

        $user = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');

        $this->actingAs($user)
            ->get(route('attachments.show', $attachment))
            ->assertOk();
    }

    public function test_staff_from_another_department_is_forbidden(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $deptA = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $deptB = Department::create(['name' => 'Engineering', 'sla_hours' => 48]);
        $attachment = $this->makeAttachment($deptA);

        $outsider = User::factory()->create(['department_id' => $deptB->id])->assignRole('staff');

        $this->actingAs($outsider)
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_creator_can_view_attachment_when_document_is_routed_elsewhere(): void
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $frontDesk = Department::create(['name' => 'Front Desk', 'sla_hours' => 48]);
        $other = Department::create(['name' => 'Engineering', 'sla_hours' => 48]);

        Storage::fake('local');
        $creator = User::factory()->create(['department_id' => $frontDesk->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-ATTACH-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $other->id,
            'created_by' => $creator->id,
        ]);
        $document->syncRouteSteps([$other->id]);

        $path = UploadedFile::fake()->image('doc.jpg')->store('document-attachments', 'local');
        $attachment = DocumentAttachment::create([
            'document_id' => $document->id,
            'file_path' => $path,
            'department_id' => $other->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($creator)
            ->get(route('attachments.show', $attachment))
            ->assertOk();
    }

    public function test_guests_cannot_view_attachments(): void
    {
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $attachment = $this->makeAttachment($dept);

        $this->get(route('attachments.show', $attachment))
            ->assertRedirect(route('login'));
    }
}
