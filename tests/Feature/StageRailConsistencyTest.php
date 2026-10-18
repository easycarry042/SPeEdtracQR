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

        // The stepper's stage captions, not prose elsewhere on the page.
        preg_match_all('/class="cap[^"]*">([^<]+)</', $content, $matches);

        $this->assertSame($this->railLabels(), array_map('trim', $matches[1]));
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
     * The slip names one document; the at-risk list names all of them. Both
     * belong on the page — the second-worst offender is the one a department
     * head can still save, and the slip never mentions it.
     */
    public function test_the_dashboard_lists_every_at_risk_document_not_just_the_worst(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');

        foreach ([40, 30, 20] as $i => $daysOver) {
            $this->document()->update([
                'tracking_number' => 'SPD-LATE-'.$i,
                'department_id' => $dept->id,
                'status_changed_at' => now()->subDays($daysOver),
            ]);
        }

        $response = $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();

        $this->assertSame(3, $response->viewData('atRiskCount'));
        $response->assertSee('At-risk documents');
        foreach (['SPD-LATE-0', 'SPD-LATE-1', 'SPD-LATE-2'] as $tracking) {
            $response->assertSee($tracking);
        }
        $this->assertNotNull($response->viewData('slip'), 'The worst offender still leads as a slip.');
    }

    /**
     * The at-risk tracking number used to open the older per-document page,
     * so a department head clicking from the dashboard got a different Look Up
     * than the staff one. It deep links the current desk instead.
     */
    public function test_an_at_risk_tracking_number_opens_the_look_up_desk(): void
    {
        $this->seedRolesAndPermissions();

        $dept = Department::create(['name' => 'General Services', 'code' => 'GSO', 'is_active' => true]);
        $supervisor = User::factory()->create(['department_id' => $dept->id])->assignRole('Supervisor');

        $document = $this->document();
        $document->update([
            'tracking_number' => 'SPD-LATE-DESK',
            'department_id' => $dept->id,
            'status_changed_at' => now()->subDays(30),
        ]);

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('track.index', ['open' => 'SPD-LATE-DESK']), false);
    }
}
