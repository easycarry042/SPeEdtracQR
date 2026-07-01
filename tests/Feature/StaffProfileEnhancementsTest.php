<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Support\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function doc(User $user, array $attrs = []): Document
    {
        return Document::create(array_merge([
            'tracking_number' => 'SPD-SP-'.uniqid(),
            'document_type' => 'Business Permit',
            'status' => DocumentStatus::InProgress->value,
            'source' => 'walk_in',
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'status_changed_at' => now(),
        ], $attrs));
    }

    public function test_feed_collapses_system_events_into_one_group_per_document(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');
        $doc = $this->doc($user);

        // Two system events for the SAME document.
        activity()->performedOn($doc)->causedBy($user)->withProperties(['to' => 'in_review'])->log('Advanced: In Progress → In Review');
        activity()->performedOn($doc)->causedBy($user)->withProperties(['to' => 'approved'])->log('Advanced: In Review → Approved');

        // A real staff post.
        $user->highlights()->create(['highlight_type' => 'note', 'body' => 'Wrapped up the site inspection']);

        $feed = (new StaffProfile($user))->feed();
        $kinds = $feed->pluck('kind');

        $this->assertSame(1, $kinds->filter(fn ($k) => $k === 'system_group')->count(), 'system events must collapse into one chunk');
        $this->assertTrue($kinds->contains('manual'), 'staff posts stay ungrouped');

        $group = $feed->firstWhere('kind', 'system_group');
        $this->assertSame(2, $group['count']);
    }

    public function test_completions_are_prominent_and_excluded_from_the_system_group(): void
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create()->assignRole('staff');
        $doc = $this->doc($user, ['status' => DocumentStatus::Completed->value, 'completed_at' => now()]);

        // The completion transition should NOT become a system chunk.
        activity()->performedOn($doc)->causedBy($user)->withProperties(['to' => 'completed'])->log('Advanced: Approved → Completed');

        $feed = (new StaffProfile($user))->feed();
        $kinds = $feed->pluck('kind');

        $this->assertTrue($kinds->contains('completion'));
        $this->assertFalse($kinds->contains('system_group'), 'the only event was the completion — no system chunk');
    }

    public function test_staff_directory_lists_people_with_counts(): void
    {
        $this->seedRolesAndPermissions();
        $viewer = User::factory()->create()->assignRole('staff');
        $target = User::factory()->create(['name' => 'Ana Cruz'])->assignRole('staff');

        $this->doc($target); // 1 active → assigned count 1
        $this->doc($target, ['status' => DocumentStatus::Completed->value, 'completed_at' => now()]); // completed count 1

        $this->actingAs($viewer)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Ana Cruz');
    }

    public function test_staff_search_returns_matching_json(): void
    {
        $this->seedRolesAndPermissions();
        $viewer = User::factory()->create()->assignRole('staff');
        User::factory()->create(['name' => 'Benigno Reyes'])->assignRole('staff');

        $this->actingAs($viewer)
            ->getJson(route('staff.search', ['q' => 'Benigno']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Benigno Reyes']);
    }

    public function test_directory_requires_auth(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));
    }
}
