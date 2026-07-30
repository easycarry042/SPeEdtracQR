<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('staff');

        return $user;
    }

    public function test_profile_renders_for_all_tabs(): void
    {
        $user = $this->staff();
        Document::create([
            'tracking_number' => 'SPD-T-1', 'document_type' => 'A', 'status' => 'completed',
            'assigned_to' => $user->id, 'created_by' => $user->id,
            'created_at' => now()->subDays(2), 'completed_at' => now()->subDay(),
        ]);

        foreach (['activity', 'assigned', 'completions'] as $tab) {
            $this->actingAs($user)
                ->get(route('staff.profile', ['user' => $user->id, 'tab' => $tab]))
                ->assertOk();
        }
    }

    public function test_my_profile_carries_the_assigned_requests_cockpit(): void
    {
        $user = $this->staff();
        $assigned = Document::create([
            'tracking_number' => 'SPD-T-3', 'document_type' => 'Business Permit',
            'status' => 'in_progress', 'assigned_to' => $user->id, 'assigned_at' => now(),
            'accepted_at' => now(), 'citizen_name' => 'Maria Santos',
        ]);

        // Own profile defaults to the work tab and renders the review cockpit.
        $this->actingAs($user)
            ->get(route('staff.profile', $user->id))
            ->assertOk()
            ->assertSee($assigned->tracking_number, false)
            ->assertSee('reviewPanel(', false)
            ->assertSee('View history');
    }

    public function test_a_peer_profile_shows_no_review_cockpit(): void
    {
        $viewer = $this->staff();
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('staff');
        Document::create([
            'tracking_number' => 'SPD-T-4', 'document_type' => 'Cedula',
            'status' => 'in_progress', 'assigned_to' => $other->id, 'assigned_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('staff.profile', ['user' => $other->id, 'tab' => 'assigned']))
            ->assertOk()
            ->assertSee('SPD-T-4', false)
            ->assertDontSee('reviewPanel(', false);
    }

    public function test_peer_view_has_no_composer_but_renders(): void
    {
        $viewer = $this->staff();
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('staff');

        $this->actingAs($viewer)
            ->get(route('staff.profile', ['user' => $other->id]))
            ->assertOk()
            ->assertDontSee('Share an accomplishment');
    }

    public function test_owner_can_post_highlight(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('staff.highlights.store'), [
                'body' => 'Closed a tricky permit today',
                'highlight_type' => 'milestone',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_highlights', [
            'user_id' => $user->id, 'highlight_type' => 'milestone',
        ]);
    }

    public function test_cannot_attach_foreign_document(): void
    {
        $user = $this->staff();
        $foreign = Document::create([
            'tracking_number' => 'SPD-T-2', 'document_type' => 'B', 'status' => 'pending',
            'created_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($user)
            ->post(route('staff.highlights.store'), [
                'body' => 'not mine', 'highlight_type' => 'note', 'document_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('document_id');
    }
}
