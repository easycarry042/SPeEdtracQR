<x-citizen-layout>
    <x-slot name="title">Track a Document</x-slot>

    {{-- Page header (Back lives in the shared public header — no duplicate here).
         Centred, like the landing page's section heads, because the two panels
         under it are centred too. --}}
    <x-page-intro eyebrow="Track a request" title="Where is my request?" class="mx-auto mb-10 text-center">
        Scan the QR code from your confirmation email or printed slip, or type the
        tracking number printed under it.
    </x-page-intro>

    {{-- Two ways in, side by side: scan the receipt's QR, or type the number
         from it. The rule between them is decorative, so it drops on mobile
         where the columns stack. --}}
    <div class="mx-auto flex max-w-[845px] flex-col items-center gap-8 md:flex-row md:items-stretch md:gap-0">

        {{-- ── Scan ──────────────────────────────────────────────────────────── --}}
        <section class="flex w-full max-w-[360px] flex-col md:flex-1">
            <div class="track-panel flex min-h-[457.5px] flex-1 flex-col p-4 sm:p-5">
                <h2 class="cz-card-title pb-4 text-center">Scan QR Code to track</h2>
                {{-- mb-4 rather than a top margin on the button: the button sinks with
                     mt-auto, which collapses to nothing when the card has no spare
                     height, so the gap has to come from above. --}}
                {{-- Square viewport. The camera picture is landscape on nearly
                     every device; .qr-viewport fills the square with it without
                     distorting what the decoder reads (see app.css). --}}
                <div class="qr-viewport relative mb-4 overflow-hidden rounded-[16px] bg-[#1e1e1e]">
                    {{-- .qr-surface: laid out at the camera's own shape and drawn
                         scaled down, so the decoder sees undistorted pixels (see
                         .qr-viewport in app.css and SpeedQr.fitSurface). --}}
                    <div id="qr-reader" class="qr-surface"></div>

                    {{-- Framing guide. Stays up while the camera runs — the
                         library's own shaded viewfinder is hidden in CSS. It is
                         70% of the window because that is exactly the region the
                         decoder reads (SpeedQr's qrbox): it used to be a fixed
                         200px, so a QR lined up inside it could still sit partly
                         outside what was being decoded. --}}
                    <div id="scanFrame" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        {{-- Four thick rounded corner brackets, as drawn. --}}
                        <div class="relative aspect-square w-[70%]">
                            <span class="absolute left-0 top-0 h-[30%] w-[30%] rounded-tl-[13px] border-l-[8px] border-t-[8px] border-white"></span>
                            <span class="absolute right-0 top-0 h-[30%] w-[30%] rounded-tr-[13px] border-r-[8px] border-t-[8px] border-white"></span>
                            <span class="absolute bottom-0 left-0 h-[30%] w-[30%] rounded-bl-[13px] border-b-[8px] border-l-[8px] border-white"></span>
                            <span class="absolute bottom-0 right-0 h-[30%] w-[30%] rounded-br-[13px] border-b-[8px] border-r-[8px] border-white"></span>
                        </div>
                    </div>
                </div>

                <p id="scanStatus" class="sr-only" role="status" aria-live="polite">
                    Point your camera at the QR code on your document receipt.
                </p>

                <button id="startCameraBtn" type="button"
                        class="cz-btn cz-btn-primary mt-auto w-full">
                    <img src="{{ asset('images/portal/icon-camera.svg') }}" alt=""
                         class="h-5 w-5 brightness-0 invert">
                    Start Camera
                </button>

                <button id="stopCameraBtn" type="button"
                        class="cz-btn cz-btn-secondary mt-auto hidden w-full">
                    <svg class="text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="6" y="6" width="12" height="12" rx="2"/>
                    </svg>
                    Stop Camera
                </button>

                {{-- A scan opens the record straight away; this panel is the
                     feedback for the half-second in between (and the manual way
                     through if the redirect is blocked), not a confirmation step. --}}
                <div id="scannedResult" class="mt-4 hidden space-y-3 rounded-[16px] border border-[rgba(1,114,26,0.18)] bg-[#eef8ea] p-4">
                    <p class="text-[15px] font-bold text-[#004004]">QR code detected — opening your record…</p>
                    <p id="scannedId" class="break-all font-mono text-sm text-[#004004]"></p>
                    <div class="flex gap-3">
                        <button id="trackScannedBtn" type="button"
                                class="cz-btn cz-btn-primary cz-btn-sm flex-1">
                            Open it now
                        </button>
                        <button id="retryScanBtn" type="button"
                                class="cz-btn cz-btn-secondary cz-btn-sm">
                            Retry
                        </button>
                    </div>
                </div>

            </div>
        </section>

        {{-- Rule between the two routes in — decorative, so it sits between the
             panels and disappears when they stack. --}}
        <div class="mx-[30.5px] hidden w-px self-stretch bg-[rgba(0,64,4,0.12)] md:my-[70.5px] md:block" aria-hidden="true"></div>

        {{-- ── Type the number ───────────────────────────────────────────────── --}}
        <section class="flex w-full max-w-[360px] flex-col md:flex-1">
            <div class="track-panel flex min-h-[457.5px] flex-1 flex-col p-4 sm:p-5">
                <h2 class="cz-card-title pb-4 text-center">Enter Tracking Number</h2>

                {{-- Stand-in for the printed number on the receipt. --}}
                <img src="{{ asset('images/portal/illus-tracking-id.svg') }}" alt=""
                     class="mx-auto mt-[28px] h-[80px] w-[80px]">

                <div class="mt-[36px] flex items-start gap-[13px]">
                    <img src="{{ asset('images/portal/icon-info.svg') }}" alt=""
                         class="mt-[3px] h-[24px] w-[25px] shrink-0">
                    <p class="text-[15px] font-medium leading-snug text-[#4b5a50]">
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
                           class="req-field text-center font-mono uppercase tracking-wider placeholder:font-sans placeholder:not-italic placeholder:tracking-normal">

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

                    <button type="submit" class="cz-btn cz-btn-primary w-full">
                        <img src="{{ asset('images/portal/icon-search.svg') }}" alt=""
                             class="h-[18px] w-[18px] brightness-0 invert">
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

            <h2 id="cameraErrorTitle" class="cz-card-title mt-4">
                Camera access denied
            </h2>
            <p id="cameraErrorBody" class="mt-2 text-sm text-gray-600">
                Please allow camera access in your browser settings, then try again.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row-reverse">
                <button type="button" x-on:click="$dispatch('close'); startScanner()"
                        class="cz-btn cz-btn-primary w-full">
                    Try Again
                </button>
                <button type="button" x-on:click="$dispatch('close')"
                        class="cz-btn cz-btn-secondary w-full">
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
