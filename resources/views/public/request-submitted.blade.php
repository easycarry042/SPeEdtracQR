<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Submitted — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    <header class="border-b border-emerald-200/60 bg-[#f1f2f1]/90">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-9 w-9 rounded-xl">
                <span class="text-lg font-extrabold tracking-tight text-emerald-950">SPeED <span class="text-emerald-700">TraQR</span></span>
            </a>
            <a href="{{ route('track.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">Track a request</a>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-10">
        @php
            $trackUrl = url('/track/'.$document->tracking_number);
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(1)->errorCorrection('M')->generate($trackUrl);
            $qrPng = $document->qr_code_path ? \App\Support\PublicStorage::url($document->qr_code_path) : null;
        @endphp

        <div class="rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-emerald-950">Request submitted</h1>
            <p class="mt-2 text-sm text-gray-600">Save your QR code and tracking number below. You'll use these to follow your request.</p>

            <div class="mx-auto mt-6 flex h-60 w-60 items-center justify-center rounded-xl border border-gray-200 bg-white p-3 shadow-inner [&_svg]:h-full [&_svg]:w-full [&_svg]:max-h-full [&_svg]:max-w-full">
                {!! $qrSvg !!}
            </div>

            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Tracking number</p>
            <p class="mt-1 inline-block rounded-lg bg-emerald-50 px-4 py-2 font-mono text-2xl font-extrabold tracking-wide text-emerald-950">{{ $document->tracking_number }}</p>
            <p class="mt-3 text-sm text-gray-700">{{ $document->document_type }}</p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                @if($qrPng)
                    <a href="{{ $qrPng }}" download="{{ $document->tracking_number }}.png"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                        Download QR Code
                    </a>
                @endif
                <a href="{{ route('track.slip', $document->tracking_number) }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                    Print slip
                </a>
                <a href="{{ route('track.show', $document->tracking_number) }}"
                   class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Track this document
                </a>
            </div>
        </div>

        <p class="mt-6 text-center text-sm text-gray-500">
            <a href="{{ route('public.request.create') }}" class="font-semibold text-emerald-800 hover:underline">Submit another request</a>
            <span class="mx-2 text-gray-300">·</span>
            <a href="{{ url('/') }}" class="font-semibold text-emerald-800 hover:underline">Back to home</a>
        </p>
    </main>
</body>
</html>
