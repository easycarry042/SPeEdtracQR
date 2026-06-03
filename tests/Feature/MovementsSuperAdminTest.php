<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MovementsSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        return User::factory()->create()->assignRole('super_admin');
    }

    public function test_movements_loads_for_super_admin_with_a_department_less_document(): void
    {
        $admin = $this->superAdmin();
        $dept = Department::create(['name' => 'Reception', 'sla_hours' => 48]);

        // Document still in tracking with no current department — used to crash
        // attachMeta via null->sla_hours for org-wide users.
        $pending = Document::create([
            'tracking_number' => 'SPD-NODEPT-1',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'current_department_id' => null,
            'created_by' => $admin->id,
        ]);
        $pending->syncRouteSteps([$dept->id]);

        $this->actingAs($admin)->get(route('movements.index'))->assertOk();
        $this->actingAs($admin)->get(route('movements.index', ['tab' => 'tracking']))->assertOk();
    }

    public function test_movements_renders_documents_that_have_attachments(): void
    {
        $admin = $this->superAdmin();
        $dept = Department::create(['name' => 'Reception', 'sla_hours' => 48]);

        $document = Document::create([
            'tracking_number' => 'SPD-ATTACH-MV',
            'document_type' => 'Business Permit',
            'status' => 'in_transit',
            'current_department_id' => $dept->id,
            'created_by' => $admin->id,
        ]);
        $document->syncRouteSteps([$dept->id]);
        // An attachment forces the document-images component to resolve a URL,
        // which previously crashed via DocumentAttachment::url() collision.
        DocumentAttachment::create([
            'document_id' => $document->id,
            'file_path' => 'document-attachments/example.jpg',
            'department_id' => $dept->id,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)->get(route('movements.index'))->assertOk();
    }
}
