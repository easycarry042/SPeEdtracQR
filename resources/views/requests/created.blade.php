<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-green-deep">Internal Request Filed</h1>
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
            <section class="panel">
                <div class="pb p-8 text-center">
                    <p class="text-[13px] text-ink-soft">Paste the QR sticker on the paper request — every office it reaches scans it to confirm custody.</p>

                    <div class="mx-auto mt-6 flex h-56 w-56 items-center justify-center rounded-[10px] border border-hairline bg-white p-3 [&_svg]:h-full [&_svg]:w-full [&_svg]:max-h-full [&_svg]:max-w-full"
                         role="img" aria-label="QR code for tracking number {{ $document->tracking_number }}">
                        {!! $qrSvg !!}
                    </div>

                    <p class="mt-6"><span class="id-chip text-lg sm:text-xl">{{ $document->tracking_number }}</span></p>
                    <p class="mt-3 text-[15px] text-ink">{{ $document->purpose }}</p>
                    <p class="mt-1 text-[13px] text-ink-soft">
                        {{ $document->document_type }} · {{ $document->requestingDepartment?->name }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="cr-btn cr-btn-primary">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                            Print QR sticker
                        </a>
                        @if($stampedCopy)
                            <a href="{{ $stampedCopy->authorizedUrl() }}" target="_blank" class="cr-btn">QR-stamped digital copy</a>
                        @endif
                        @if($originalScan)
                            <a href="{{ $originalScan->authorizedUrl() }}" target="_blank" class="cr-btn">Original scan</a>
                        @endif
                        <a href="{{ route('requests.index') }}" class="cr-btn">Internal Requests</a>
                    </div>
                </div>
            </section>

            {{-- Endorsement chain --}}
            <section class="panel">
                <div class="ph"><h2>Endorsement chain</h2><span class="sub">The highlighted office holds it now</span></div>
                <div class="pb">
                    @include('requests.partials.chain')

                    <a href="{{ route('requests.show', $document) }}" class="mt-2 inline-flex items-center gap-1 text-[13px] font-semibold text-green hover:underline">
                        Open the full request view →
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
