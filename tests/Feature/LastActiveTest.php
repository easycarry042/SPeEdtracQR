<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * "Active now" must mean the person is here.
 *
 * It used to be read from the activity log, i.e. the last time they caused a
 * logged change — so somebody reading the site all morning showed as idle
 * since their login.
 */
class LastActiveTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('staff');

        return $user;
    }

    public function test_opening_a_page_marks_the_user_as_present(): void
    {
        $user = $this->staff();
        $this->assertNull($user->last_active_at);

        $this->actingAs($user)->get(route('staff.profile', ['user' => $user->id]))->assertOk();

        $this->assertNotNull($user->fresh()->last_active_at, 'a page view should count as presence');
        $this->assertTrue($user->fresh()->last_active_at->gt(now()->subMinute()));
    }

    public function test_their_own_profile_reads_active_now(): void
    {
        $user = $this->staff();

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $user->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Active now', $html);
        $this->assertStringNotContainsString('Last active', $html);
    }

    /**
     * A forgotten tab polls forever. If that counted, everyone who left a
     * browser open would read as present until the machine slept.
     */
    public function test_a_background_poll_does_not_count_as_presence(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('speedy.inbox'));

        $this->assertNull($user->fresh()->last_active_at, 'a JSON poll is the tab talking, not the person');
    }

    /** Anything they actually did counts, whatever it accepts back. */
    public function test_a_state_changing_request_counts_as_presence(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('staff.highlights.store'), ['body' => 'Did a thing', 'highlight_type' => 'note']);

        $this->assertNotNull($user->fresh()->last_active_at);
    }

    /** One write a minute, not one per request. */
    public function test_the_stamp_is_not_rewritten_on_every_request(): void
    {
        $user = $this->staff();
        $user->forceFill(['last_active_at' => now()->subSeconds(5)])->saveQuietly();
        $first = $user->fresh()->last_active_at;

        $this->actingAs($user)->get(route('staff.profile', ['user' => $user->id]))->assertOk();

        $this->assertTrue(
            $first->equalTo($user->fresh()->last_active_at),
            'a stamp under a minute old should be left alone'
        );
    }

    /** Stamping presence must not bury the audit log under "User updated". */
    public function test_presence_is_not_written_to_the_audit_log(): void
    {
        $user = $this->staff();
        $before = Activity::count();

        $this->actingAs($user)->get(route('staff.profile', ['user' => $user->id]))->assertOk();

        $this->assertSame($before, Activity::count(), 'presence should be a quiet write');
    }

    /** The half the middleware exists for: a colleague seeing a live teammate. */
    public function test_a_peer_sees_someone_who_is_currently_browsing_as_active(): void
    {
        $viewer = $this->staff();
        $peer = User::factory()->create(['is_active' => true]);
        $peer->assignRole('staff');

        // The teammate opens a page — nothing is created, changed or logged.
        $this->actingAs($peer)->get(route('staff.profile', ['user' => $peer->id]))->assertOk();

        $html = $this->actingAs($viewer)
            ->get(route('staff.profile', ['user' => $peer->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Active now', $html, 'merely reading the site is presence');
    }

    /**
     * The directory and the Users page read presence the same way the profile
     * does — one person must not be "Active now" on one screen and "Active 1h
     * ago" on another at the same moment.
     */
    public function test_the_directory_agrees_with_the_profile(): void
    {
        $viewer = $this->staff();
        $viewer->givePermissionTo('view reports');
        $peer = User::factory()->create(['is_active' => true]);
        $peer->assignRole('staff');

        $this->actingAs($peer)->get(route('staff.profile', ['user' => $peer->id]))->assertOk();

        $html = $this->actingAs($viewer)->get(route('staff.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Active now', $html);
    }

    /** Accounts not seen since the column existed still report their last trace. */
    public function test_it_falls_back_to_the_activity_log_when_never_stamped(): void
    {
        $user = $this->staff();
        $peer = User::factory()->create(['is_active' => true, 'last_active_at' => null]);
        $peer->assignRole('staff');

        activity()->causedBy($peer)->withProperties([])->log('did something');
        Activity::latest('id')->first()->update(['created_at' => now()->subHours(3)]);

        $html = $this->actingAs($user)
            ->get(route('staff.profile', ['user' => $peer->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Last active', $html);
    }
}
