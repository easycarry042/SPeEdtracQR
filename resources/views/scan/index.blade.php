<x-app-layout>
    <div class="mx-auto w-full max-w-3xl">
        <div class="mb-6 rounded-[10px] border border-hairline bg-green-wash/50 p-4 text-sm text-ink">
            <p class="font-semibold text-green-deep">Look up a document</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-ink-soft">
                <li>Scan the QR on the folder, or type the tracking number, to open the document.</li>
                <li>Scanning is for <strong class="text-ink">identification only</strong> — it no longer changes a document's status.</li>
                <li>To move a document forward, open it and use the <strong class="text-ink">Status Progress</strong> controls (assigned staff / admin).</li>
            </ul>
        </div>

        <div class="panel p-5">
            <div id="reader" class="min-h-[280px] overflow-hidden rounded-lg border border-hairline-strong"></div>

            <div class="mt-4 scan-manual">
                <input id="manualTracking" placeholder="SPD-YYYYMMDD-XXXXXX"
                       class="flex-1 rounded-lg border border-hairline-strong px-3 py-2 font-mono tracking-widest text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                <button id="manualSubmit" class="cr-btn cr-btn-primary !px-4 !py-2 !text-sm">Open</button>
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
