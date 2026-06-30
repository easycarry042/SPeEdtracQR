<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentEditTest extends TestCase
{
    use RefreshDatabase;

    private function document(User $creator, ?User $assignee = null): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-EDIT-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Old Name',
            'status' => 'in_progress',
            'created_by' => $creator->id,
            'assigned_to' => $assignee?->id,
        ]);
    }

    public function test_creator_can_edit_document_details(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');
        $document = $this->document($user, $user);

        $this->actingAs($user)
            ->put(route('documents.update', $document), [
                'document_type' => 'Mayor\'s Permit',
                'citizen_name' => 'New Name',
                'citizen_contact' => '09171234567',
            ])
            ->assertRedirect(route('track.show', $document->tracking_number));

        $document->refresh();
        $this->assertSame('New Name', $document->citizen_name);
        $this->assertSame("Mayor's Permit", $document->document_type);
        $this->assertSame('09171234567', $document->citizen_contact);
    }

    public function test_unrelated_staff_cannot_edit(): void
    {
        $this->seedRolesAndPermissions();
        $creator = User::factory()->create()->assignRole('staff');
        $document = $this->document($creator, $creator);

        $outsider = User::factory()->create()->assignRole('staff');

        $this->actingAs($outsider)
            ->put(route('documents.update', $document), [
                'document_type' => 'Business Permit',
                'citizen_name' => 'Hacked',
            ])
            ->assertForbidden();

        $this->assertSame('Old Name', $document->fresh()->citizen_name);
    }
}
