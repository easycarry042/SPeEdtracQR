<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The layout's page bar already titles every page, and it is the page's only
 * <h1>. Views must not add a second heading repeating that title — it rendered
 * as two stacked headings saying the same thing.
 *
 * Asserted as an <h1> count rather than a text count: the title text also
 * appears in the nav, so counting the words measures the sidebar, not the body.
 */
class PageTitleDuplicationTest extends TestCase
{
    use RefreshDatabase;

    /** A Supervisor gets the topnav shell, whose page bar renders the <h1>. */
    private function supervisor(): User
    {
        $this->seedRolesAndPermissions();
        $dept = Department::factory()->create();

        return User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'services report' => ['reports.services'],
            'staff directory' => ['staff.index'],
            'internal requests' => ['requests.index'],
            'analytics' => ['analytics'],
            'history' => ['history'],
            'dashboard' => ['dashboard'],
        ];
    }

    #[DataProvider('pages')]
    public function test_each_page_renders_exactly_one_top_level_heading(string $route): void
    {
        $content = $this->actingAs($this->supervisor())
            ->get(route($route))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($content, '<h1'),
            "{$route} should render exactly one <h1> (the layout page bar)."
        );
    }

    public function test_internal_requests_keeps_its_file_request_action(): void
    {
        // Removing the heading must not take the page's only action with it.
        $this->actingAs($this->supervisor())
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('File Request');
    }

    public function test_staff_directory_keeps_its_search(): void
    {
        $this->actingAs($this->supervisor())
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Name or email');
    }

    public function test_history_no_longer_renders_a_second_heading(): void
    {
        $this->actingAs($this->supervisor())
            ->get(route('history'))
            ->assertOk()
            ->assertDontSee('Document History');
    }

    public function test_title_row_actions_reach_sidebar_users_too(): void
    {
        // Super admins get the sidebar shell, not the topnav one. A page action
        // moved into the title row must render for them as well.
        $this->seedRolesAndPermissions();
        $dept = Department::factory()->create();
        $admin = User::factory()->create(['department_id' => $dept->id])->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('File Request');
    }
}
