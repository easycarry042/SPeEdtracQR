<x-app-layout>
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 p-4 text-sm text-emerald-950 shadow-sm">
            <p class="font-semibold">Look up a document</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-emerald-900/90">
                <li>Scan the QR on the folder, or type the tracking number, to open the document.</li>
                <li>Scanning is for <strong>identification only</strong> — it no longer changes a document's status.</li>
                <li>To move a document forward, open it and use the <strong>Status Progress</strong> controls (assigned staff / admin).</li>
            </ul>
        </div>

        <div class="rounded-xl border border-[#e6ece8] bg-white p-5">
            <div id="reader" class="overflow-hidden rounded-lg border border-gray-300"></div>

            <div class="mt-4 flex gap-2">
                <input id="manualTracking" placeholder="SPD-YYYYMMDD-XXXXXX"
                       class="flex-1 rounded-lg border border-gray-300 px-3 py-2 font-mono tracking-widest">
                <button id="manualSubmit" class="rounded-lg bg-[#0f4d28] px-4 py-2 font-bold text-white">Open</button>
            </div>

            <div id="result" class="mt-3 hidden">
                <div id="resultInner" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900 shadow-sm">
                    <span id="resultText" class="flex-1 leading-snug"></span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const trackBase = @json(url('/track'));

        function normalizeTracking(decodedText) {
            if (decodedText.includes('/track/')) return decodedText.split('/track/').pop();
            return decodedText.trim();
        }

        function openDocument(trackingNumber) {
            const value = (trackingNumber || '').trim();
            if (!value) return;
            window.location.href = `${trackBase}/${encodeURIComponent(value)}`;
        }

        document.getElementById('manualSubmit').addEventListener('click', () => {
            openDocument(document.getElementById('manualTracking').value);
        });
        document.getElementById('manualTracking').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') openDocument(e.target.value);
        });

        const scanner = new Html5Qrcode('reader');
        scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 280 }, (decodedText) => {
            scanner.stop().catch(() => {});
            openDocument(normalizeTracking(decodedText));
        }, () => {});
    </script>
</x-app-layout>
