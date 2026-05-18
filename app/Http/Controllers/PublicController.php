<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function index()
    {
        return view('public.home');
    }

    public function track($trackingNumber)
    {
        $document = Document::where('tracking_number', $trackingNumber)
            ->with(['scans' => function ($q) {
                $q->orderBy('scanned_at', 'asc');
            }, 'scans.department'])
            ->firstOrFail();

        return view('public.track', compact('document'));
    }
}