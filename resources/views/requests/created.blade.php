<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Internal Request Filed</h1>
    </x-slot>

    @php
        $trackUrl = url('/track/'.$document->tracking_number);
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate($trackUrl);
        $stampedCopy = $document->attachments->first(fn ($a) => str_ends_with($a->file_path, '-qr-stamped.png'));
        $originalScan = $document->attachments->first(fn ($a) => ! str_ends_with($a->file_path, '-qr-stamped.png'));
    @endphp

    <div class="page-shell">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- QR + actions --}}
            <div class="rounded-2xl border border-gray-200/90 bg-white p-8 text-center shadow-md">
                <p class="text-sm text-gray-600">Paste the QR sticker on the paper request — every office it reaches scans it to confirm custody.</p>

                <div class="mx-auto mt-6 flex h-56 w-56 items-center justify-center rounded-xl border border-gray-200 bg-white p-3 shadow-inner [&_svg]:h-full [&_svg]:w-full [&_svg]:max-h-full [&_svg]:max-w-full">
                    {!! $qrSvg !!}
                </div>

                <p class="mt-6 font-mono text-2xl font-extrabold text-emerald-950 sm:text-3xl">{{ $document->tracking_number }}</p>
                <p class="mt-2 text-lg text-gray-900">{{ $document->purpose }}</p>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $document->document_type }} · {{ $document->requestingDepartment?->name }}
                    @if($document->amount !== null)
                        · ₱{{ number_format((float) $document->amount, 2) }}
                    @endif
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('documents.sticker', $document) }}" target="_blank"
                       class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                        Print QR sticker
                    </a>
                    @if($stampedCopy)
                        <a href="{{ $stampedCopy->authorizedUrl() }}" target="_blank"
                           class="inline-flex items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-2.5 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-100">
                            QR-stamped digital copy
                        </a>
                    @endif
                    @if($originalScan)
                        <a href="{{ $originalScan->authorizedUrl() }}" target="_blank"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Original scan
                        </a>
                    @endif
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        Back to dashboard
                    </a>
                </div>
            </div>

            {{-- Endorsement chain --}}
            <div class="rounded-2xl border border-gray-200/90 bg-white p-8 shadow-md">
                <h2 class="text-sm font-bold uppercase tracking-wide text-emerald-900">Endorsement chain</h2>
                <p class="mt-0.5 text-xs text-gray-500">The route this request follows. The highlighted office holds it now.</p>

                <div class="mt-6">
                    @include('requests.partials.chain')
                </div>

                <a href="{{ route('requests.show', $document) }}"
                   class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 hover:underline">
                    Open the full request view →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
