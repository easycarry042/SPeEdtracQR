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
        <p class="mt-2 text-gray-500">Enter your tracking number below or scan the QR code on your receipt.</p>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Manual tracking input --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Enter Tracking Number</h2>

            <form method="GET" action="{{ route('citizen.track') }}" class="mt-4 space-y-4">
                <label for="tracking" class="block text-sm font-semibold text-gray-700">
                    Document Tracking ID
                </label>

                <div class="flex gap-3">
                    <input id="tracking"
                           type="text"
                           name="tracking"
                           value="{{ request('tracking') }}"
                           placeholder="e.g. SPD-20260521-00001"
                           autocomplete="off"
                           required
                           class="flex-1 rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 font-mono text-sm tracking-wider text-gray-800 uppercase placeholder:font-sans placeholder:normal-case placeholder:text-gray-400 shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-400/30">
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

        <div class="flex items-center gap-4">
            <div class="h-px flex-1 bg-emerald-200"></div>
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">or scan QR code</span>
            <div class="h-px flex-1 bg-emerald-200"></div>
        </div>

        {{-- QR scanner --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Scan QR Code</h2>
            <p id="scanStatus" class="text-center text-sm font-medium text-gray-600">
                Point your camera at the QR code on your document receipt.
            </p>

            <div class="relative mx-auto max-w-sm">
                <div id="qr-reader"
                     class="overflow-hidden rounded-xl border-2 border-dashed border-emerald-300 bg-gray-50"
                     style="min-height: 260px;">
                </div>
            </div>

            <div class="text-center space-y-3">
                <button id="startCameraBtn" type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 8l6 4-6 4V8z"/>
                    </svg>
                    Start Camera
                </button>
                <button id="stopCameraBtn" type="button"
                        class="hidden inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="6" y="6" width="12" height="12" rx="2"/>
                    </svg>
                    Stop Camera
                </button>
            </div>

            <div id="scannedResult" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3">
                <p class="text-sm font-semibold text-emerald-800">QR code detected</p>
                <p id="scannedId" class="break-all font-mono text-sm text-emerald-900"></p>
                <div class="flex gap-3">
                    <button id="trackScannedBtn" type="button"
                            class="flex-1 rounded-lg bg-emerald-500 py-2 text-sm font-semibold text-white transition hover:bg-emerald-600">
                        Track this Document
                    </button>
                    <button id="retryScanBtn" type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Retry
                    </button>
                </div>
            </div>

            <div id="cameraError" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                <strong>Camera access denied.</strong>
                Please allow camera access in your browser settings, then click Start Camera again.
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const trackUrl = @json(route('citizen.track'));
        let scanner = null;
        let scannerRunning = false;
        let lastScannedId = '';

        const scanStatus = document.getElementById('scanStatus');
        const startCameraBtn = document.getElementById('startCameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const scannedResult = document.getElementById('scannedResult');
        const scannedIdEl = document.getElementById('scannedId');
        const cameraError = document.getElementById('cameraError');

        function normalizeTracking(decodedText) {
            const stripped = decodedText.trim();
            if (stripped.includes('/track/')) {
                return stripped.split('/track/').pop().split('?')[0];
            }
            const parts = stripped.split('/').filter(Boolean);
            return parts.length > 0 ? parts[parts.length - 1] : stripped;
        }

        function goToTracking(trackingNumber) {
            window.location.href = trackUrl + '?tracking=' + encodeURIComponent(trackingNumber);
        }

        function setScannerUi(running) {
            scannerRunning = running;
            startCameraBtn.classList.toggle('hidden', running);
            stopCameraBtn.classList.toggle('hidden', !running);
        }

        function showScanResult(trackingNumber) {
            lastScannedId = trackingNumber;
            scannedIdEl.textContent = trackingNumber;
            scannedResult.classList.remove('hidden');
            scanStatus.textContent = 'QR code scanned successfully.';
        }

        function onScanSuccess(decodedText) {
            stopScanner();
            showScanResult(normalizeTracking(decodedText));
        }

        function startScanner() {
            cameraError.classList.add('hidden');
            scannedResult.classList.add('hidden');
            scanStatus.textContent = 'Initialising camera…';

            if (!scanner) {
                scanner = new Html5Qrcode('qr-reader');
            }

            Html5Qrcode.getCameras()
                .then(devices => {
                    if (!devices || devices.length === 0) {
                        scanStatus.textContent = 'No camera found on this device.';
                        cameraError.classList.remove('hidden');
                        return;
                    }

                    const cameraId = devices[devices.length - 1].id;

                    scanner.start(
                        cameraId,
                        { fps: 10, qrbox: { width: 220, height: 220 } },
                        onScanSuccess,
                        () => {}
                    ).then(() => {
                        setScannerUi(true);
                        scanStatus.textContent = 'Point your camera at the QR code on your document receipt.';
                    }).catch(err => {
                        console.error(err);
                        scanStatus.textContent = 'Could not start camera. Please try again.';
                        cameraError.classList.remove('hidden');
                    });
                })
                .catch(err => {
                    console.error(err);
                    scanStatus.textContent = 'Camera access was denied.';
                    cameraError.classList.remove('hidden');
                });
        }

        function stopScanner() {
            if (!scanner || !scannerRunning) {
                setScannerUi(false);
                return;
            }

            scanner.stop().then(() => {
                setScannerUi(false);
                scanStatus.textContent = 'Camera stopped. Click Start Camera to scan again.';
            }).catch(() => setScannerUi(false));
        }

        startCameraBtn.addEventListener('click', startScanner);
        stopCameraBtn.addEventListener('click', stopScanner);
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
