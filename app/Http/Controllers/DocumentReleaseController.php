<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Support\Lending;
use Illuminate\Http\Request;

/**
 * QR-gated release: a Completed document is claimed at the counter by the
 * citizen presenting their QR stub. Staff scan it, verify the person matches
 * the record, and mark the release — the final physical hand-over.
 */
class DocumentReleaseController extends Controller
{
    public function store(Request $request, Document $document)
    {
        $user = $request->user();

        // Counter staff (scan documents), the assignee's chain, and admins may release.
        abort_unless(
            $user->can('scan documents') || $user->can('assign documents') || $user->can('manage system'),
            403,
        );

        if ($document->statusEnum() !== DocumentStatus::Completed) {
            return $this->fail($request, 'Only a Completed document can be released to the citizen.');
        }

        if ($document->claimed_at !== null) {
            return $this->fail($request, 'This document was already released on '.$document->claimed_at->format('M d, Y h:i A').'.');
        }

        // A borrowing claimed at the counter is its hand-over: the items leave
        // the stockroom (stock-checked) in the same step.
        $booking = $document->booking;

        if ($booking && $booking->isAwaitingPickup() && $refusal = Lending::release($booking, $user)) {
            return $this->fail($request, $refusal);
        }

        $document->forceFill([
            'claimed_at' => now(),
            'released_by' => $user->id,
        ])->save();

        activity()
            ->performedOn($document)
            ->causedBy($user)
            ->log('Released to citizen'.($document->citizen_name ? " ({$document->citizen_name})" : ''));

        $document->logSystemComment("Released to citizen by {$user->name}");

        $message = 'Marked as released — the citizen has claimed this document.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'claimed_at' => $document->claimed_at->toISOString()]);
        }

        return back()->with('status', $message);
    }

    private function fail(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['release' => $message]);
    }
}
