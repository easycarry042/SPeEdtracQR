<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Support\StaffProfile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The figures under the completion grid.
 *
 * The grid on its own only says "busy sometimes"; these are the numbers that
 * make the panel worth reading, so the arithmetic behind them is pinned here.
 */
class CompletionActivityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('staff');

        return $user;
    }

    private function completed(User $user, string $type, string $when): void
    {
        Document::create([
            'tracking_number' => 'SPD-'.str()->random(12),
            'document_type' => $type,
            'status' => 'completed',
            'assigned_to' => $user->id,
            'created_at' => now()->parse($when)->subDay(),
            'completed_at' => now()->parse($when),
        ]);
    }

    public function test_an_empty_profile_reports_zeroes_not_nulls(): void
    {
        $data = (new StaffProfile($this->staff()))->heatmap();

        $this->assertSame(0, $data['total']);
        $this->assertSame(0, $data['last30']);
        $this->assertNull($data['busiest_type']);
        $this->assertNull($data['peak_day']);
        $this->assertSame(0, $data['current_streak']);
        $this->assertSame(0, $data['longest_streak']);
    }

    public function test_it_names_the_type_this_person_handles_most(): void
    {
        $user = $this->staff();
        $this->completed($user, 'Business Permit', '-3 days');
        $this->completed($user, 'Business Permit', '-4 days');
        $this->completed($user, 'Cedula', '-5 days');

        $data = (new StaffProfile($user))->heatmap();

        $this->assertSame('Business Permit', $data['busiest_type']);
        $this->assertSame(2, $data['busiest_type_count']);
    }

    public function test_the_peak_day_is_the_busiest_single_day(): void
    {
        $user = $this->staff();
        $this->completed($user, 'A', '-10 days');
        $this->completed($user, 'A', '-2 days');
        $this->completed($user, 'B', '-2 days');
        $this->completed($user, 'C', '-2 days');

        $data = (new StaffProfile($user))->heatmap();

        $this->assertSame(now()->subDays(2)->toDateString(), $data['peak_day']);
        $this->assertSame(3, $data['peak_day_count']);
    }

    public function test_last_thirty_days_excludes_older_work(): void
    {
        $user = $this->staff();
        $this->completed($user, 'A', '-5 days');
        $this->completed($user, 'A', '-29 days');
        $this->completed($user, 'A', '-80 days');

        $this->assertSame(2, (new StaffProfile($user))->heatmap()['last30']);
    }

    public function test_consecutive_days_make_a_streak(): void
    {
        $user = $this->staff();
        foreach ([0, 1, 2, 3] as $back) {
            $this->completed($user, 'A', "-{$back} days");
        }

        $data = (new StaffProfile($user))->heatmap();

        $this->assertSame(4, $data['current_streak']);
        $this->assertSame(4, $data['longest_streak']);
    }

    /** Two completions on one day are one day of the streak, not two. */
    public function test_a_double_day_does_not_inflate_the_streak(): void
    {
        $user = $this->staff();
        $this->completed($user, 'A', '-1 days');
        $this->completed($user, 'B', '-1 days');

        $this->assertSame(1, (new StaffProfile($user))->heatmap()['current_streak']);
    }

    /** A gap ends the run; the best run is still remembered. */
    public function test_a_broken_run_keeps_the_longest_but_resets_the_current(): void
    {
        $user = $this->staff();
        foreach ([20, 21, 22, 23, 24] as $back) {
            $this->completed($user, 'A', "-{$back} days");
        }
        $this->completed($user, 'A', '-1 days');

        $data = (new StaffProfile($user))->heatmap();

        $this->assertSame(5, $data['longest_streak']);
        $this->assertSame(1, $data['current_streak']);
    }

    /**
     * At 9am nobody has finished anything yet. A run that ends yesterday is
     * still running, or every streak would die overnight.
     */
    public function test_a_run_ending_yesterday_still_counts_as_current(): void
    {
        $user = $this->staff();
        foreach ([1, 2, 3] as $back) {
            $this->completed($user, 'A', "-{$back} days");
        }

        $this->assertSame(3, (new StaffProfile($user))->heatmap()['current_streak']);
    }

    /** Two days of silence does end it. */
    public function test_a_run_ending_before_yesterday_is_over(): void
    {
        $user = $this->staff();
        foreach ([2, 3, 4] as $back) {
            $this->completed($user, 'A', "-{$back} days");
        }

        $data = (new StaffProfile($user))->heatmap();

        $this->assertSame(0, $data['current_streak']);
        $this->assertSame(3, $data['longest_streak']);
    }

    /** Another person's work is not this person's activity. */
    public function test_it_counts_only_this_persons_completions(): void
    {
        $user = $this->staff();
        $other = User::factory()->create(['is_active' => true]);
        $this->completed($user, 'A', '-1 days');
        $this->completed($other, 'A', '-1 days');
        $this->completed($other, 'A', '-2 days');

        $this->assertSame(1, (new StaffProfile($user))->heatmap()['total']);
    }

    public function test_the_panel_renders_the_grid_and_the_figures(): void
    {
        $user = $this->staff();
        $this->completed($user, 'Business Permit', '-1 days');

        $html = $this->actingAs($user)
            ->get(route('staff.profile', $user->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Completion Activity', $html);
        $this->assertStringContainsString('gs-heat-cell', $html);
        $this->assertStringContainsString('Most handled', $html);
        $this->assertStringContainsString('Longest streak', $html);
        $this->assertStringContainsString('Business Permit', $html);
    }
}
