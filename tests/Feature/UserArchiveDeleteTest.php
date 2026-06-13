<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserArchiveDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_archive_restore_and_delete_a_user(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');
        $target = User::factory()->create()->assignRole('staff');

        $this->actingAs($admin)->patch(route('admin.users.archive', $target))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->actingAs($admin)->patch(route('admin.users.restore', $target))->assertRedirect();
        $this->assertNotSoftDeleted('users', ['id' => $target->id]);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $target))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_user_with_document_activity_cannot_be_permanently_deleted(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');
        $target = User::factory()->create()->assignRole('staff');

        Document::create([
            'tracking_number' => 'SPD-TEST-DEL001',
            'document_type' => 'Business Permit',
            'status' => 'pending',
            'created_by' => $target->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_or_archive_own_account(): void
    {
        $this->seedRolesAndPermissions();

        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)
            ->patch(route('admin.users.archive', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }
}
