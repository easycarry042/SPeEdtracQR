<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Claim Slip — {{ $document->tracking_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Print: drop the screen chrome and let the slip fill the sheet. */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .slip { box-shadow: none !important; border-color: #d1d5db !important; margin: 0 !important; }
        }
        @page { margin: 16mm; }
    </style>
</head>
<body class="min-h-screen bg-[#f1f2f1] py-10 antialiased text-gray-900">
    @php
        $trackUrl = url('/track/'.$document->tracking_number);
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate($trackUrl);
        $isInternal = $document->isInternal();
    @endphp

    {{-- Screen-only action bar --}}
    <div class="no-print mx-auto mb-6 flex max-w-xl items-center justify-between px-6">
        <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 active:scale-95">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
            Print / Save as PDF
        </button>
    </div>

    {{-- The slip --}}
    <div class="slip mx-auto max-w-xl overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm">
        {{-- Header band --}}
        <div class="flex items-center gap-3 border-b border-gray-200 px-8 py-5">
            <img src="{{ asset('images/icon.png') }}" alt="" class="h-10 w-10 rounded-lg">
            <div class="min-w-0">
                <p class="text-sm font-extrabold tracking-tight text-emerald-950">SPeED <span class="text-emerald-700">TraQR</span></p>
                <p class="text-xs text-gray-500">Municipal Document Tracking · Official Claim Slip</p>
            </div>
            <span class="ml-auto rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide {{ $isInternal ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                {{ $isInternal ? 'Internal' : 'Citizen' }}
            </span>
        </div>

        <div class="px-8 py-8 text-center">
            {{-- QR --}}
            <div class="mx-auto flex h-56 w-56 items-center justify-center rounded-xl border border-gray-200 bg-white p-3 [&_svg]:h-full [&_svg]:w-full">
                {!! $qrSvg !!}
            </div>

            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-500">Tracking Number</p>
            <p class="mt-1 inline-block rounded-lg bg-emerald-50 px-4 py-2 font-mono text-2xl font-extrabold tracking-wide text-emerald-950">{{ $document->tracking_number }}</p>

            {{-- Details --}}
            <dl class="mx-auto mt-6 grid max-w-sm grid-cols-2 gap-x-6 gap-y-3 text-left text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Request Type</dt>
                    <dd class="mt-0.5 font-medium text-gray-800">{{ $document->document_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Date Issued</dt>
                    <dd class="mt-0.5 font-medium text-gray-800">{{ $document->created_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                @if($document->citizen_name)
                    <div class="col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Requester</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $document->citizen_name }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Instructions footer --}}
        <div class="border-t border-gray-200 bg-[#f8faf8] px-8 py-5 text-center">
            <p class="text-xs text-gray-600">
                Present this slip at the counter. Scan the QR code or enter the tracking number at
                <span class="font-semibold text-emerald-800">{{ url('/track') }}</span> to follow this request.
            </p>
        </div>
    </div>
</body>
</html>
