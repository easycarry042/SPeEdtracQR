<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_moves_through_three_departments(): void
    {
        // Seed real roles + permissions so the can() gates behave like production.
        $this->seedRolesAndPermissions();

        $user = User::factory()->create()->assignRole('staff');
        $dept1 = Department::create(['name' => 'Reception',    'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting',   'sla_hours' => 48]);
        $dept3 = Department::create(['name' => 'Mayors Office', 'sla_hours' => 48]);

        // Create document with a per-document route: Reception → Accounting → Mayors Office
        $document = Document::create([
            'tracking_number' => 'SPD-TEST-00001',
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept1->id, $dept2->id, $dept3->id]);

        $scan = fn ($action, $dept) => $this->actingAs($user)->postJson('/api/scan', [
            'tracking_number' => $document->tracking_number,
            'action' => $action,
            'department_id' => $dept->id,
        ]);

        $scan('in', $dept1)->assertOk();
        $scan('out', $dept1)->assertOk();
        $scan('in', $dept2)->assertOk();
        $scan('out', $dept2)->assertOk();
        $scan('in', $dept3)->assertOk();

        $fresh = $document->fresh();
        $this->assertEquals('in_transit', $fresh->status);
        $this->assertEquals($dept3->id, $fresh->current_department_id);
    }
}
