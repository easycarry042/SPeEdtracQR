<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Registered e-signature management (profile page). The signature is drawn on
 * a canvas client-side and posted as a PNG data URL; it lives on the private
 * disk and is only ever served back to its owner here. Approvals copy it into
 * the acted request step (see RequestStepController) so the historical record
 * survives later re-registration.
 */
class SignatureController extends Controller
{
    /** Cap decoded signature size: a canvas sketch should be a few KB, never MB. */
    private const MAX_BYTES = 512 * 1024;

    public function store(Request $request)
    {
        $request->validate([
            'signature' => 'required|string|max:800000',
        ]);

        $dataUrl = $request->string('signature')->toString();

        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['signature' => 'The signature must be a PNG drawing.']);
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['signature' => 'The signature drawing could not be read.']);
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false || $info['mime'] !== 'image/png') {
            throw ValidationException::withMessages(['signature' => 'The signature drawing could not be read.']);
        }

        $user = $request->user();
        $path = "signatures/user-{$user->id}.png";
        Storage::disk('local')->put($path, $binary);
        $user->update(['signature_path' => $path]);

        return back()->with('status', 'signature-saved');
    }

    /** Serve the owner's own registered signature for the profile preview. */
    public function show(Request $request)
    {
        $user = $request->user();

        abort_unless($user->signature_path && Storage::disk('local')->exists($user->signature_path), 404);

        return response(Storage::disk('local')->get($user->signature_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
            $user->update(['signature_path' => null]);
        }

        return back()->with('status', 'signature-removed');
    }
}
