<x-app-layout>
    {{-- Shown only when no documents are currently in progress; otherwise
         /track redirects straight to the latest in-progress document. --}}
    <div class="mx-auto w-full max-w-lg py-6">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-8 text-center shadow-lg shadow-gray-200/60 ring-1 ring-gray-100">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
            </div>
            <p class="mt-3 font-semibold text-gray-700">No documents in progress</p>
            <p class="mt-1 text-sm text-gray-500">Documents currently being processed will show up here automatically.</p>

            <form method="GET" action="{{ route('track.index') }}" class="mt-6 space-y-3 border-t border-gray-100 pt-6">
                <p class="text-sm text-gray-600">Or look up any document by tracking number</p>
                <input id="trackingInput" name="tracking_number" class="w-full rounded-xl border border-gray-200 px-4 py-3 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="SPD-YYYYMMDD-XXXXXX">
                <button type="submit" class="w-full rounded-xl bg-emerald-800 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Search
                </button>
            </form>

            {{-- Upload-to-scan: decode a saved QR image client-side, then jump to the document. --}}
            <div class="mt-4 border-t border-gray-100 pt-6">
                <p class="text-sm text-gray-600">Have your QR code saved as an image?</p>
                <label for="qrFile" class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/50 py-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span id="qrFileLabel">Upload QR code image</span>
                </label>
                <input id="qrFile" type="file" accept="image/*" class="hidden">
                <p id="qrError" class="mt-2 hidden text-sm font-semibold text-red-600"></p>
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
                        window.location.href = `${trackBase}/${encodeURIComponent(tracking)}`;
                    })
                    .catch(() => {
                        fileLabel.textContent = 'Upload QR code image';
                        showError('Could not read a QR code from that image. Try a clearer photo.');
                    })
                    .finally(() => {
                        fileInput.value = '';
                    });
            });
        })();
    </script>

    {{-- html5-qrcode's scanFile needs a container element to exist in the DOM. --}}
    <div id="qr-file-reader-temp" class="hidden"></div>
</x-app-layout>
