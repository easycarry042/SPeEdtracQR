{{-- Shared QR scanning helpers: html5-qrcode CDN + tracking-number extraction
     + a single-camera lifecycle. Included once per page that scans QRs
     (Look up hub, custody strip) so the SPD- pattern can never drift. --}}
@once
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    window.SpeedQr = (function () {
        // Tracking number format: SPD-YYYYMMDD-XXXXXX (6 base32 chars).
        const TRACKING_RE = /SPD-\d{8}-[0-9A-Z]{6}/i;

        function extractTracking(text) {
            const match = (text || '').match(TRACKING_RE);
            return match ? match[0].toUpperCase() : null;
        }

        let scanner = null;

        /** Start the live camera in #elementId; onDecode(decodedText) fires per frame. */
        function start(elementId, onDecode, onError) {
            stop();
            scanner = new Html5Qrcode(elementId);
            return scanner
                .start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, onDecode, () => {})
                .catch((e) => { scanner = null; if (onError) onError(e); });
        }

        function stop() {
            if (!scanner) return;
            scanner.stop().catch(() => {});
            scanner = null;
        }

        /** Non-prompting camera check: resolves true when a videoinput exists. */
        function hasCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return Promise.resolve(false);
            return navigator.mediaDevices.enumerateDevices()
                .then((devices) => devices.some((d) => d.kind === 'videoinput'))
                .catch(() => false);
        }

        return { TRACKING_RE, extractTracking, start, stop, hasCamera };
    })();
</script>
@endonce
