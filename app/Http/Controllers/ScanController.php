<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * QR lookup tool. Scanning identifies a document and opens it — it no longer
     * records IN/OUT or advances status (advancement is manual; see
     * DocumentStatusController). The page redirects to the public track page.
     */
    public function index()
    {
        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        return view('scan.index');
    }

    /**
     * @deprecated Routing-era scan-to-advance. Status is now changed manually via
     * DocumentStatusController — scanning no longer mutates documents. Retained as
     * a hard stop so any stale client cannot silently change state.
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Scanning no longer changes a document\'s status. Open the document and use its status controls instead.',
        ], 410);
    }

    /**
     * @deprecated Offline scan sync is obsolete under the manual model.
     */
    public function sync(Request $request)
    {
        return response()->json([
            'message' => 'Offline scan sync has been retired. Scanning is now a lookup tool only.',
        ], 410);
    }

    /**
     * Undo the most recent scan (a mis-scan or premature handoff) and revert the
     * document to its prior location/status. An OUT is reversed by bringing the
     * document back to where it last checked in; an IN is simply removed (the IN
     * did not move it). The correction is written to the activity log.
     */
    public function undoLast(Document $document)
    {
        $this->ensureCanScan();

        $last = $document->scans()->first();
        if (! $last) {
            return back()->withErrors(['undo' => 'There is no scan to undo for this document.']);
        }

        $undoneAction = $last->action;
        $undoneDeptName = $last->department->name ?? null;
        $last->delete();

        $remaining = $document->scans()->get();
        $latestIn = $remaining->firstWhere('action', 'in');

        if ($remaining->isEmpty()) {
            $document->current_department_id = null;
            $document->status = 'pending';
        } elseif ($undoneAction === 'out') {
            // Document had been forwarded; return it to its last check-in point.
            $document->current_department_id = $latestIn?->department_id;
            $document->status = $latestIn ? 'in_transit' : 'pending';
        } else {
            // Removed an arrival scan; it stays where it physically was.
            $document->status = 'in_transit';
        }
        $document->completed_at = null;
        $document->save();

        activity()
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->log('Undid '.$undoneAction.' scan'.($undoneDeptName ? ' at '.$undoneDeptName : ''));

        return back()->with('status', 'The last scan was undone.');
    }

    private function ensureCanScan(): void
    {
        $user = auth()->user();

        // System administrators manage the org but do not operate scanners.
        if ($user?->can('manage system') || ! $user?->can('scan documents')) {
            abort(403, 'You do not have permission to scan documents.');
        }
    }
}
