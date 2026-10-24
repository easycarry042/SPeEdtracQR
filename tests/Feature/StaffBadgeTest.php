<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StaffBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endorsement decisions are signed by scanning the signer's own badge QR instead
 * of retyping a password. The badge is a secret, so it must be unguessable,
 * private to its owner, and reissuable when a card is lost.
 */
class StaffBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create(['is_active' => true])->assignRole('Supervisor');
    }

    public function test_the_badge_code_is_minted_on_first_use_and_then_stable(): void
    {
        $user = $this->supervisor();

        $this->assertNull($user->identity_code);

        $first = $user->badgeCode();
        $this->assertNotEmpty($first);
        $this->assertSame($first, $user->fresh()->badgeCode());
        $this->assertGreaterThanOrEqual(16, strlen($first));
    }

    public function test_the_badge_payload_is_not_a_url(): void
    {
        // A photographed badge must be useless in a browser.
        $payload = $this->supervisor()->badgePayload();

        $this->assertStringStartsWith('SPDSTAFF:', $payload);
        $this->assertStringNotContainsString('http', $payload);
    }

    public function test_a_badge_matches_only_its_owner(): void
    {
        $owner = $this->supervisor();
        $other = $this->supervisor();

        $payload = $owner->badgePayload();
        $other->badgeCode(); // ensure the other user has one too

        $this->assertTrue(StaffBadge::belongsTo($payload, $owner->fresh()));
        $this->assertFalse(StaffBadge::belongsTo($payload, $other->fresh()));
    }

    public function test_foreign_payloads_never_match(): void
    {
        $user = $this->supervisor();
        $user->badgeCode();
        $user = $user->fresh();

        foreach ([
            'SPD-20260728-K7M9Q2',            // a folder code, not a badge
            'https://example.com/',
            'SPDSTAFF:',
            'SPDSTAFF:short',
            '',
        ] as $payload) {
            $this->assertFalse(
                StaffBadge::belongsTo($payload, $user),
                "Expected [{$payload}] to be rejected as a badge."
            );
        }
    }

    public function test_a_user_without_a_badge_cannot_be_matched(): void
    {
        // An empty identity_code must never make an empty scan "valid".
        $user = $this->supervisor();

        $this->assertFalse(StaffBadge::belongsTo('SPDSTAFF:'.str_repeat('a', 32), $user));
    }

    public function test_the_badge_page_shows_only_your_own_badge(): void
    {
        $user = $this->supervisor();

        $this->actingAs($user)
            ->get(route('profile.badge'))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee('Print badge')
            // The secret itself is never printed as text next to the code.
            ->assertDontSee($user->fresh()->identity_code);
    }

    public function test_reissuing_a_badge_invalidates_the_old_one(): void
    {
        $user = $this->supervisor();
        $old = $user->badgePayload();

        $this->actingAs($user)
            ->post(route('profile.badge.regenerate'))
            ->assertRedirect(route('profile.badge'));

        $user = $user->fresh();
        $this->assertFalse(StaffBadge::belongsTo($old, $user));
        $this->assertTrue(StaffBadge::belongsTo($user->badgePayload(), $user));
    }

    public function test_guests_cannot_reach_a_badge(): void
    {
        $this->get(route('profile.badge'))->assertRedirect(route('login'));
    }
}
