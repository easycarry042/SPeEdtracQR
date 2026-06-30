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
     * @deprecated Routing-era scan undo. There are no IN/OUT scans to undo under
     * the manual status model — use the document's status controls (Move back)
     * via DocumentStatusController instead.
     */
    public function undoLast(Document $document)
    {
        return response()->json([
            'message' => 'Scan undo has been retired. Use the document\'s status controls (Move back) instead.',
        ], 410);
    }
}
