<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentRouteStep;
use App\Models\RoutingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_creation_stores_custom_route(): void
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create()->assignRole('staff');
        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);
        $dept3 = Department::create(['name' => 'Records', 'sla_hours' => 12]);

        $this->actingAs($user)->post(route('documents.store'), [
            'document_type' => 'Business Permit',
            'citizen_name' => 'Maria Santos',
            'citizen_contact' => '09170001111',
            'purpose' => 'Renewal',
            'description' => 'Annual business permit renewal',
            'route_departments' => [$dept1->id, $dept3->id, $dept2->id],
        ])->assertRedirect();

        $document = Document::where('citizen_name', 'Maria Santos')->first();
        $this->assertNotNull($document);
        // These fields used to be silently dropped (missing columns).
        $this->assertSame('09170001111', $document->citizen_contact);
        $this->assertSame('Renewal', $document->purpose);
        $this->assertSame('Annual business permit renewal', $document->description);

        $this->assertEquals(
            [$dept1->id, $dept3->id, $dept2->id],
            DocumentRouteStep::where('document_id', $document->id)
                ->orderBy('step_order')
                ->pluck('department_id')
                ->all()
        );

        $document->update(['current_department_id' => $dept1->id, 'status' => 'in_transit']);

        $this->assertEquals($dept3->id, $document->getNextDepartment()?->id);
        $this->assertEquals(
            ['Reception', 'Records', 'Accounting'],
            $document->getRoutingChain()->pluck('name')->all()
        );
    }

    public function test_routing_uses_only_route_steps_not_global_rules(): void
    {
        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);

        // A global rule exists for this type, but the document has no route_steps.
        RoutingRule::create([
            'document_type' => 'Business Permit',
            'from_department_id' => $dept1->id,
            'to_department_id' => $dept2->id,
            'step_order' => 1,
        ]);

        $document = Document::create([
            'tracking_number' => 'SPD-NOSTEPS-1',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept1->id,
            'created_by' => User::factory()->create()->id,
        ]);

        // route_steps is the single source of truth — global rules are ignored.
        $this->assertTrue($document->getRoutingChain()->isEmpty());
        $this->assertNull($document->getNextDepartment());
    }

    public function test_scan_out_follows_per_document_route(): void
    {
        $this->seedRolesAndPermissions();

        $user = User::factory()->create()->assignRole('staff');
        $dept1 = Department::create(['name' => 'Reception', 'sla_hours' => 48]);
        $dept2 = Department::create(['name' => 'Accounting', 'sla_hours' => 48]);

        $document = Document::create([
            'tracking_number' => 'SPD-TEST-ROUTE01',
            'document_type' => 'Other',
            'status' => 'in_transit',
            'current_department_id' => $dept1->id,
            'created_by' => $user->id,
        ]);
        $document->syncRouteSteps([$dept1->id, $dept2->id]);

        $this->actingAs($user)->postJson('/api/scan', [
            'tracking_number' => $document->tracking_number,
            'action' => 'out',
            'department_id' => $dept1->id,
        ])->assertOk();

        $this->assertEquals($dept2->id, $document->fresh()->current_department_id);
    }
}
