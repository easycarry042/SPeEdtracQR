<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Department;
use App\Models\Document;

class DocumentFlowTest extends TestCase
{
    public function test_document_moves_through_three_departments()
    {
        $user = User::factory()->create();
        $dept1 = Department::factory()->create(['name' => 'Reception']);
        $dept2 = Department::factory()->create(['name' => 'Accounting']);
        $dept3 = Department::factory()->create(['name' => 'Mayors Office']);

        $response = $this->actingAs($user)->postJson('/api/documents', [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz'
        ]);
        $tracking = $response['document']['tracking_number'];

        // Scan IN at Reception
        $this->postJson('/api/documents/scan', [
            'tracking_number' => $tracking,
            'action' => 'in',
            'department_id' => $dept1->id
        ])->assertStatus(200);

        // Scan OUT from Reception
        $this->postJson('/api/documents/scan', [
            'tracking_number' => $tracking,
            'action' => 'out',
            'department_id' => $dept1->id
        ])->assertStatus(200);

        // Scan IN at Accounting
        $this->postJson('/api/documents/scan', [
            'tracking_number' => $tracking,
            'action' => 'in',
            'department_id' => $dept2->id
        ])->assertStatus(200);

        // Final OUT from Mayor's Office
        $this->postJson('/api/documents/scan', [
            'tracking_number' => $tracking,
            'action' => 'out',
            'department_id' => $dept3->id
        ])->assertStatus(200);

        $doc = Document::where('tracking_number', $tracking)->first();
        $this->assertEquals('completed', $doc->status);
    }
}