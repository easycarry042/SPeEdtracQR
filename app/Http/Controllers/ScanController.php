<?php

namespace App\Http\Controllers;

class ScanController extends Controller
{
    /**
     * QR lookup tool. Scanning identifies a document and opens it — it does not
     * record IN/OUT or advance status (advancement is manual; see
     * DocumentStatusController). The page redirects to the public track page.
     */
    public function index()
    {
        if (auth()->user()?->can('manage system')) {
            return redirect()->route('admin.dashboard');
        }

        return view('scan.index');
    }
}
