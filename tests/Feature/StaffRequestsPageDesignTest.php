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
            ->assertSee('images/staff/seal-1.png', false)
            ->assertSee('images/staff/seal-2.png', false)
            ->assertSee('<h1 class="layout-title" data-soft-nav="title" tabindex="-1">Requests</h1>', false);
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
            ->assertSee('<p class="sb-section" id="sb-menu-label">Menu</p>', false)
            ->assertSee('aria-labelledby="sb-menu-label"', false)
            ->assertSee('images/staff/logo-white.png', false)
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

    public function test_signed_in_pages_have_a_plain_background(): void
    {
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertDontSee('doodle-bg.png', false)
            ->assertSee('bg-[var(--page-wash-base)]', false);
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
        // The lit panel carries an amber edge marker (.sb-row.is-active::before),
        // so the current page is still identifiable without colour perception,
        // and aria-current names it to screen readers.
        $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('class="nav-link sb-row is-active" aria-current="page"', false);
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

    public function test_the_table_shows_ten_rows_a_page(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 12);

        $content = $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        // The page size drives both the visible rows and the pager.
        $this->assertStringContainsString('perPage: 10', $content);
        $this->assertStringContainsString('pageItems', $content);
        $this->assertStringContainsString('Requests pages', $content);
    }

    public function test_the_table_keeps_a_fixed_size_and_scrolls_inside(): void
    {
        $content = $this->actingAs($this->staff())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        // The page fills the window and the table card takes the rest of it,
        // scrolling its rows internally under a pinned header and pager.
        $this->assertStringContainsString('class="app-fixed-height', $content);
        $this->assertStringContainsString('pb-10 pt-2 main-fixed', $content);
        $this->assertStringContainsString('<div class="st-card">', $content);
        $this->assertStringContainsString('<div class="st-scroll">', $content);
        $this->assertStringContainsString('<table class="st-table">', $content);
        $this->assertStringContainsString('class="st-pager"', $content);
        $this->assertStringNotContainsString('x-show="pageCount > 1"', $content);
    }

    /**
     * The dashboard's Review modal uses the Look Up detail card: same fixed
     * size, title row, and the four-stage bar from DocumentStatus::railStages().
     */
    public function test_the_review_modal_uses_the_look_up_card_and_four_stage_bar(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 1);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('class="dm-card dm-card-review dm-fixed', false)
            ->assertSee('<ol class="dm-steps" aria-label="Progress">', false)
            ->assertSee('window.REVIEW_RAIL = JSON.parse(', false)
            ->assertSee('Tracking Number');
    }

    /**
     * The review footer is three plain buttons: Cancel, Return for revision,
     * and the next step — "Process" when that step is the approval. No "More"
     * menu, and no link out to the full record.
     */
    public function test_the_review_footer_offers_return_for_revision_and_process(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 1);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('x-show="prog().canReturn" @click="returnOpen = true"', false)
            ->assertSee("next.value === 'approved' ? 'Process'", false)
            ->assertDontSee('moreOpen', false)
            ->assertDontSee('Open the full record');
    }

    /**
     * Each staff table's filter button opens a panel to narrow rows to one
     * request type (the filing office, on Internal) and a submitted-date range.
     */
    public function test_each_staff_table_offers_a_type_and_date_range_filter(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        foreach (['staff.dashboard' => 'Request type', 'track.index' => 'Request type', 'requests.index' => 'Requesting office'] as $route => $typeLabel) {
            $content = $this->actingAs($supervisor)
                ->get(route($route))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('...window.stFilterMixin()', $content, $route);
            $this->assertStringContainsString('role="dialog" aria-label="Filter requests"', $content, $route);
            $this->assertStringContainsString($typeLabel, $content, $route);
            $this->assertStringContainsString('x-model="fType"', $content, $route);
            $this->assertStringContainsString('type="date" x-model="fFrom"', $content, $route);
            $this->assertStringContainsString('type="date" x-model="fTo"', $content, $route);
            $this->assertStringContainsString('passesFilter(', $content, $route);
        }
    }

    /** The date-range filter compares against a plain day, not the display string. */
    public function test_rows_carry_a_machine_readable_submitted_day(): void
    {
        $staff = $this->staff();
        $this->assign($staff, 1);

        $payload = $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->viewData('requestPayload');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $payload->first()['submitted_on']);
    }

    /**
     * Dashboard, Look Up and Internal share one table design (.st-* classes
     * and the shared pager), so the three work lists cannot drift apart.
     */
    public function test_look_up_and_internal_use_the_dashboard_table_design(): void
    {
        $this->seedRolesAndPermissions();
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        foreach (['track.index', 'requests.index'] as $route) {
            $content = $this->actingAs($supervisor)
                ->get(route($route))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('class="app-fixed-height', $content, $route);
            $this->assertStringContainsString('class="st-search"', $content, $route);
            $this->assertStringContainsString('class="st-tool-btn"', $content, $route);
            $this->assertStringContainsString('<div class="st-card">', $content, $route);
            $this->assertStringContainsString('class="st-table"', $content, $route);
            $this->assertStringContainsString('class="st-pager"', $content, $route);
            $this->assertStringContainsString('perPage: 10', $content, $route);
        }
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
