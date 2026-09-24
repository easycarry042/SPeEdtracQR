<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff Requests page: one sidebar shell for every signed-in role, the two
 * municipal seals leading the page bar, three headline tiles, and a table that
 * shows five rows at a time.
 */
class StaffRequestsPageDesignTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function assign(User $staff, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Document::create([
                'tracking_number' => sprintf('SPD-20260101-A%05d', $i),
                'document_type' => 'Business Permit',
                'citizen_name' => "Citizen {$i}",
                'status' => 'in_progress',
                'assigned_to' => $staff->id,
                'assigned_at' => now(),
                'accepted_at' => now(),
            ]);
        }
    }

    public function test_the_page_bar_leads_with_both_municipal_seals(): void
    {
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('images/SPL-logo.png', false)
            ->assertSee('images/TOURISM-logo.png', false)
            ->assertSee('<h1 class="layout-title">Requests</h1>', false);
    }

    public function test_the_identity_chip_names_the_person_role_and_desk(): void
    {
        // Department scoping decides what a staff member can see, so the chip
        // names the desk rather than leaving it implied by the initials.
        $department = Department::factory()->create(['name' => 'Tourism Office']);
        $this->seedRolesAndPermissions();
        $staff = User::factory()->create([
            'name' => 'Ana Cruz',
            'department_id' => $department->id,
        ])->assignRole('staff');

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Ana Cruz')
            ->assertSee('Tourism Office')
            ->assertSee('AC');
    }

    public function test_staff_now_get_the_sidebar_shell(): void
    {
        // Staff used to get a horizontal topnav; the whole app is one sidebar
        // shell now, so the link set is the only thing that varies by role.
        $content = $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sidebar-pinned', $content);
        $this->assertStringNotContainsString('topnav-bar', $content);

        foreach (['Dashboard', 'Look Up', 'Internal'] as $link) {
            $this->assertStringContainsString($link, $content);
        }
    }

    public function test_the_sidebar_names_its_menu_and_offers_the_accessibility_toolbar(): void
    {
        // Sienna parks its own launcher at the screen edge where it is easy to
        // miss, so the sidebar gives it a named row.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Main Menu')
            ->assertSee('images/icon-white.png', false)
            ->assertSee('Accessibility')
            ->assertSee(".asw-menu-btn')?.click()", false);
    }

    public function test_the_page_bar_drops_the_role_badge(): void
    {
        // The identity chip on the right already names the role; the badge beside
        // the title said it twice.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('px-2.5 py-0.5 text-xs font-semibold text-green-deep', false);
    }

    public function test_the_floating_launcher_is_hidden_behind_the_sign_in(): void
    {
        // Signed-in pages reach the toolbar from the sidebar row, so the vendor
        // launcher is parked off-screen — it keeps its click handler, which is
        // what that row fires. Public pages have no sidebar, so it stays.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('left: -9999px !important', false);

        $this->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertDontSee('left: -9999px !important', false);
    }

    public function test_signed_in_pages_carry_the_same_wash_as_the_public_ones(): void
    {
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('images/doodle-bg.png', false)
            // The doodle without the public pages' colour ramp, which would
            // fight the green sidebar and the white panels.
            ->assertSee('var(--page-wash-veil)', false)
            ->assertDontSee('background-image: var(--page-wash),', false);
    }

    public function test_the_page_bar_fades_into_the_page_instead_of_ruling_it_off(): void
    {
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('bg-gradient-to-b from-white via-white/85 to-transparent', false);
    }

    public function test_the_active_nav_row_is_marked_by_more_than_colour(): void
    {
        // The lit panel carries a brass edge marker, so the current page is
        // still identifiable without colour perception.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('before:bg-brass', false);
    }

    public function test_the_three_headline_tiles_count_the_staff_members_work(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 2);

        Document::create([
            'tracking_number' => 'SPD-20260101-PEND1',
            'document_type' => 'Cedula',
            'status' => 'pending',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Pending')
            ->assertSee('In Progress')
            ->assertSee('Completed')
            ->assertViewHas('pendingCount', 1)
            ->assertViewHas('inProgressCount', 2);
    }

    public function test_the_table_shows_five_rows_a_page(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 12);

        $content = $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        // The page size drives both the visible rows and the pager.
        $this->assertStringContainsString('perPage: 5', $content);
        $this->assertStringContainsString('pageItems', $content);
        $this->assertStringContainsString('Requests pages', $content);
    }

    public function test_the_search_box_filters_without_a_round_trip(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 3);

        // Every assigned row is already in the Alpine payload, so filtering and
        // paging must not go back to the server for rows it already holds.
        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('id="requestSearch"', false)
            ->assertSee('SPD-20260101-A00003', false);
    }
}
