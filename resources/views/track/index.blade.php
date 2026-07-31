<x-app-layout>
    {{-- Look up hub: find a ticket by tracking number or by its QR code.
         The QR-IMAGE UPLOAD is the primary scan path (works on every device);
         the live camera is offered only when a camera actually exists.
         Rendered for /track?find=1 (and /scan) and as the empty fallback. --}}
    <div class="mx-auto w-full max-w-lg py-6">
        <div class="panel p-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            </div>
            <p class="mt-3 font-semibold text-ink">Look up a document</p>
            <p class="mt-1 text-sm text-ink-soft">Finding a document only opens it — status is changed on the document itself.</p>

            @if($errors->has('lookup'))
                <div class="mt-4 rounded-lg border border-status-red-wash bg-status-red-wash px-4 py-3 text-sm font-semibold text-status-red" role="alert">
                    {{ $errors->first('lookup') }}
                </div>
            @endif

            {{-- 1 · Tracking number search --}}
            <form method="GET" action="{{ route('track.index') }}" class="mt-6 space-y-3 border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Type the tracking number</p>
                <input id="trackingInput" name="tracking_number" class="w-full rounded-lg border border-hairline-strong px-4 py-3 text-center font-mono text-sm text-ink shadow-sm transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20" placeholder="SPD-YYYYMMDD-XXXXXX">
                <button type="submit" class="w-full rounded-lg bg-green py-3 font-semibold text-white transition hover:bg-green-deep focus:outline-none focus:ring-2 focus:ring-green/40 focus:ring-offset-2">
                    Search
                </button>
            </form>

            {{-- 2 · PRIMARY scan path: upload the QR image (every device has this) --}}
            <div class="mt-4 border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Or find it from the QR code</p>
                <button type="button" id="qrUploadBtn"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-green-deep py-4 text-[15px] font-bold text-white shadow-sm transition hover:bg-green focus:outline-none focus:ring-2 focus:ring-green/40 focus:ring-offset-2">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span id="qrUploadLabel">Upload QR image</span>
                </button>
                <p class="mt-2 text-xs text-ink-soft">Choose a photo or screenshot of the QR — or drag one onto the button.</p>
                <input id="qrFile" type="file" accept="image/*" class="hidden">
                <p id="qrError" class="mt-2 hidden text-sm font-semibold text-status-red" role="alert"></p>
            </div>

            {{-- 3 · Live camera — only rendered when a camera exists --}}
            <div id="cameraSection" class="mt-4 hidden border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Or scan with your camera</p>
                <button id="cameraToggle" type="button" class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-hairline-strong bg-green-wash/40 py-3 text-sm font-semibold text-green-deep transition hover:border-green-bright hover:bg-green-wash">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 19H3a2 2 0 01-2-2v-2m8-4h.01M12 12h.01M16 12h.01M8 12h.01"/></svg>
                    <span id="cameraToggleLabel">Open camera scanner</span>
                </button>
                <div id="reader" class="mt-3 hidden min-h-[220px] overflow-hidden rounded-lg border border-hairline-strong"></div>
            </div>
        </div>
    </div>

    @include('partials.qr-scan-helpers')
    <script>
        (function () {
            const trackBase = @json(url('/track'));
            const uploadBtn = document.getElementById('qrUploadBtn');
            const uploadLabel = document.getElementById('qrUploadLabel');
            const fileInput = document.getElementById('qrFile');
            const errorEl = document.getElementById('qrError');
            const trackingInput = document.getElementById('trackingInput');
            const cameraSection = document.getElementById('cameraSection');
            const cameraToggle = document.getElementById('cameraToggle');
            const cameraToggleLabel = document.getElementById('cameraToggleLabel');
            const readerEl = document.getElementById('reader');

            const extractTracking = window.SpeedQr.extractTracking;

            function showError(message) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            }

            function openDocument(tracking) {
                window.location.href = `${trackBase}/${encodeURIComponent(tracking)}`;
            }

            // ── Primary: upload / drag-and-drop a QR image ──
            function readQrFile(file) {
                if (!file) return;
                errorEl.classList.add('hidden');
                uploadLabel.textContent = 'Reading image…';
                uploadBtn.disabled = true;

                const reader = new Html5Qrcode('qr-file-reader-temp', /* verbose= */ false);
                reader.scanFile(file, false)
                    .then((decodedText) => {
                        const tracking = extractTracking(decodedText);
                        if (!tracking) {
                            showError(window.SpeedQr.FOREIGN_CODE_MESSAGE);
                            return;
                        }
                        if (trackingInput) trackingInput.value = tracking;
                        uploadLabel.textContent = 'Opening…';
                        openDocument(tracking);
                    })
                    .catch(() => {
                        showError('Could not read a QR code from that image. Use a clearer, well-lit photo — or type the tracking number above.');
                    })
                    .finally(() => {
                        uploadBtn.disabled = false;
                        if (uploadLabel.textContent !== 'Opening…') uploadLabel.textContent = 'Upload QR image';
                        fileInput.value = '';
                    });
            }

            uploadBtn.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => readQrFile(e.target.files && e.target.files[0]));

            ['dragover', 'dragenter'].forEach((ev) => uploadBtn.addEventListener(ev, (e) => {
                e.preventDefault();
                uploadBtn.classList.add('ring-2', 'ring-green/60');
            }));
            ['dragleave', 'drop'].forEach((ev) => uploadBtn.addEventListener(ev, (e) => {
                e.preventDefault();
                uploadBtn.classList.remove('ring-2', 'ring-green/60');
            }));
            uploadBtn.addEventListener('drop', (e) => readQrFile(e.dataTransfer.files && e.dataTransfer.files[0]));

            // ── Tertiary: live camera, only when one actually exists ──
            window.SpeedQr.hasCamera().then((yes) => { if (yes) cameraSection.classList.remove('hidden'); });

            let cameraOn = false;

            function stopCamera() {
                window.SpeedQr.stop();
                cameraOn = false;
                readerEl.classList.add('hidden');
                cameraToggleLabel.textContent = 'Open camera scanner';
            }

            cameraToggle.addEventListener('click', function () {
                if (cameraOn) {
                    stopCamera();
                    return;
                }

                errorEl.classList.add('hidden');
                readerEl.classList.remove('hidden');
                cameraToggleLabel.textContent = 'Close camera scanner';
                cameraOn = true;

                window.SpeedQr.start('reader', (decodedText) => {
                    // Only codes THIS system issued may open a record. The old
                    // fallback to the raw decoded text meant any QR at all — a
                    // Wi-Fi code, a payment code, another site's link — was taken
                    // as a tracking number and navigated to.
                    const tracking = extractTracking(decodedText);
                    stopCamera();
                    if (!tracking) {
                        showError(window.SpeedQr.FOREIGN_CODE_MESSAGE);

                        return;
                    }
                    openDocument(tracking);
                }, (cameraError) => {
                    stopCamera();
                    showError(window.SpeedQr.describe(cameraError) + ' You can upload a QR image instead — it works the same.');
                });
            });
        })();
    </script>

    {{-- html5-qrcode's scanFile needs a container element to exist in the DOM. --}}
    <div id="qr-file-reader-temp" class="hidden"></div>
</x-app-layout>
