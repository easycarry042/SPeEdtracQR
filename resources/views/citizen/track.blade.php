<x-citizen-layout>
    <x-slot name="title">Track a Document</x-slot>

    {{-- Page header (Back lives in the shared public header — no duplicate here). --}}
    <div class="mb-[34px] text-center">
        {{-- Phone framing a QR code: the two ways in, in one mark. --}}
        <img src="{{ asset('images/portal/icon-scan-phone.svg') }}" alt=""
             class="mx-auto h-[75px] w-[49px] -rotate-[13.99deg]">

        <h1 class="mt-[22px] text-[26px] font-black text-[#004004] sm:text-[30px]">
            Track your requests now!
        </h1>
    </div>

    {{-- Two ways in, side by side: scan the receipt's QR, or type the number
         from it. The rule between them is decorative, so it drops on mobile
         where the columns stack. --}}
    <div class="mx-auto flex max-w-[1056px] flex-col items-center gap-8 md:flex-row md:items-stretch md:gap-0">

        {{-- ── Scan ──────────────────────────────────────────────────────────── --}}
        <section class="flex w-full max-w-[450px] flex-col md:flex-1">
            <div class="track-panel flex min-h-[572px] flex-1 flex-col rounded-[20px] p-[12px]">
                <h2 class="pb-[16px] pt-[8px] text-center text-[22px] font-extrabold text-[#017209]">Scan QR Code to track</h2>
                {{-- mb-4 rather than a top margin on the button: the button sinks with
                     mt-auto, which collapses to nothing when the card has no spare
                     height, so the gap has to come from above. --}}
                {{-- Square viewport. html5-qrcode injects a <video> sized to the
                     camera stream, which is landscape on nearly every device —
                     left to itself it letterboxes inside this box and leaves a
                     black band underneath. .qr-viewport makes the feed cover. --}}
                <div class="qr-viewport relative mb-4 overflow-hidden rounded-[20px] bg-[#1e1e1e]">
                    <div id="qr-reader" class="absolute inset-0 h-full w-full"></div>

                    {{-- Framing guide. Stays up while the camera runs — the
                         library's own shaded viewfinder is hidden in CSS. --}}
                    <div id="scanFrame" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        {{-- Four thick rounded corner brackets, as drawn. --}}
                        <div class="relative h-[250px] w-[250px]">
                            <span class="absolute left-0 top-0 h-[84px] w-[84px] rounded-tl-[16px] border-l-[11px] border-t-[11px] border-white"></span>
                            <span class="absolute right-0 top-0 h-[84px] w-[84px] rounded-tr-[16px] border-r-[11px] border-t-[11px] border-white"></span>
                            <span class="absolute bottom-0 left-0 h-[84px] w-[84px] rounded-bl-[16px] border-b-[11px] border-l-[11px] border-white"></span>
                            <span class="absolute bottom-0 right-0 h-[84px] w-[84px] rounded-br-[16px] border-b-[11px] border-r-[11px] border-white"></span>
                        </div>
                    </div>
                </div>

                <p id="scanStatus" class="sr-only" role="status" aria-live="polite">
                    Point your camera at the QR code on your document receipt.
                </p>

                <button id="startCameraBtn" type="button"
                        class="track-cta mt-auto inline-flex w-full items-center justify-center gap-[10px] rounded-[40px] bg-[#01721a] text-[20px] font-black text-white transition hover:bg-[#004004] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c]">
                    <img src="{{ asset('images/portal/icon-camera.svg') }}" alt=""
                         class="h-[26px] w-[26px] brightness-0 invert">
                    Start Camera
                </button>

                <button id="stopCameraBtn" type="button"
                        class="track-cta mt-auto hidden w-full items-center justify-center gap-[10px] rounded-[40px] border border-[rgba(0,64,4,0.5)] bg-white text-[20px] font-black text-[#004004] transition hover:bg-[rgba(141,255,60,0.2)]">
                    <svg class="h-5 w-5 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="6" y="6" width="12" height="12" rx="2"/>
                    </svg>
                    Stop Camera
                </button>

                {{-- A scan opens the record straight away; this panel is the
                     feedback for the half-second in between (and the manual way
                     through if the redirect is blocked), not a confirmation step. --}}
                <div id="scannedResult" class="mt-4 hidden space-y-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4">
                    <p class="text-sm font-bold text-emerald-900">QR code detected — opening your record…</p>
                    <p id="scannedId" class="break-all font-mono text-sm text-emerald-950"></p>
                    <div class="flex gap-3">
                        <button id="trackScannedBtn" type="button"
                                class="flex-1 rounded-full bg-emerald-900 py-2 text-sm font-bold text-white transition hover:bg-emerald-950">
                            Open it now
                        </button>
                        <button id="retryScanBtn" type="button"
                                class="rounded-full border border-emerald-300 px-4 py-2 text-sm font-bold text-emerald-800 transition hover:bg-white">
                            Retry
                        </button>
                    </div>
                </div>

            </div>
        </section>

        {{-- Rule between the two routes in — decorative, so it sits between the
             panels and disappears when they stack. --}}
        <div class="mx-[38px] hidden w-[2px] self-stretch bg-black/20 md:my-[88px] md:block" aria-hidden="true"></div>

        {{-- ── Type the number ───────────────────────────────────────────────── --}}
        <section class="flex w-full max-w-[450px] flex-col md:flex-1">
            <div class="track-panel flex min-h-[572px] flex-1 flex-col rounded-[20px] px-[25px] pb-[12px] pt-[12px]">
                <h2 class="pb-[16px] pt-[8px] text-center text-[22px] font-extrabold text-[#017209]">Enter Tracking Number</h2>

                {{-- Stand-in for the printed number on the receipt. --}}
                <img src="{{ asset('images/portal/illus-tracking-id.svg') }}" alt=""
                     class="mx-auto mt-[35px] h-[100px] w-[100px]">

                <div class="mt-[45px] flex items-start gap-[16px]">
                    <img src="{{ asset('images/portal/icon-info.svg') }}" alt=""
                         class="mt-[3px] h-[30px] w-[31px] shrink-0">
                    <p class="text-[18px] font-medium leading-snug text-[#33a64c]">
                        Your tracking ID is printed on the document receipt issued when the document was submitted.
                    </p>
                </div>

                <form method="GET" action="{{ route('citizen.track') }}" class="mt-auto flex flex-col gap-4 pt-5">
                    <label for="tracking" class="sr-only">Document tracking ID</label>

                    <input id="tracking"
                           type="text"
                           name="tracking"
                           value="{{ request('tracking') }}"
                           placeholder="SPD-XXXXXXXX-XXXXXX"
                           autocomplete="off"
                           required
                           @if(! empty($trackingError)) aria-invalid="true" aria-describedby="tracking-error" @endif
                           class="h-[70px] w-full rounded-[20px] border {{ ! empty($trackingError) ? 'border-rose-300 bg-rose-50/60 focus:border-rose-400 focus:ring-rose-400/30' : 'border-transparent bg-[rgba(1,114,26,0.3)] focus:border-[#01721a] focus:ring-[#8dff3c]/40' }} px-[36px] font-mono text-[18px] uppercase tracking-wider text-[#004004] placeholder:font-sans placeholder:text-[20px] placeholder:font-semibold placeholder:tracking-normal placeholder:text-[rgba(0,64,4,0.5)] transition focus:bg-white/70 focus:outline-none focus:ring-4">

                    @if(! empty($trackingError))
                        <div id="tracking-error" role="alert"
                             class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-rose-800">We couldn't track that number.</p>
                                <p class="mt-0.5 text-sm text-rose-700">{{ $trackingError }}</p>
                            </div>
                        </div>
                    @endif

                    <button type="submit"
                            class="track-cta inline-flex w-full items-center justify-center gap-[10px] rounded-[60px] bg-[#01721a] text-[20px] font-black text-white transition hover:bg-[#004004] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c]">
                        <img src="{{ asset('images/portal/icon-search.svg') }}" alt=""
                             class="h-[23px] w-[23px] brightness-0 invert">
                        Search
                    </button>
                </form>
            </div>
        </section>

    </div>

    {{-- Camera trouble interrupts with a dialog rather than a panel tucked under
         the card, which the citizen has to notice on their own. --}}
    <x-modal name="camera-error" maxWidth="md" focusable>
        <div class="p-6 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3.5h.01M10.3 4.2L2.5 17.5A2 2 0 004.2 20.5h15.6a2 2 0 001.7-3L13.7 4.2a2 2 0 00-3.4 0z"/>
                </svg>
            </span>

            <h2 id="cameraErrorTitle" class="mt-4 text-xl font-extrabold text-emerald-950">
                Camera access denied
            </h2>
            <p id="cameraErrorBody" class="mt-2 text-sm text-gray-600">
                Please allow camera access in your browser settings, then try again.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <button type="button" x-on:click="$dispatch('close'); startScanner()"
                        class="inline-flex w-full items-center justify-center rounded-full bg-emerald-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-950">
                    Try Again
                </button>
                <button type="button" x-on:click="$dispatch('close')"
                        class="inline-flex w-full items-center justify-center rounded-full border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-700 transition hover:bg-gray-50">
                    Close
                </button>
            </div>

            <p class="mt-4 text-xs text-gray-400">
                You can still track your document by typing its number on the right.
            </p>
        </div>
    </x-modal>

    {{-- The shared scanner (window.SpeedQr). This page used to carry its own
         copy plus a CDN <script> for html5-qrcode; the CDN copy overwrote the
         bundled one, so on an offline or locked-down LAN the library never
         arrived and Start Camera did nothing. The helper also fails loudly on
         an insecure origin, which is the usual reason no permission prompt
         ever appears. --}}
    @include('partials.qr-scan-helpers')

    <script>
        const trackUrl = @json(route('citizen.track'));
        let lastScannedId = '';

        const scanStatus = document.getElementById('scanStatus');
        const scanFrame = document.getElementById('scanFrame');
        const startCameraBtn = document.getElementById('startCameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const scannedResult = document.getElementById('scannedResult');
        const scannedIdEl = document.getElementById('scannedId');
        const cameraErrorTitle = document.getElementById('cameraErrorTitle');
        const cameraErrorBody = document.getElementById('cameraErrorBody');

        // The dialog is the x-modal component; it listens on the window for its
        // own name, so plain JS can drive it without an Alpine scope.
        function showCameraError(title, body) {
            cameraErrorTitle.textContent = title;
            cameraErrorBody.textContent = body;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'camera-error' }));
        }

        function hideCameraError() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'camera-error' }));
        }

        /** Name the failure; SpeedQr.describe() supplies the what-to-do-next. */
        function cameraErrorTitleFor(error) {
            if (! window.isSecureContext || ! navigator.mediaDevices) {
                return 'Camera blocked on this address';
            }

            switch ((error && error.name) || '') {
                case 'NotAllowedError':
                case 'SecurityError':
                    return 'Camera access denied';
                case 'NotFoundError':
                case 'DevicesNotFoundError':
                case 'OverconstrainedError':
                    return 'No camera found';
                case 'NotReadableError':
                case 'TrackStartError':
                    return 'Camera already in use';
                default:
                    return 'Could not start the camera';
            }
        }

        function goToTracking(trackingNumber) {
            window.location.href = trackUrl + '?tracking=' + encodeURIComponent(trackingNumber);
        }

        function setScannerUi(running) {
            startCameraBtn.classList.toggle('hidden', running);
            stopCameraBtn.classList.toggle('hidden', !running);
            stopCameraBtn.classList.toggle('inline-flex', running);
            // The framing guide stays up while the camera runs — the library's
            // own shaded viewfinder is hidden in CSS, because it is positioned
            // against the video's natural box rather than our square one.
        }

        function showScanResult(trackingNumber) {
            lastScannedId = trackingNumber;
            scannedIdEl.textContent = trackingNumber;
            scannedResult.classList.remove('hidden');
            scanStatus.textContent = 'QR code scanned. Opening ' + trackingNumber + '.';
        }

        function startScanner() {
            hideCameraError();
            scannedResult.classList.add('hidden');
            scanStatus.textContent = 'Initialising camera…';

            // Optimistic, like the staff scanners: the error callback puts the
            // UI back if the camera never opens.
            setScannerUi(true);

            window.SpeedQr.start('qr-reader', (decodedText) => {
                // extractTracking rejects codes this system did not issue, so a
                // Wi-Fi or payment QR can't send the citizen to a bogus lookup.
                const tracking = window.SpeedQr.extractTracking(decodedText);

                if (! tracking) {
                    scanStatus.textContent = window.SpeedQr.FOREIGN_CODE_MESSAGE;

                    return;
                }

                stopScanner();
                showScanResult(tracking);

                // Scanning IS the request: the citizen has already pointed the
                // camera at their receipt, so asking them to confirm the number
                // they cannot read anyway is a step with no decision in it. The
                // panel above stays as feedback while the page loads.
                goToTracking(tracking);
            }, (cameraError) => {
                setScannerUi(false);
                scanStatus.textContent = 'The camera could not be started.';
                showCameraError(cameraErrorTitleFor(cameraError), window.SpeedQr.describe(cameraError));
            });
        }

        function stopScanner() {
            window.SpeedQr.stop();
            setScannerUi(false);
        }

        startCameraBtn.addEventListener('click', startScanner);
        stopCameraBtn.addEventListener('click', () => {
            stopScanner();
            scanStatus.textContent = 'Camera stopped. Click Start Camera to scan again.';
        });
        document.getElementById('trackScannedBtn').addEventListener('click', () => {
            if (lastScannedId) {
                goToTracking(lastScannedId);
            }
        });
        document.getElementById('retryScanBtn').addEventListener('click', () => {
            scannedResult.classList.add('hidden');
            lastScannedId = '';
            startScanner();
        });
    </script>
</x-citizen-layout>
