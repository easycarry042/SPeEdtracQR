{{-- Shared QR scanning helpers: tracking-number extraction + a single-camera
     lifecycle. Included once per page that scans QRs (Requests hub, custody
     strip) so the accepted-code rules can never drift between them.

     html5-qrcode is bundled through Vite (resources/js/app.js), not fetched from
     a CDN: a records office on a locked-down or offline LAN could not load the
     CDN copy, and the scanner then failed silently as "the QR won't read". --}}
@once
<script>
    window.SpeedQr = (function () {
        // Tracking numbers this system issues: SPD- (citizen) and INT- (internal
        // dept-to-dept), both PREFIX-YYYYMMDD-XXXXXX with 6 base32 characters.
        // INT- was missing here, so scanning an internal request's own sticker
        // was rejected as unreadable.
        const TRACKING_RE = /^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/i;

        /** Paths of ours that a QR may legitimately point at. */
        const OUR_PATHS = /^\/(track|verify)\/((?:SPD|INT)-\d{8}-[0-9A-Z]{6})(?:\/|$)/i;

        /**
         * Extract a tracking number, but ONLY from a code this system issued:
         * either one of our own tracking URLs (same origin) or a bare tracking
         * number. A foreign QR — a Wi-Fi code, a payment code, a link to another
         * site, or text that merely mentions a tracking number — returns null so
         * callers reject it instead of acting on someone else's code.
         */
        function extractTracking(text) {
            const raw = (text || '').trim();
            if (!raw) return null;

            // A bare tracking number (typed in, or a code encoding just the number).
            if (TRACKING_RE.test(raw)) return raw.toUpperCase();

            // Otherwise it must be one of OUR URLs.
            let url;
            try {
                url = new URL(raw, window.location.origin);
            } catch (e) {
                return null;
            }

            if (url.origin !== window.location.origin) return null;

            const match = url.pathname.match(OUR_PATHS);

            return match ? match[2].toUpperCase() : null;
        }

        /** Staff badge payloads (see App\Support\StaffBadge) — opaque, never a URL. */
        const STAFF_BADGE_RE = /^SPDSTAFF:[A-Za-z0-9]{16,40}$/;

        function isStaffBadge(text) {
            return STAFF_BADGE_RE.test((text || '').trim());
        }

        /** Human-readable rejection, for UIs that need to explain the refusal. */
        const FOREIGN_CODE_MESSAGE = 'That code was not issued by SPeED TraQR. Scan the QR printed on the claim slip or folder.';

        let scanner = null;

        /** Start the live camera in #elementId; onDecode(decodedText) fires per frame. */
        function start(elementId, onDecode, onError) {
            stop();
            if (typeof Html5Qrcode === 'undefined') {
                if (onError) onError(new Error('Scanner library unavailable — run npm run build.'));

                return Promise.resolve();
            }
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

        return { TRACKING_RE, FOREIGN_CODE_MESSAGE, extractTracking, isStaffBadge, start, stop, hasCamera };
    })();
</script>
@endonce
