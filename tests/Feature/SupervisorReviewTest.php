<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Supervisor resolution flow (assign-approve / deny) and the deduplicated
 * navigation, following the in-place cockpit refactor.
 */
class SupervisorReviewTest extends TestCase
{
    use RefreshDatabase;

    private function doc(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-'.now()->format('Ymd').'-'.substr(strtoupper(uniqid()), -6),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Jane Citizen',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_supervisor_assign_approve_assigns_and_returns_json(): void
    {
        $this->seedRolesAndPermissions();
        // Department head assigns within their own department.
        $dept = Department::create(['name' => 'Records', 'code' => 'REC', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
        $staff = User::factory()->create(['department_id' => $dept->id])->assignRole('staff');
        $doc = $this->doc(['department_id' => $dept->id]);

        $this->actingAs($supervisor)
            ->postJson(route('documents.assign-approve', $doc), ['assigned_to' => $staff->id])
            ->assertOk();

        $doc->refresh();
        $this->assertSame($staff->id, $doc->assigned_to);
        $this->assertNull($doc->accepted_at); // staff must still accept
    }

    public function test_supervisor_deny_is_terminal(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $doc = $this->doc();

        $this->actingAs($supervisor)
            ->postJson(route('documents.deny', $doc), ['reason' => 'Duplicate request.'])
            ->assertOk();

        $this->assertSame('denied', $doc->fresh()->status);
    }

    public function test_plain_staff_cannot_assign_approve(): void
    {
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create()->assignRole('staff');
        $doc = $this->doc();

        $this->actingAs($staff)
            ->postJson(route('documents.assign-approve', $doc), ['assigned_to' => $staff->id])
            ->assertForbidden();
    }

    public function test_supervisor_topnav_has_no_users_link(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'));
    }

    public function test_super_admin_account_menu_drops_sidebar_duplicates(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            // Sidebar keeps "Staff"/"Settings"; the account menu no longer
            // duplicates them under these labels.
            ->assertDontSee('Staff directory');
    }
}
