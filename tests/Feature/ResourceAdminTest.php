<?php

namespace Tests\Feature;

use App\Models\RequestType;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('super_admin');
    }

    public function test_admin_can_create_a_resource(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.resources.store'), ['name' => 'Municipal Plaza'])
            ->assertRedirect(route('admin.resources.index'));

        $this->assertDatabaseHas('resources', ['name' => 'Municipal Plaza', 'is_active' => true]);
    }

    public function test_admin_can_create_a_booking_type_linked_to_a_resource(): void
    {
        $admin = $this->admin();
        $resource = Resource::create(['name' => 'Covered Court']);

        $this->actingAs($admin)
            ->post(route('admin.request-types.store'), [
                'name' => 'Covered Court Reservation',
                'kind' => RequestType::KIND_BOOKING,
                'resource_id' => $resource->id,
            ])
            ->assertRedirect(route('admin.request-types.index'));

        $type = RequestType::where('name', 'Covered Court Reservation')->firstOrFail();
        $this->assertSame(RequestType::KIND_BOOKING, $type->kind);
        $this->assertSame($resource->id, $type->resource_id);
    }

    public function test_a_booking_type_requires_a_resource(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.request-types.store'), [
                'name' => 'Sound System Request',
                'kind' => RequestType::KIND_BOOKING,
            ])
            ->assertSessionHasErrors('resource_id');
    }

    public function test_staff_cannot_manage_resources(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)->get(route('admin.resources.index'))->assertRedirect();
    }
}
