<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\RoutingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_moves_through_three_departments(): void
    {
        // Seed roles so Spatie permission checks work
        Role::firstOrCreate(['name' => 'staff',           'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'receiving_staff', 'guard_name' => 'web']);

        $user  = User::factory()->create()->assignRole('staff');
        $dept1 = Department::create(['name' => 'Reception',    'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting',   'sla_hours' => 48]);
        $dept3 = Department::create(['name' => 'Mayors Office','sla_hours' => 48]);

        // Routing rules: Reception → Accounting → Mayors Office (then complete)
        RoutingRule::create(['document_type' => 'Business Permit', 'from_department_id' => $dept1->id, 'to_department_id' => $dept2->id, 'step_order' => 1]);
        RoutingRule::create(['document_type' => 'Business Permit', 'from_department_id' => $dept2->id, 'to_department_id' => $dept3->id, 'step_order' => 2]);

        // Create document directly
        $document = Document::create([
            'tracking_number'     => 'SPD-TEST-00001',
            'document_type'       => 'Business Permit',
            'citizen_name'        => 'Juan Dela Cruz',
            'status'              => 'pending',
            'created_by'          => $user->id,
        ]);

        $scan = fn ($action, $dept) => $this->actingAs($user)->postJson('/api/scan', [
            'tracking_number' => $document->tracking_number,
            'action'          => $action,
            'department_id'   => $dept->id,
        ]);

        $scan('in',  $dept1)->assertOk();
        $scan('out', $dept1)->assertOk();
        $scan('in',  $dept2)->assertOk();
        $scan('out', $dept2)->assertOk();
        $scan('in',  $dept3)->assertOk();

        $fresh = $document->fresh();
        $this->assertEquals('in_transit', $fresh->status);
        $this->assertEquals($dept3->id, $fresh->current_department_id);
    }
}
