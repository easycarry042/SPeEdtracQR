<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Document;
use App\Models\RequestType;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One Resources page for every role. Staff and supervisors run the lending
 * desk and the stock there; a super admin sees the same page plus the catalog
 * controls (add, edit, deactivate, delete). The super admin's old Resources
 * list — no stock, no lending, and no way to add equipment — is gone.
 */
class ResourceCatalogParityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->admin = User::factory()->create()->assignRole('super_admin');
    }

    private function chairs(): Resource
    {
        return Resource::create(['name' => 'Monoblock Chairs', 'kind' => Resource::KIND_EQUIPMENT, 'total_quantity' => 100, 'is_active' => true]);
    }

    private function booking(Resource $resource, string $status, string $start, string $end): Booking
    {
        return $resource->bookings()->create([
            'document_id' => Document::create([
                'tracking_number' => 'SPD-CAT-'.strtoupper(uniqid()),
                'document_type' => 'Chairs Borrowing',
                'status' => 'in_progress',
                'quantity' => 10,
            ])->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => $status,
        ]);
    }

    public function test_the_super_admin_gets_the_same_page_as_staff_plus_the_catalog_controls(): void
    {
        $chairs = $this->chairs();
        $this->booking($chairs, Booking::STATUS_PENDING, now()->addDay()->toDateTimeString(), now()->addDays(2)->toDateTimeString());

        $this->actingAs($this->admin)->get(route('admin.resources.index'))->assertRedirect(route('inventory.index'));

        $page = $this->actingAs($this->admin)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Lending desk')
            ->assertSee('href="'.route('admin.resources.create').'"', false)
            ->assertSee('href="'.route('admin.resources.edit', $chairs).'"', false);
        $this->assertCount(1, $page->viewData('desk')['approve']);

        // The sidebar's Resources row opens it.
        $page->assertSee('href="'.route('inventory.index').'"', false);

        // Staff see the same desk and stock, without the catalog controls.
        $staff = User::factory()->create()->assignRole('staff');
        $this->actingAs($staff)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee('Lending desk')
            ->assertDontSee('href="'.route('admin.resources.create').'"', false)
            ->assertDontSee('href="'.route('admin.resources.edit', $chairs).'"', false);
    }

    public function test_the_super_admin_can_add_equipment_with_its_count(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.resources.store'), [
                'name' => 'Folding Tables',
                'kind' => Resource::KIND_EQUIPMENT,
                'total_quantity' => 40,
                'location' => 'GSO stockroom',
            ])
            ->assertRedirect(route('inventory.index'));

        $tables = Resource::where('name', 'Folding Tables')->firstOrFail();
        $this->assertTrue($tables->isEquipment());
        $this->assertSame(40, $tables->total_quantity);
        $this->assertSame('GSO stockroom', $tables->location);

        $this->actingAs($this->admin)
            ->post(route('admin.resources.store'), ['name' => 'Tents', 'kind' => Resource::KIND_EQUIPMENT, 'total_quantity' => 2.5])
            ->assertSessionHasErrors('total_quantity');
        $this->actingAs($this->admin)
            ->post(route('admin.resources.store'), ['name' => 'Tents'])
            ->assertSessionHasErrors('kind');
    }

    public function test_an_item_with_bookings_cannot_change_kind(): void
    {
        $chairs = $this->chairs();
        $this->booking($chairs, Booking::STATUS_PENDING, now()->addDay()->toDateTimeString(), now()->addDays(2)->toDateTimeString());

        $this->actingAs($this->admin)
            ->put(route('admin.resources.update', $chairs), ['name' => 'Monoblock Chairs', 'kind' => Resource::KIND_FACILITY])
            ->assertSessionHasErrors('kind');
        $this->assertTrue($chairs->fresh()->isEquipment());

        // Renaming is fine.
        $this->actingAs($this->admin)
            ->put(route('admin.resources.update', $chairs), ['name' => 'Plastic Chairs', 'kind' => Resource::KIND_EQUIPMENT])
            ->assertRedirect(route('inventory.index'));
        $this->assertSame('Plastic Chairs', $chairs->fresh()->name);
    }

    public function test_an_item_with_live_bookings_cannot_be_deleted(): void
    {
        $chairs = $this->chairs();
        $out = $this->booking($chairs, Booking::STATUS_APPROVED, now()->subDays(3)->toDateTimeString(), now()->subDay()->toDateTimeString());
        $out->update(['released_at' => now()->subDays(3)]);

        $this->actingAs($this->admin)->delete(route('admin.resources.destroy', $chairs))->assertSessionHas('error');
        $this->assertNotNull($chairs->fresh());

        // Once everything is back, it can go.
        $out->update(['returned_at' => now(), 'returned_quantity' => 10]);
        $this->actingAs($this->admin)->delete(route('admin.resources.destroy', $chairs))->assertRedirect(route('inventory.index'));
        $this->assertNull($chairs->fresh());
    }

    public function test_a_deactivated_item_is_not_offered_to_citizens(): void
    {
        $chairs = $this->chairs();
        RequestType::create(['name' => 'Chairs Borrowing', 'kind' => RequestType::KIND_EQUIPMENT, 'resource_id' => $chairs->id, 'is_active' => true]);

        $this->actingAs($this->admin)->patch(route('admin.resources.toggle-active', $chairs))->assertSessionHas('status');
        $this->assertFalse($chairs->fresh()->is_active);
        auth()->logout();

        $this->get(route('public.request.create'))->assertOk()->assertDontSee('Chairs Borrowing');

        $this->post(route('public.request.store'), [
            'document_type' => 'Chairs Borrowing',
            'citizen_name' => 'Juan Dela Cruz',
            'citizen_email' => 'juan@example.com',
            'consent' => '1',
            'quantity' => 5,
            'needed_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(2)->toDateString(),
        ])->assertSessionHasErrors('document_type');

        $this->assertSame(0, Document::count());
    }

    public function test_a_request_type_must_point_at_the_right_kind_of_item(): void
    {
        $chairs = $this->chairs();
        $court = Resource::create(['name' => 'Covered Court', 'kind' => Resource::KIND_FACILITY, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.request-types.store'), ['name' => 'Chairs Borrowing', 'kind' => RequestType::KIND_BOOKING, 'resource_id' => $chairs->id])
            ->assertSessionHasErrors('resource_id');
        $this->actingAs($this->admin)
            ->post(route('admin.request-types.store'), ['name' => 'Court Borrowing', 'kind' => RequestType::KIND_EQUIPMENT, 'resource_id' => $court->id])
            ->assertSessionHasErrors('resource_id');

        $this->actingAs($this->admin)
            ->post(route('admin.request-types.store'), ['name' => 'Chairs Borrowing', 'kind' => RequestType::KIND_EQUIPMENT, 'resource_id' => $chairs->id])
            ->assertSessionHasNoErrors();
    }
}
