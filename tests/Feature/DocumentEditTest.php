<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentEditTest extends TestCase
{
    use RefreshDatabase;

    private function documentAt(Department $dept, User $creator): Document
    {
        $document = Document::create([
            'tracking_number' => 'SPD-EDIT-'.uniqid(),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Old Name',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $creator->id,
        ]);
        $document->syncRouteSteps([$dept->id]);

        return $document;
    }

    public function test_staff_in_department_can_edit_document_details(): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $document = $this->documentAt($dept, $user);

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
        // These columns were previously missing, so edits were silently dropped.
        $this->assertSame('09171234567', $document->citizen_contact);
    }

    public function test_staff_from_other_department_cannot_edit(): void
    {
        $this->seedRolesAndPermissions();
        $deptA = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $deptB = Department::create(['name' => 'Engineering', 'sla_hours' => 48]);
        $creator = User::factory()->create(['department_id' => $deptA->id]);
        $document = $this->documentAt($deptA, $creator);

        $outsider = User::factory()->create(['department_id' => $deptB->id])->assignRole('staff');

        $this->actingAs($outsider)
            ->put(route('documents.update', $document), [
                'document_type' => 'Business Permit',
                'citizen_name' => 'Hacked',
            ])
            ->assertForbidden();

        $this->assertSame('Old Name', $document->fresh()->citizen_name);
    }
}
