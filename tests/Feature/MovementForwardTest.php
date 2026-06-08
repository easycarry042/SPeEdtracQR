<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementForwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_forward_moves_document_to_next_department(): void
    {
        $this->seedRolesAndPermissions();

        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);

        $user = User::factory()->create(['department_id' => $dept1->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-FWDTEST-002',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept1->id,
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept1->id, $dept2->id]);

        $this->actingAs($user)->post(route('api.scan.store'), [
            'tracking_number' => $document->tracking_number,
            'department_id' => $dept1->id,
            'action' => 'out',
            'next_department_id' => $dept2->id,
        ])->assertOk();

        $this->assertEquals($dept2->id, $document->fresh()->current_department_id);
    }

    public function test_movements_tracking_tab_lists_forwarded_documents(): void
    {
        $this->seedRolesAndPermissions();

        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);

        $user = User::factory()->create(['department_id' => $dept1->id])->assignRole('staff');

        $document = Document::create([
            'tracking_number' => 'SPD-TRACK-001',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept2->id,
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept1->id, $dept2->id]);

        $document->scans()->create([
            'scanned_by' => $user->id,
            'department_id' => $dept1->id,
            'action' => 'out',
            'scanned_at' => now(),
            'location_ip' => '127.0.0.1',
        ]);

        $this->actingAs($user)
            ->get(route('movements.index', ['tab' => 'tracking']))
            ->assertOk()
            ->assertSee('SPD-TRACK-001');
    }
}
