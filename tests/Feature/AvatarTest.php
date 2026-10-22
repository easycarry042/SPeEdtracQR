<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile pictures.
 *
 * The privacy boundary is the point: a colleague may see a colleague's face,
 * the public never may. Staff are anonymous on every citizen surface, so the
 * file lives on the private disk and only ever comes back through an
 * authenticated route.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('staff');

        return $user;
    }

    public function test_a_user_can_upload_a_picture(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 400)])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);
    }

    /** The whole reason it is not on the public disk. */
    public function test_a_guest_cannot_fetch_a_staff_picture(): void
    {
        Storage::fake('local');
        $user = $this->staff();
        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        auth()->logout();

        $this->get(route('staff.avatar', $user->id))->assertRedirect(route('login'));
    }

    public function test_a_colleague_can_see_a_colleague(): void
    {
        Storage::fake('local');
        $owner = $this->staff();
        $this->actingAs($owner)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $peer = User::factory()->create(['is_active' => true]);
        $peer->assignRole('staff');

        $this->actingAs($peer)->get(route('staff.avatar', $owner->id))->assertOk();
    }

    public function test_the_picture_replaces_the_initials_on_the_profile(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $before = $this->actingAs($user)->get(route('staff.profile', $user->id))->getContent();
        $this->assertStringNotContainsString(route('staff.avatar', $user->id), $before);

        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

        $after = $this->actingAs($user)->get(route('staff.profile', $user->id))->getContent();
        $this->assertStringContainsString(route('staff.avatar', $user->id), $after);
    }

    public function test_a_user_can_remove_their_picture(): void
    {
        Storage::fake('local');
        $user = $this->staff();
        $this->actingAs($user)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);
        $path = $user->fresh()->avatar_path;

        $this->actingAs($user)->delete(route('profile.avatar.destroy'))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($path);
    }

    /** Replacing leaves no orphan behind on disk. */
    public function test_replacing_deletes_the_previous_file(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('one.jpg')]);
        $first = $user->fresh()->avatar_path;

        $this->travel(2)->seconds();

        $this->actingAs($user)->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('two.png')]);
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    public function test_a_non_image_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    /** `mimes:` trusts the extension; the bytes must be checked too. */
    public function test_a_script_wearing_a_jpg_extension_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('profile.avatar.store'), [
                'avatar' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "pwned";'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_an_oversized_picture_is_rejected(): void
    {
        Storage::fake('local');
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('profile.avatar.store'), ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');
    }

    /** Nobody edits anybody else's picture: the route only ever acts on self. */
    public function test_the_upload_route_only_touches_the_signed_in_user(): void
    {
        Storage::fake('local');
        $owner = $this->staff();
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('staff');

        $this->actingAs($owner)->post(route('profile.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('me.jpg'),
            'user_id' => $other->id,   // ignored by design
        ]);

        $this->assertNotNull($owner->fresh()->avatar_path);
        $this->assertNull($other->fresh()->avatar_path);
    }
}
