<?php

namespace App\Http\Controllers;

class ScanController extends Controller
{
    /**
     * Legacy /scan entry point. The scanner is folded into the Look up hub
     * (track.index?find=1): scanning identifies a document and opens it — it
     * does not record IN/OUT or advance status (advancement is manual; see
     * DocumentStatusController).
     */
    public function index()
    {
        if (auth()->user()?->can('manage system')) {
            return to_route('admin.dashboard');
        }

        return to_route('track.index', ['find' => 1]);
    }
}
