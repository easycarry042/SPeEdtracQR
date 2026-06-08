<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentUndoScanTest extends TestCase
{
    use RefreshDatabase;

    private function scan(Document $doc, User $user, int $deptId, string $action, $at): void
    {
        $doc->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $deptId,
            'action' => $action,
            'scanned_at' => $at,
            'location_ip' => '127.0.0.1',
        ]);
    }

    public function test_undoing_an_out_returns_document_to_previous_department(): void
    {
        $this->seedRolesAndPermissions();
        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept1->id])->assignRole('staff');

        // Document was received at dept1, then forwarded to dept2.
        $document = Document::create([
            'tracking_number' => 'SPD-UNDO-1',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept2->id,
            'created_by' => $user->id,
        ]);
        $this->scan($document, $user, $dept1->id, 'in', now()->subHours(2));
        $this->scan($document, $user, $dept1->id, 'out', now()->subHour());

        $this->actingAs($user)
            ->post(route('documents.undo-scan', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertEquals($dept1->id, $document->current_department_id);
        $this->assertSame('in_transit', $document->status);
        $this->assertSame(1, $document->scans()->count());
    }

    public function test_undoing_the_only_scan_makes_document_pending_again(): void
    {
        $this->seedRolesAndPermissions();
        $dept = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $user = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-UNDO-2',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $user->id,
        ]);
        $this->scan($document, $user, $dept->id, 'in', now()->subHour());

        $this->actingAs($user)
            ->post(route('documents.undo-scan', $document))
            ->assertRedirect();

        $document->refresh();
        $this->assertNull($document->current_department_id);
        $this->assertSame('pending', $document->status);
        $this->assertSame(0, $document->scans()->count());
    }

    public function test_staff_cannot_undo_another_departments_scan(): void
    {
        $this->seedRolesAndPermissions();
        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $owner = User::factory()->create(['department_id' => $dept1->id]);
        $outsider = User::factory()->create(['department_id' => $dept2->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-UNDO-3',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept1->id,
            'created_by' => $owner->id,
        ]);
        $this->scan($document, $owner, $dept1->id, 'in', now()->subHour());

        $this->actingAs($outsider)
            ->post(route('documents.undo-scan', $document))
            ->assertForbidden();

        $this->assertSame(1, $document->scans()->count());
    }
}
