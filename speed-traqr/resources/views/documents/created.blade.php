@php
    $trackUrl = url('/track/'.$document->tracking_number);
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate($trackUrl);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Document Created</h1>
    </x-slot>

    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-8 text-center shadow-md">
            <p class="text-lg text-gray-600">Scan this code to open tracking, or print the sticker for the physical file.</p>

            <div class="mx-auto mt-6 flex h-56 w-56 items-center justify-center rounded-xl border border-gray-200 bg-white p-3 shadow-inner [&_svg]:h-full [&_svg]:w-full [&_svg]:max-h-full [&_svg]:max-w-full">
                {!! $qrSvg !!}
            </div>

            @if($document->qr_code_path)
                <p class="mt-2 text-xs text-gray-400">PNG copy: <a class="text-emerald-700 underline" href="{{ Storage::url($document->qr_code_path) }}" target="_blank" rel="noopener">open file</a></p>
            @endif

            <p class="mt-6 font-mono text-2xl font-extrabold text-emerald-950 sm:text-3xl">{{ $document->tracking_number }}</p>
            <p class="mt-2 text-lg text-gray-900">{{ $document->document_type }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ $document->citizen_name ?? 'N/A' }}</p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                    Print QR sticker
                </a>
                <a href="{{ route('track.show', $document->tracking_number) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    View tracking
                </a>
                <a href="{{ route('scan.index') }}" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-100">
                    Record handoff (Scan)
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
