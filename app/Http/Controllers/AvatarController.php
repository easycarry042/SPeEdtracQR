<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profile pictures.
 *
 * Uploads land on the **private** disk and come back only through show(),
 * behind auth. Staff are anonymous to the public on every citizen surface, so
 * a photo must never sit on a guessable public URL. Any signed-in colleague
 * may see a colleague's picture — the staff directory is built on that — but
 * nobody outside the system can.
 *
 * Only the owner can set or remove their own picture.
 */
class AvatarController extends Controller
{
    /** Browser-renderable stills only: HEIC is an image no browser will draw. */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** A face, not a document scan. */
    private const MAX_KILOBYTES = 2048;

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', self::EXTENSIONS),
                'max:'.self::MAX_KILOBYTES,
            ],
        ], [
            'avatar.max' => 'The picture must be 2 MB or smaller.',
            'avatar.mimes' => 'The picture must be a JPG, PNG, or WebP image.',
        ]);

        $file = $request->file('avatar');

        // `mimes:` trusts the extension; this reads the bytes. A .jpg that is
        // really a script would otherwise be stored and streamed back.
        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['avatar' => 'That file could not be read as an image.']);
        }

        $user = $request->user();
        $previous = $user->avatar_path;

        $path = $file->storeAs(
            'avatars',
            "user-{$user->id}-".now()->timestamp.'.'.$file->extension(),
            'local'
        );

        $user->forceFill(['avatar_path' => $path])->save();

        // The filename carries a timestamp so a replacement busts any cached
        // copy; the old file is now unreferenced, so remove it.
        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return back()->with('status', 'avatar-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return back()->with('status', 'avatar-removed');
    }

    /** Stream a colleague's picture to a signed-in user. */
    public function show(Request $request, User $user): StreamedResponse
    {
        abort_unless(
            $user->avatar_path && Storage::disk('local')->exists($user->avatar_path),
            404
        );

        return Storage::disk('local')->response(
            $user->avatar_path,
            null,
            [
                'Content-Type' => Storage::disk('local')->mimeType($user->avatar_path),
                // Private: a shared counter PC must not serve one clerk's face
                // from cache to the next person who signs in.
                'Cache-Control' => 'private, max-age=300',
            ]
        );
    }
}
