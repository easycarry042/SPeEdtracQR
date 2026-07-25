<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_deactivate_and_activate_accounts(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');
        $a = User::factory()->create(['is_active' => true])->assignRole('staff');
        $b = User::factory()->create(['is_active' => true])->assignRole('staff');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), ['action' => 'activate', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success');

        $this->assertTrue($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
    }

    public function test_admin_can_bulk_archive_accounts(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');
        $a = User::factory()->create()->assignRole('staff');
        $b = User::factory()->create()->assignRole('staff');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), ['action' => 'archive', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $a->id]);
        $this->assertSoftDeleted('users', ['id' => $b->id]);
    }

    public function test_bulk_action_never_touches_the_acting_admins_own_account(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create(['is_active' => true])->assignRole('super_admin');
        $other = User::factory()->create(['is_active' => true])->assignRole('staff');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$admin->id, $other->id]])
            ->assertSessionHas('success');

        $this->assertTrue($admin->fresh()->is_active, 'admin must not deactivate self via bulk');
        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_bulk_action_rejects_unknown_action(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');
        $a = User::factory()->create()->assignRole('staff');

        $this->actingAs($admin)
            ->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$a->id]])
            ->assertSessionHasErrors('action');
    }

    public function test_staff_without_permission_cannot_bulk_act(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');
        $a = User::factory()->create(['is_active' => true])->assignRole('staff');

        // The app redirects unauthorized users to their dashboard rather than 403ing.
        $this->actingAs($staff)
            ->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$a->id]])
            ->assertRedirect();

        $this->assertTrue($a->fresh()->is_active, 'staff without permission must not change accounts');
    }
}
