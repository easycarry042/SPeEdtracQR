<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every filter dropdown is the <x-select-menu> listbox rather than a native
 * <select>, so the pages that carry one must still render and still submit a
 * value under the same field name.
 */
class FilterDropdownTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function filterPages(): array
    {
        return [
            'admin dashboard' => ['admin.dashboard'],
            'audit log' => ['admin.audit-log.index'],
            'assignments' => ['admin.assignments.index'],
            'users' => ['admin.users.index'],
            'history' => ['history'],
            'staff directory' => ['staff.index'],
        ];
    }

    #[DataProvider('filterPages')]
    public function test_filter_pages_render_the_listbox_dropdown(string $routeName): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create()->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('aria-haspopup="listbox"', false)
            ->assertSee('data-filter-control', false);
    }

    public function test_analytics_filters_render_for_a_supervisor(): void
    {
        $this->seedRolesAndPermissions();
        $department = Department::create(['name' => 'Records', 'code' => 'REC', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $department->id])->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->get(route('analytics'))
            ->assertOk()
            ->assertSee('aria-haspopup="listbox"', false);
    }

    public function test_dropdown_value_still_filters_the_users_list(): void
    {
        $this->seedRolesAndPermissions();
        $admin = User::factory()->create(['name' => 'Ada Admin'])->assignRole('super_admin');
        $staff = User::factory()->create(['name' => 'Sam Staff'])->assignRole('staff');

        // The hidden input submits under the same name the <select> used.
        // Emails only appear in the table, unlike names (the account menu).
        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'staff']))
            ->assertOk()
            ->assertSee($staff->email)
            ->assertDontSee($admin->email);
    }
}
