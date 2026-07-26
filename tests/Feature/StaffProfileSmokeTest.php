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
