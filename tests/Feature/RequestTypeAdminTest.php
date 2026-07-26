<?php

namespace Tests\Feature;

use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    public function test_admin_can_create_a_request_type_with_requirements(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.request-types.store'), [
                'name' => 'Business Permit',
                'kind' => RequestType::KIND_DOCUMENT,
                'requirements' => [
                    ['label' => 'Barangay Business Clearance', 'is_mandatory' => '1'],
                    ['label' => 'Cedula'], // no is_mandatory → optional
                ],
            ])
            ->assertRedirect(route('admin.request-types.index'));

        $type = RequestType::where('name', 'Business Permit')->with('requirements')->firstOrFail();
        $this->assertCount(2, $type->requirements);
        $this->assertTrue($type->requirements->firstWhere('label', 'Barangay Business Clearance')->is_mandatory);
        $this->assertFalse($type->requirements->firstWhere('label', 'Cedula')->is_mandatory);
    }

    public function test_admin_can_replace_the_requirement_checklist(): void
    {
        $admin = $this->admin();
        $type = RequestType::create(['name' => 'Barangay Clearance', 'kind' => RequestType::KIND_DOCUMENT]);
        $type->requirements()->create(['label' => 'Old requirement', 'is_mandatory' => true]);

        $this->actingAs($admin)
            ->put(route('admin.request-types.update', $type), [
                'name' => 'Barangay Clearance',
                'kind' => RequestType::KIND_DOCUMENT,
                'requirements' => [
                    ['label' => 'Valid Government ID', 'is_mandatory' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.request-types.index'));

        $labels = $type->fresh()->requirements->pluck('label')->all();
        $this->assertSame(['Valid Government ID'], $labels);
    }

    public function test_admin_can_create_an_equipment_type_with_a_resource_and_requirement(): void
    {
        $admin = $this->admin();
        $resource = \App\Models\Resource::create(['name' => 'Monoblock Chairs']);

        $this->actingAs($admin)
            ->post(route('admin.request-types.store'), [
                'name' => 'Chairs Borrowing',
                'kind' => RequestType::KIND_EQUIPMENT,
                'resource_id' => $resource->id,
                'requirements' => [
                    ['label' => 'Letter of Request', 'is_mandatory' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.request-types.index'));

        $type = RequestType::where('name', 'Chairs Borrowing')->with('requirements')->firstOrFail();
        $this->assertSame(RequestType::KIND_EQUIPMENT, $type->kind);
        $this->assertSame($resource->id, $type->resource_id);
        $this->assertCount(1, $type->requirements);
    }

    public function test_an_equipment_type_requires_a_resource(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.request-types.store'), [
                'name' => 'Tent Borrowing',
                'kind' => RequestType::KIND_EQUIPMENT,
            ])
            ->assertSessionHasErrors('resource_id');
    }

    public function test_staff_cannot_manage_request_types(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('admin.request-types.index'))
            ->assertRedirect();
    }
}
