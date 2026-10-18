<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One stage rail, counted the same way everywhere.
 *
 * The app has five statuses but shows four stages: In Progress and In Review
 * are one stage to anyone reading a progress bar, and two separate moves to
 * the staff member advancing the document. For a while the two models leaked
 * into the UI side by side — the supervisor's dashboard drew five stages while
 * Look Up drew four — so the same request was "stage 3 of 5" on one screen and
 * "stage 3 of 4" on another. A citizen told one number at the counter must not
 * find a different one on their tracking link.
 */
class StageRailConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $status = 'in_review'): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-RAIL-'.strtoupper(uniqid()),
            'document_type' => 'Business Permit',
            'citizen_name' => 'Juan Dela Cruz',
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    /** Every label the rail is allowed to show. */
    private function railLabels(): array
    {
        return array_column(DocumentStatus::railStages(), 'label');
    }

    public function test_the_rail_has_four_stages_not_five(): void
    {
        $this->assertCount(4, DocumentStatus::railStages());
        $this->assertSame(['Pending', 'In review', 'Processing', 'Completed'], $this->railLabels());
    }

    public function test_in_progress_and_in_review_share_one_stage(): void
    {
        $this->assertSame(2, DocumentStatus::InProgress->railStage());
        $this->assertSame(2, DocumentStatus::InReview->railStage());
        // …while they remain two distinct statuses staff move between.
        $this->assertNotSame(DocumentStatus::InProgress, DocumentStatus::InReview);
    }

    public function test_the_citizen_tracking_page_counts_in_fours(): void
    {
        $document = $this->document();

        $content = $this->get(route('track.show', $document->tracking_number))
            ->assertOk()
            ->getContent();

        // The rail's stage captions, not prose elsewhere on the page. The
        // citizen page draws the Look Up rail (<x-stage-bar>) now, so the
        // caption class is the shared .dm-step-label rather than the old
        // stepper's .cap.
        preg_match_all('/class="dm-step-label">([^<]+)</', $content, $matches);

        $this->assertSame($this->railLabels(), array_map('trim', $matches[1]));
    }

    /**
     * The citizen's bar and the staff bar are one component now. Before this
     * they were two different drawings of the same four stages — the citizen
     * got numbered circles on a brass line, staff got dots on a green one — so
     * nobody could tell from a screenshot whether the two agreed.
     */
    public function test_the_citizen_rail_is_the_look_up_rail_in_the_citizen_palette(): void
    {
        $content = $this->get(route('track.show', $this->document()->tracking_number))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dm-steps-cz', $content);
        // .stp / .cap were the old stepper's own furniture.
        $this->assertStringNotContainsString('class="stp"', $content, 'the old citizen stepper should be gone');
        $this->assertStringNotContainsString('class="cap', $content, 'the old citizen stepper should be gone');
    }

    public function test_the_supervisor_dashboard_slip_counts_in_fours(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');

        // Old enough to be at risk, which is what the slip picks up.
        $document = $this->document();
        $document->update(['department_id' => $dept->id, 'status_changed_at' => now()->subDays(30)]);

        $slip = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk()->viewData('slip');

        $this->assertNotNull($slip, 'An overdue document should surface on the slip.');
        $this->assertSame($this->railLabels(), $slip['stages']);
        $this->assertLessThanOrEqual(4, $slip['current']);
    }

    /**
     * The slip names one document; the at-risk list names all of them. The
     * second-worst offender is the one still worth saving and the slip never
     * mentions it — so the full list sits on the assignee's own dashboard,
     * drawn with the same rail.
     */
    public function test_the_staff_dashboard_lists_every_at_risk_request_not_just_the_worst(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');

        foreach ([40, 30, 20] as $i => $daysOver) {
            $this->document()->update([
                'tracking_number' => 'SPD-LATE-'.$i,
                'assigned_to' => $staff->id,
                'assigned_at' => now(),
                'status_changed_at' => now()->subDays($daysOver),
            ]);
        }

        $response = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();

        $this->assertSame(3, $response->viewData('atRiskCount'));
        $response->assertSee('At-risk requests');
        foreach (['SPD-LATE-0', 'SPD-LATE-1', 'SPD-LATE-2'] as $tracking) {
            $response->assertSee($tracking);
        }

        // Soonest to breach first — the order the queue should be worked in.
        $this->assertSame(
            ['SPD-LATE-0', 'SPD-LATE-1', 'SPD-LATE-2'],
            $response->viewData('atRisk')->pluck('tracking_number')->all()
        );
    }

    /** The at-risk rows carry the same four-stage rail as every other surface. */
    public function test_the_staff_at_risk_rows_draw_the_rail(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');
        $document = $this->document();
        $document->update([
            'tracking_number' => 'SPD-LATE-RAIL',
            'assigned_to' => $staff->id,
            'assigned_at' => now(),
            'status_changed_at' => now()->subDays(30),
        ]);

        $content = $this->actingAs($staff)->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dm-steps-sm', $content);
        // The row opens the queue's own Review modal rather than sending the
        // assignee to another page to act on their own work.
        $this->assertStringContainsString('openById('.$document->id.')', $content);
    }

    /**
     * A staff member's at-risk list is their own. Work assigned to someone else
     * is that person's to rescue; listing it here would put one warning on
     * several desks with nothing to say whose it was.
     */
    public function test_the_staff_at_risk_list_is_scoped_to_the_assignee(): void
    {
        $this->seedRolesAndPermissions();

        $staff = User::factory()->create()->assignRole('staff');
        $colleague = User::factory()->create()->assignRole('staff');

        $this->document()->update([
            'tracking_number' => 'SPD-LATE-THEIRS',
            'assigned_to' => $colleague->id,
            'assigned_at' => now(),
            'status_changed_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk();

        $this->assertSame(0, $response->viewData('atRiskCount'));
        $response->assertDontSee('SPD-LATE-THEIRS');
    }

    /** The supervisor desk keeps the slip and no longer repeats the list. */
    public function test_the_supervisor_dashboard_no_longer_carries_the_at_risk_list(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');

        $this->document()->update([
            'tracking_number' => 'SPD-LATE-DESK',
            'department_id' => $dept->id,
            'status_changed_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        $response->assertDontSee('At-risk documents');
        $response->assertDontSee('At-risk requests');
        $this->assertNotNull($response->viewData('slip'), 'The worst offender still leads as a slip.');
    }
}
