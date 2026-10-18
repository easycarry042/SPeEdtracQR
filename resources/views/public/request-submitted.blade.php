{{-- The receipt: shown once a request is filed (and reopenable for a short
     while, see App\Support\CitizenReceipt). It wears the citizen layout like
     every other public page — it used to carry its own grey page and header,
     so the step right after the form looked like a different site. --}}
<x-citizen-layout :speedy-document="$document">
    <x-slot name="title">Request Submitted</x-slot>

    @php
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(240)->margin(1)->errorCorrection('M')->generate(\App\Support\TrackingQr::payload($document->tracking_number));
        $qrPng = $document->qr_code_path ? \App\Support\PublicStorage::url($document->qr_code_path) : null;
    @endphp

    <div class="mx-auto max-w-[680px]">
        <div class="cz-card px-6 py-8 text-center sm:px-10 sm:py-10">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#eef8ea] text-[#01721a]">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h1 class="cz-title mt-5 text-[32px] sm:text-[38px]">Request submitted</h1>
            <p class="cz-lead mx-auto mt-3 max-w-[460px] text-[16px] sm:text-[17px]">
                Save your QR code and tracking number below. To follow your request, scan the code on
                this site’s Track a request page — your phone’s camera app won’t open it.
            </p>

            <div class="mx-auto mt-7 flex h-60 w-60 items-center justify-center rounded-[16px] border border-[rgba(0,64,4,0.12)] bg-white p-3 [&_svg]:h-full [&_svg]:max-h-full [&_svg]:w-full [&_svg]:max-w-full">
                {!! $qrSvg !!}
            </div>

            <p class="cz-key mt-7">Tracking number</p>
            <p class="mt-2"><span class="cz-id px-4 py-2 text-[22px] sm:text-[26px]">{{ $document->tracking_number }}</span></p>
            <p class="mt-3 text-[16px] font-medium text-[#4b5a50]">{{ $document->document_type }}</p>

            <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:flex-wrap">
                @if($qrPng)
                    <a href="{{ $qrPng }}" download="{{ $document->tracking_number }}.png" class="cz-btn cz-btn-primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                        Download QR Code
                    </a>
                @endif
                <a href="{{ route('track.slip', ['trackingNumber' => $document->tracking_number, 'from' => 'receipt']) }}" class="cz-btn cz-btn-secondary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                    Print slip
                </a>
                {{-- Without a QR file to download, following the request is the
                     next step, so it takes the primary slot. --}}
                <a href="{{ $document->citizenTrackingUrl() }}" class="cz-btn {{ $qrPng ? 'cz-btn-secondary' : 'cz-btn-primary' }}">
                    Track this request
                </a>
            </div>
        </div>

        <p class="mt-6 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-[15px] text-[#4b5a50]">
            <a href="{{ route('public.request.create') }}" class="font-bold text-[#01721a] underline-offset-2 hover:underline">Submit another request</a>
            <span aria-hidden="true">·</span>
            <a href="{{ route('citizen.track') }}" class="font-bold text-[#01721a] underline-offset-2 hover:underline">Track a different request</a>
            <span aria-hidden="true">·</span>
            <a href="{{ \App\Support\CitizenView::homeUrl() }}" class="font-bold text-[#01721a] underline-offset-2 hover:underline">Back to home</a>
        </p>
    </div>
</x-citizen-layout>
