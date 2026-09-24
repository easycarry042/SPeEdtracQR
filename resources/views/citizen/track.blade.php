<x-citizen-layout>
    <x-slot name="title">Track a Document</x-slot>

    {{-- Page header (Back lives in the shared public header — no duplicate here). --}}
    <div class="mb-10 text-center">
        {{-- Phone framing a QR code: the two ways in, in one mark. --}}
        <svg class="mx-auto h-16 w-16 text-emerald-950" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 48 48" aria-hidden="true">
            <rect x="15" y="5" width="18" height="38" rx="3"/>
            <path stroke-linecap="round" d="M21 10h6"/>
            <rect x="20" y="17" width="5" height="5" rx="1"/>
            <path stroke-linecap="round" d="M28 17h1.5M28 21h3M20 27h3M26 27h3M20 31h1.5M24 31h2M28 31h1"/>
        </svg>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-emerald-950 sm:text-4xl">
            Track your requests now!
        </h1>
    </div>

    {{-- Two ways in, side by side: scan the receipt's QR, or type the number
         from it. The rule between them is decorative, so it drops on mobile
         where the columns stack. --}}
    <div class="mx-auto flex max-w-4xl flex-col gap-8 md:flex-row md:items-stretch md:gap-8">

        {{-- ── Scan ──────────────────────────────────────────────────────────── --}}
        <section class="flex flex-1 flex-col">
            <h2 class="mb-4 text-center text-base font-extrabold text-emerald-800">Scan QR Code to track</h2>

            <div class="portal-card flex flex-1 flex-col rounded-2xl p-4">
                {{-- mb-4 rather than a top margin on the button: the button sinks with
                     mt-auto, which collapses to nothing when the card has no spare
                     height, so the gap has to come from above. --}}
                <div class="relative mb-4 overflow-hidden rounded-xl bg-[#232323]" style="min-height: 260px;">
                    <div id="qr-reader" class="min-h-[260px] w-full"></div>

                    {{-- Idle framing guide. Hidden once the camera starts, so it
                         doesn't stack with the scanner's own viewfinder box. --}}
                    <div id="scanFrame" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative h-40 w-40">
                            <span class="absolute left-0 top-0 h-8 w-8 border-l-4 border-t-4 border-white rounded-tl"></span>
                            <span class="absolute right-0 top-0 h-8 w-8 border-r-4 border-t-4 border-white rounded-tr"></span>
                            <span class="absolute bottom-0 left-0 h-8 w-8 border-b-4 border-l-4 border-white rounded-bl"></span>
                            <span class="absolute bottom-0 right-0 h-8 w-8 border-b-4 border-r-4 border-white rounded-br"></span>
                        </div>
                    </div>
                </div>

                <p id="scanStatus" class="sr-only" role="status" aria-live="polite">
                    Point your camera at the QR code on your document receipt.
                </p>

                <button id="startCameraBtn" type="button"
                        class="mt-auto inline-flex w-full items-center justify-center gap-2 rounded-full bg-emerald-900 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-950 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h1.6a2 2 0 001.7-1l.5-.9a1 1 0 01.9-.6h4.6a1 1 0 01.9.6l.5.9a2 2 0 001.7 1H19a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <circle cx="12" cy="13" r="3.2"/>
                    </svg>
                    Start Camera
                </button>

                <button id="stopCameraBtn" type="button"
                        class="mt-auto hidden w-full items-center justify-center gap-2 rounded-full border border-emerald-300 bg-white px-6 py-3.5 text-sm font-bold text-emerald-900 shadow-sm transition hover:bg-emerald-50">
                    <svg class="h-5 w-5 text-rose-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="6" y="6" width="12" height="12" rx="2"/>
                    </svg>
                    Stop Camera
                </button>

                <div id="scannedResult" class="mt-4 hidden space-y-3 rounded-xl border border-emerald-300 bg-emerald-50 p-4">
                    <p class="text-sm font-bold text-emerald-900">QR code detected</p>
                    <p id="scannedId" class="break-all font-mono text-sm text-emerald-950"></p>
                    <div class="flex gap-3">
                        <button id="trackScannedBtn" type="button"
                                class="flex-1 rounded-full bg-emerald-900 py-2 text-sm font-bold text-white transition hover:bg-emerald-950">
                            Track this Document
                        </button>
                        <button id="retryScanBtn" type="button"
                                class="rounded-full border border-emerald-300 px-4 py-2 text-sm font-bold text-emerald-800 transition hover:bg-white">
                            Retry
                        </button>
                    </div>
                </div>

            </div>
        </section>

        {{-- Rule between the two routes in — decorative, so it sits below the
             column headings and disappears when they stack. --}}
        <div class="hidden w-px self-stretch bg-emerald-400/50 md:mt-12 md:block" aria-hidden="true"></div>

        {{-- ── Type the number ───────────────────────────────────────────────── --}}
        <section class="flex flex-1 flex-col">
            <h2 class="mb-4 text-center text-base font-extrabold text-emerald-800">Enter Tracking Number</h2>

            <div class="portal-card flex flex-1 flex-col rounded-2xl p-6">
                {{-- Stand-in for the printed number on the receipt. --}}
                <svg class="mx-auto h-24 w-24 text-emerald-950" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 48 48" aria-hidden="true">
                    <rect x="6" y="14" width="36" height="18" rx="4"/>
                    <path stroke-linecap="round" d="M14 20l4 6m0-6l-4 6M22 20l4 6m0-6l-4 6M30 20l4 6m0-6l-4 6"/>
                    <path stroke-linecap="round" d="M37 27v-1"/>
                </svg>

                <div class="mt-4 flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path stroke-linecap="round" d="M12 11v5M12 8h.01"/>
                    </svg>
                    <p class="text-sm font-semibold text-emerald-800">
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
                           class="w-full rounded-full border {{ ! empty($trackingError) ? 'border-rose-300 bg-rose-50/60 focus:border-rose-400 focus:ring-rose-400/30' : 'border-transparent bg-emerald-200/50 focus:border-emerald-600 focus:ring-emerald-500/25' }} px-5 py-3.5 font-mono text-sm uppercase tracking-wider text-emerald-950 placeholder:font-sans placeholder:tracking-normal placeholder:text-emerald-950/45 transition focus:bg-white/70 focus:outline-none focus:ring-4">

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
                            class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-emerald-900 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-950 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="M20 20l-4.5-4.5"/>
                        </svg>
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
            // The idle guide would otherwise sit on top of the scanner's own
            // viewfinder box once the camera is live.
            scanFrame.classList.toggle('hidden', running);
        }

        function showScanResult(trackingNumber) {
            lastScannedId = trackingNumber;
            scannedIdEl.textContent = trackingNumber;
            scannedResult.classList.remove('hidden');
            scanStatus.textContent = 'QR code scanned successfully.';
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
