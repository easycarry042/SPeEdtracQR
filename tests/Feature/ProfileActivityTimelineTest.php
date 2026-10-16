<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * My Profile → Activity renders the log as a timeline: a coloured dot per
 * event, the time, a headline, and the reason underneath. The colour is the
 * feature — a staff member scanning the column has to be able to find the
 * entry that needs them without reading every line.
 */
class ProfileActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create()->assignRole('staff');
    }

    private function doc(User $user): Document
    {
        return Document::create([
            'tracking_number' => 'SPD-TL-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::InProgress->value,
            'source' => 'walk_in',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status_changed_at' => now(),
        ]);
    }

    public function test_the_activity_tab_draws_events_on_a_timeline(): void
    {
        $user = $this->staff();
        $doc = $this->doc($user);

        activity()->performedOn($doc)->causedBy($user)->log('Assigned to Paolo Mendoza');

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $user->id, 'tab' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sp-tl-row', $html, 'the feed should render as timeline rows');
        $this->assertStringContainsString('sp-tl-headline', $html);
        $this->assertStringContainsString('Assigned to Paolo Mendoza', $html);
    }

    public function test_an_entry_that_needs_attention_is_marked_red(): void
    {
        $user = $this->staff();
        $doc = $this->doc($user);

        activity()->performedOn($doc)->causedBy($user)
            ->log('Returned for revision at Municipal Engineering Office: Please attach the approved equipment list.');

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $user->id, 'tab' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sp-tl-row t-red', $html);
        // The reason is split onto its own line rather than run into the headline.
        $this->assertStringContainsString('sp-tl-detail', $html);
        $this->assertStringContainsString('Please attach the approved equipment list.', $html);
    }

    public function test_routine_and_finished_events_take_their_own_colours(): void
    {
        $user = $this->staff();
        $doc = $this->doc($user);

        activity()->performedOn($doc)->causedBy($user)->log('Reviewed requirement "Barangay Business Clearance": Approved');
        activity()->performedOn($doc)->causedBy($user)->log('Posted public message on document');

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $user->id, 'tab' => 'activity']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('sp-tl-row t-green', $html);
        $this->assertStringContainsString('sp-tl-row t-blue', $html);
    }

    public function test_a_long_run_of_updates_folds_after_the_first_few(): void
    {
        $user = $this->staff();
        $doc = $this->doc($user);

        foreach (range(1, 7) as $i) {
            activity()->performedOn($doc)->causedBy($user)->log("Assigned to Paolo Mendoza ({$i})");
        }

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $user->id, 'tab' => 'activity']))
            ->assertOk()
            ->getContent();

        // Four shown, three folded away behind the control — all seven are in
        // the page, so nothing is lost, it is just not all shouting at once.
        $this->assertSame(7, substr_count($html, 'sp-tl-row'), 'every update should still be in the page');
        // Matched on the row itself — the "Show fewer" control carries the same
        // x-show, and counting the bare attribute counted it too.
        $this->assertSame(
            3,
            preg_match_all('/<li class="sp-tl-row[^"]*" x-show="open" x-cloak>/', $html),
            'the overflow rows should be folded'
        );
        $this->assertStringContainsString('Show 3 earlier updates', $html);
    }
}
