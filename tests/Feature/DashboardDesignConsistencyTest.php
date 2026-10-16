<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The supervisor's dashboard and the staff dashboard are two different views of
 * the same workspace, and they used to look like two different products: the
 * staff one on the .st-* table system, the supervisor one still on generic
 * Tailwind greys and emeralds.
 *
 * These assert the shared furniture, so the two cannot drift apart again
 * without someone being told.
 */
class DashboardDesignConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string $role): User
    {
        $this->seedRolesAndPermissions();
        $dept = Department::factory()->create();

        return User::factory()->create(['department_id' => $dept->id])->assignRole($role);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function dashboards(): array
    {
        return [
            'staff' => ['staff', 'staff.dashboard'],
            'supervisor' => ['Supervisor', 'dashboard'],
        ];
    }

    #[DataProvider('dashboards')]
    public function test_both_dashboards_are_built_from_the_same_table_system(string $role, string $route): void
    {
        $html = $this->actingAs($this->userWith($role))
            ->get(route($route))
            ->assertOk()
            ->getContent();

        foreach (['st-card', 'st-scroll', 'st-table', 'st-toolbar', 'st-search', 'st-pager'] as $class) {
            $this->assertStringContainsString($class, $html, "{$route} should use .{$class}");
        }
    }

    #[DataProvider('dashboards')]
    public function test_both_dashboards_lead_with_the_gradient_stat_tiles(string $role, string $route): void
    {
        $html = $this->actingAs($this->userWith($role))
            ->get(route($route))
            ->assertOk()
            ->getContent();

        // The tile component's gradient wrapper — the supervisor used to draw
        // its own <x-stat-card>, a flat white panel that matched nothing.
        $this->assertStringContainsString('bg-gradient-to-r', $html);
        $this->assertStringNotContainsString('x-stat-card', $html);
    }

    #[DataProvider('dashboards')]
    public function test_neither_dashboard_falls_back_to_the_old_emerald_palette(string $role, string $route): void
    {
        $html = $this->actingAs($this->userWith($role))
            ->get(route($route))
            ->assertOk()
            ->getContent();

        // The design system has green-deep / green / green-wash. Tailwind's
        // stock emerald ramp is what the pre-redesign screens were built from.
        foreach (['bg-emerald-50', 'bg-emerald-700', 'text-emerald-950', 'divide-gray-200'] as $stale) {
            $this->assertStringNotContainsString($stale, $html, "{$route} still carries {$stale}");
        }
    }

    public function test_the_supervisor_table_pages_and_filters_like_the_staff_one(): void
    {
        $html = $this->actingAs($this->userWith('Supervisor'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        // Same browser-side mixin, same pager partial, same filter popover.
        $this->assertStringContainsString('window.stFilterMixin()', $html);
        $this->assertStringContainsString('supervisorFilter-type', $html);
        $this->assertStringContainsString('st-page-step', $html);
    }
}
