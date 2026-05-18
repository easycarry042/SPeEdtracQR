<x-citizen-layout>
    <x-slot name="title">Track a Document</x-slot>

    {{-- Page header --}}
    <div class="mb-8 text-center">
        <a href="{{ route('citizen.dashboard') }}"
           class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Citizen Portal
        </a>
        <h1 class="text-3xl font-extrabold tracking-tight text-emerald-950 sm:text-4xl">
            Track a Document
        </h1>
        <p class="mt-2 text-gray-500">Enter your tracking ID manually or scan the QR code on your document receipt.</p>
    </div>

    <div class="mx-auto max-w-2xl space-y-6" x-data="trackPage()">

        {{-- ── Tab Switcher ─────────────────────────────────────────────────── --}}
        <div class="flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
            <button @click="tab = 'manual'"
                    :class="tab === 'manual'
                        ? 'bg-emerald-500 text-white shadow'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Enter Tracking ID
            </button>
            <button @click="tab = 'scan'; initScanner()"
                    :class="tab === 'scan'
                        ? 'bg-emerald-500 text-white shadow'
                        : 'text-gray-500 hover:text-gray-700'"
                    class="flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 7V5a2 2 0 0 1 2-2h2m10 0h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                    <rect x="9" y="9" width="6" height="6" rx="1" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Scan QR Code
            </button>
        </div>

        {{-- ── Tab: Manual Input ─────────────────────────────────────────────── --}}
        <div x-show="tab === 'manual'" x-cloak>
            <form method="GET" action="{{ route('citizen.track') }}"
                  class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">

                <label for="tracking" class="block text-sm font-semibold text-gray-700">
                    Document Tracking ID
                </label>

                <div class="flex gap-3">
                    <input id="tracking"
                           type="text"
                           name="tracking"
                           :value="manualId"
                           @input="manualId = $event.target.value"
                           placeholder="e.g. SPD-2026-00001"
                           autocomplete="off"
                           required
                           class="flex-1 rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm font-mono tracking-wider text-gray-800 placeholder-gray-400 shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0z"/>
                        </svg>
                        Track
                    </button>
                </div>

                <p class="text-xs text-gray-400">
                    Your tracking ID is printed on the document receipt issued when the document was submitted.
                </p>
            </form>
        </div>

        {{-- ── Tab: QR Scanner ──────────────────────────────────────────────── --}}
        <div x-show="tab === 'scan'" x-cloak>
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">

                {{-- Status / instruction text --}}
                <p class="text-center text-sm font-medium text-gray-600" x-text="scanStatus"></p>

                {{-- Camera preview area --}}
                <div class="relative mx-auto max-w-sm">
                    <div id="qr-reader"
                         class="overflow-hidden rounded-xl border-2 border-dashed border-emerald-300 bg-gray-50"
                         style="min-height: 260px;">
                    </div>

                    {{-- Scan overlay corners --}}
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center" x-show="scannerRunning" x-cloak>
                        <div class="relative h-44 w-44">
                            <span class="absolute top-0 left-0 h-6 w-6 border-t-4 border-l-4 border-emerald-500 rounded-tl-lg"></span>
                            <span class="absolute top-0 right-0 h-6 w-6 border-t-4 border-r-4 border-emerald-500 rounded-tr-lg"></span>
                            <span class="absolute bottom-0 left-0 h-6 w-6 border-b-4 border-l-4 border-emerald-500 rounded-bl-lg"></span>
                            <span class="absolute bottom-0 right-0 h-6 w-6 border-b-4 border-r-4 border-emerald-500 rounded-br-lg"></span>
                        </div>
                    </div>
                </div>

                {{-- Start / Stop scanner button --}}
                <div class="text-center space-y-3">
                    <button x-show="!scannerRunning"
                            @click="startScanner()"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 8l6 4-6 4V8z"/>
                        </svg>
                        Start Camera
                    </button>

                    <button x-show="scannerRunning"
                            x-cloak
                            @click="stopScanner()"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                        <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="2"/>
                        </svg>
                        Stop Camera
                    </button>
                </div>

                {{-- Scanned result preview + confirm --}}
                <div x-show="scannedId" x-cloak class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3">
                    <p class="text-sm font-semibold text-emerald-800">QR Code detected!</p>
                    <p class="font-mono text-sm text-emerald-900 break-all" x-text="scannedId"></p>
                    <div class="flex gap-3">
                        <button @click="trackScanned()"
                                class="flex-1 rounded-lg bg-emerald-500 py-2 text-sm font-semibold text-white hover:bg-emerald-600 transition">
                            Track this Document
                        </button>
                        <button @click="scannedId = ''; startScanner()"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
                            Retry
                        </button>
                    </div>
                </div>

                {{-- Camera permission error --}}
                <div x-show="cameraError" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    <strong>Camera access denied.</strong>
                    Please allow camera access in your browser settings, then refresh the page.
                </div>
            </div>
        </div>

    </div>

    {{-- ── html5-qrcode library (npm package version) ──────────────────────── --}}
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        function trackPage() {
            return {
                tab: 'manual',
                manualId: '',

                // Scanner state
                scanner: null,
                scannerRunning: false,
                scannedId: '',
                scanStatus: 'Point your camera at the QR code on your document.',
                cameraError: false,

                initScanner() {
                    // Lazily create the scanner instance once
                    if (!this.scanner) {
                        this.scanner = new Html5Qrcode('qr-reader');
                    }
                },

                startScanner() {
                    this.cameraError = false;
                    this.scannedId   = '';
                    this.scanStatus  = 'Initialising camera…';

                    Html5Qrcode.getCameras()
                        .then(devices => {
                            if (!devices || devices.length === 0) {
                                this.scanStatus  = 'No camera found on this device.';
                                this.cameraError = true;
                                return;
                            }
                            const cameraId = devices[devices.length - 1].id; // prefer back camera

                            this.scanner.start(
                                cameraId,
                                { fps: 10, qrbox: { width: 220, height: 220 } },
                                (decodedText) => this.onScanSuccess(decodedText),
                                () => {}  // ignore per-frame failures silently
                            ).then(() => {
                                this.scannerRunning = true;
                                this.scanStatus = 'Point your camera at the QR code on your document.';
                            }).catch(err => {
                                console.error(err);
                                this.scanStatus  = 'Could not start camera: ' + err;
                                this.cameraError = true;
                            });
                        })
                        .catch(err => {
                            console.error(err);
                            this.cameraError = true;
                            this.scanStatus  = 'Camera access was denied. Please check your browser permissions.';
                        });
                },

                stopScanner() {
                    if (this.scanner && this.scannerRunning) {
                        this.scanner.stop().then(() => {
                            this.scannerRunning = false;
                            this.scanStatus = 'Camera stopped. Click "Start Camera" to scan again.';
                        });
                    }
                },

                onScanSuccess(text) {
                    this.stopScanner();
                    // The QR may encode a full URL like https://example.com/track/TRK-123
                    // or just the bare tracking number. Extract the last path segment.
                    const stripped = text.trim();
                    const parts    = stripped.split('/').filter(Boolean);
                    this.scannedId = parts.length > 0 ? parts[parts.length - 1] : stripped;
                    this.scanStatus = 'QR code scanned successfully!';
                },

                trackScanned() {
                    if (this.scannedId) {
                        window.location.href = '/citizen/track?tracking=' + encodeURIComponent(this.scannedId);
                    }
                },
            };
        }
    </script>
</x-citizen-layout>
