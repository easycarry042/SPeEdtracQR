<x-app-layout>
    {{-- Look up hub: tracking-number search, QR image upload, and an on-demand
         live camera scanner. Rendered for /track?find=1 (and /scan, which
         redirects here), and as the fallback when there is nothing to open. --}}
    <div class="mx-auto w-full max-w-lg py-6">
        <div class="panel p-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
            </div>
            <p class="mt-3 font-semibold text-ink">Look up a document</p>
            <p class="mt-1 text-sm text-ink-soft">Scanning identifies a document and opens it — it doesn't change its status. Use the Status Progress controls on the document to move it forward.</p>

            <form method="GET" action="{{ route('track.index') }}" class="mt-6 space-y-3 border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Type the tracking number</p>
                <input id="trackingInput" name="tracking_number" class="w-full rounded-lg border border-hairline-strong px-4 py-3 text-center font-mono text-sm text-ink shadow-sm transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20" placeholder="SPD-YYYYMMDD-XXXXXX">
                <button type="submit" class="w-full rounded-lg bg-green py-3 font-semibold text-white transition hover:bg-green-deep focus:outline-none focus:ring-2 focus:ring-green/40 focus:ring-offset-2">
                    Search
                </button>
            </form>

            {{-- Upload-to-scan: decode a saved QR image client-side, then jump to the document. --}}
            <div class="mt-4 border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Have your QR code saved as an image?</p>
                <label for="qrFile" class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-hairline-strong bg-green-wash/40 py-3 text-sm font-semibold text-green-deep transition hover:border-green-bright hover:bg-green-wash">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span id="qrFileLabel">Upload QR code image</span>
                </label>
                <input id="qrFile" type="file" accept="image/*" class="hidden">
                <p id="qrError" class="mt-2 hidden text-sm font-semibold text-status-red"></p>
            </div>

            {{-- Live camera scan, started on demand so the page doesn't grab the camera on load. --}}
            <div class="mt-4 border-t border-hairline pt-6">
                <p class="text-sm text-ink-soft">Or scan the QR on the folder</p>
                <button id="cameraToggle" type="button" class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-hairline-strong bg-green-wash/40 py-3 text-sm font-semibold text-green-deep transition hover:border-green-bright hover:bg-green-wash">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 19H3a2 2 0 01-2-2v-2m8-4h.01M12 12h.01M16 12h.01M8 12h.01"/></svg>
                    <span id="cameraToggleLabel">Open camera scanner</span>
                </button>
                <div id="reader" class="mt-3 hidden overflow-hidden rounded-lg border border-hairline-strong"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function () {
            const trackBase = @json(url('/track'));
            const fileInput = document.getElementById('qrFile');
            const fileLabel = document.getElementById('qrFileLabel');
            const errorEl = document.getElementById('qrError');
            const trackingInput = document.getElementById('trackingInput');
            const cameraToggle = document.getElementById('cameraToggle');
            const cameraToggleLabel = document.getElementById('cameraToggleLabel');
            const readerEl = document.getElementById('reader');

            // Tracking number format: SPD-YYYYMMDD-XXXXXX (6 base32 chars).
            const TRACKING_RE = /SPD-\d{8}-[0-9A-Z]{6}/i;

            function showError(message) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            }

            function extractTracking(decodedText) {
                const match = (decodedText || '').match(TRACKING_RE);
                return match ? match[0].toUpperCase() : null;
            }

            function openDocument(tracking) {
                window.location.href = `${trackBase}/${encodeURIComponent(tracking)}`;
            }

            fileInput.addEventListener('change', function (e) {
                const file = e.target.files && e.target.files[0];
                if (!file) return;

                errorEl.classList.add('hidden');
                fileLabel.textContent = 'Reading…';

                const reader = new Html5Qrcode('qr-file-reader-temp', /* verbose= */ false);
                reader.scanFile(file, false)
                    .then((decodedText) => {
                        const tracking = extractTracking(decodedText);
                        if (!tracking) {
                            fileLabel.textContent = 'Upload QR code image';
                            showError('That QR code does not contain a valid SPD- tracking number.');
                            return;
                        }
                        if (trackingInput) trackingInput.value = tracking;
                        openDocument(tracking);
                    })
                    .catch(() => {
                        fileLabel.textContent = 'Upload QR code image';
                        showError('Could not read a QR code from that image. Try a clearer photo.');
                    })
                    .finally(() => {
                        fileInput.value = '';
                    });
            });

            // Live camera scanner — started/stopped on demand.
            let scanner = null;

            function stopCamera() {
                if (!scanner) return;
                scanner.stop().catch(() => {});
                scanner = null;
                readerEl.classList.add('hidden');
                cameraToggleLabel.textContent = 'Open camera scanner';
            }

            cameraToggle.addEventListener('click', function () {
                if (scanner) {
                    stopCamera();
                    return;
                }

                errorEl.classList.add('hidden');
                readerEl.classList.remove('hidden');
                cameraToggleLabel.textContent = 'Close camera scanner';

                scanner = new Html5Qrcode('reader');
                scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (decodedText) => {
                    const tracking = extractTracking(decodedText) || decodedText.trim();
                    stopCamera();
                    if (tracking) openDocument(tracking);
                }, () => {}).catch(() => {
                    stopCamera();
                    showError('Could not start the camera. Check permissions, or upload a QR image instead.');
                });
            });
        })();
    </script>

    {{-- html5-qrcode's scanFile needs a container element to exist in the DOM. --}}
    <div id="qr-file-reader-temp" class="hidden"></div>
</x-app-layout>
