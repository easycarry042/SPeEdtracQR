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

        /** Signed request QRs (see App\Support\TrackingQr) — never a URL. */
        const SIGNED_PREFIX = 'SPDQR1:';
        const SIGNATURE_BYTES = 8;
        const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        /**
         * The tracking number inside a signed request QR, or null. Only the
         * server holds the key, so the signature is checked there (custody
         * scans post the raw payload); a forged code here can at most open a
         * tracking page anyone could type the number into anyway.
         */
        function decodeSigned(raw) {
            const body = raw.slice(SIGNED_PREFIX.length);
            if (!/^[A-Z2-7]+$/.test(body)) return null;

            let bits = '';
            for (const ch of body) bits += BASE32.indexOf(ch).toString(2).padStart(5, '0');

            let bytes = '';
            for (let i = 0; i + 8 <= bits.length; i += 8) {
                bytes += String.fromCharCode(parseInt(bits.slice(i, i + 8), 2));
            }

            const tracking = bytes.slice(0, -SIGNATURE_BYTES);

            return bytes.length > SIGNATURE_BYTES && TRACKING_RE.test(tracking) ? tracking : null;
        }

        /**
         * Extract a tracking number, but ONLY from a code this system issued:
         * a signed request QR (what every new code carries), one of our own
         * tracking URLs (same origin), or a bare tracking number — the last two
         * keep stickers printed before signed QRs working. A foreign QR — a
         * Wi-Fi code, a payment code, a link to another site, or text that
         * merely mentions a tracking number — returns null so callers reject it
         * instead of acting on someone else's code.
         */
        function extractTracking(text) {
            const raw = (text || '').trim();
            if (!raw) return null;

            if (raw.toUpperCase().startsWith(SIGNED_PREFIX)) return decodeSigned(raw.toUpperCase());

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

        /**
         * Serializes every start/stop. Releasing a camera is asynchronous, so
         * without this a second scan opens a new stream while the previous one
         * is still shutting down and the browser refuses it — the scanner looks
         * dead even though the permission is granted.
         */
        let queue = Promise.resolve();

        /**
         * html5-qrcode measures its container when it starts. Callers usually
         * reveal the box with x-show and call start() in the same tick, so the
         * div can still be display:none at that moment — wait for it to actually
         * be laid out.
         *
         * Readiness is WIDTH plus a layout box, never height: these containers are
         * empty divs, and an empty div is 0px tall however visible it is. Its
         * height only appears once html5-qrcode injects the video — so waiting on
         * height deadlocked every scanner until the timeout fired.
         */
        function waitForBox(elementId, timeoutMs = 3000) {
            const startedAt = Date.now();

            return new Promise((resolve, reject) => {
                (function check() {
                    const element = document.getElementById(elementId);

                    if (! element) {
                        reject(new Error('The scanner box is not on the page.'));

                        return;
                    }

                    // getClientRects() is empty for display:none (and for any
                    // hidden ancestor), but works for position:fixed panels,
                    // where offsetParent would be null.
                    if (element.getClientRects().length > 0 && element.offsetWidth > 0) {
                        resolve(element);

                        return;
                    }

                    if (Date.now() - startedAt > timeoutMs) {
                        reject(new Error('The scanner area stayed hidden, so the camera could not be sized. Close and reopen the scanner, or reload the page.'));

                        return;
                    }

                    requestAnimationFrame(check);
                })();
            });
        }

        /** Square viewfinder that always fits — a fixed qrbox throws on small boxes. */
        function qrbox(viewfinderWidth, viewfinderHeight) {
            const side = Math.max(120, Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.7));

            return { width: side, height: side };
        }

        /**
         * Ask for HD frames. Without this the browser hands over its default
         * (often 640x480), and a fixed-focus laptop webcam then sees a QR held
         * at arm's length as too few pixels to decode.
         */
        const HD = { width: { ideal: 1280 }, height: { ideal: 720 } };

        function scanConfig(camera) {
            return { fps: 10, qrbox, videoConstraints: Object.assign({}, camera, HD) };
        }

        /**
         * QR only, and the browser's native BarcodeDetector where it exists
         * (Chrome/Edge): it reads blurry, low-contrast webcam frames far more
         * reliably than the bundled JavaScript decoder, which stays the fallback.
         */
        function scannerOptions() {
            const formats = window.Html5QrcodeSupportedFormats;

            return {
                verbose: false,
                formatsToSupport: formats ? [formats.QR_CODE] : undefined,
                useBarCodeDetectorIfSupported: true,
            };
        }

        /** Prefer the rear camera; fall back to whatever this machine has. */
        function launch(elementId, onDecode) {
            scanner = new Html5Qrcode(elementId, scannerOptions());

            return scanner
                .start({ facingMode: 'environment' }, scanConfig({ facingMode: 'environment' }), onDecode, () => {})
                .catch((error) => {
                    // Desktops with only a built-in webcam reject "environment"
                    // outright (OverconstrainedError), so retry by device id.
                    return Html5Qrcode.getCameras().then((cameras) => {
                        if (! cameras || cameras.length === 0) {
                            throw error;
                        }

                        const camera = { deviceId: { exact: cameras[0].id } };

                        return scanner.start(camera, scanConfig(camera), onDecode, () => {});
                    });
                });
        }

        /**
         * Turn a camera failure into something a clerk can act on. "Check your
         * permissions" is useless when permission was never the problem — and
         * the most common cause in an office is exactly that: the page is served
         * over plain http:// on a LAN address, where browsers disable the camera
         * outright and the site permission toggle has no effect.
         */
        function describe(error) {
            const name = (error && error.name) || '';

            if (! window.isSecureContext || ! navigator.mediaDevices) {
                return 'Your browser blocks the camera on this address (' + window.location.origin + '). '
                    + 'Cameras only work over https:// or on http://localhost — allowing the permission cannot override this. '
                    + 'Open the site over HTTPS, or scan the QR from the machine running the server.';
            }

            if (name === 'NotAllowedError' || name === 'SecurityError') {
                return 'The browser is still blocking the camera for this site. Open the padlock/camera icon in the address bar, set Camera to Allow, then reload the page.';
            }

            if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || name === 'OverconstrainedError') {
                return 'No camera was found on this device. Use a phone or a machine with a webcam, or record it manually.';
            }

            if (name === 'NotReadableError' || name === 'TrackStartError') {
                return 'Another app or browser tab is already using the camera. Close it (Zoom, Meet, another scanner tab) and try again.';
            }

            return (error && error.message) ? error.message : 'The camera could not be started.';
        }

        /**
         * A .qr-surface (see .qr-viewport in app.css) is laid out at a real
         * decoding size and only drawn small. Its width is set before the camera
         * starts, because html5-qrcode reads it then to size the video: 1280px
         * for a landscape feed, 720px where the screen — and so usually the
         * phone camera's picture — is portrait.
         */
        function prepareSurface(elementId) {
            const element = document.getElementById(elementId);

            if (! element || ! element.classList.contains('qr-surface')) {
                return;
            }

            element.classList.remove('is-fitted');
            element.style.transform = '';
            element.style.width = (window.innerHeight > window.innerWidth ? 720 : 1280) + 'px';
        }

        let fitObserver = null;

        /**
         * Scale the surface down to cover its square window, centred. A
         * transform, never a width change: html5-qrcode measures clientWidth /
         * clientHeight, which a transform leaves alone, so its decoding stays
         * undistorted and full resolution while the feed fills the window.
         */
        function fitSurface(elementId) {
            const element = document.getElementById(elementId);

            if (! element || ! element.classList.contains('qr-surface')) {
                return;
            }

            const box = element.parentElement;

            const apply = () => {
                const video = element.querySelector('video');

                // Until the stream's metadata arrives the video is the
                // browser's default 150px tall, and a fit taken then zoomed the
                // feed in ~5×. Wait for the real picture size.
                if (! video || ! video.videoWidth || ! video.videoHeight) {
                    return false;
                }

                const width = element.offsetWidth;
                const height = video.offsetHeight;

                if (! width || ! height || ! box.clientWidth) {
                    return false;
                }

                const scale = Math.max(box.clientWidth / width, box.clientHeight / height);
                const x = (box.clientWidth - width * scale) / 2;
                const y = (box.clientHeight - height * scale) / 2;

                element.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
                element.classList.add('is-fitted');

                return true;
            };

            // The video only has a height once its metadata has arrived.
            const startedAt = Date.now();
            (function fitWhenReady() {
                if (! apply() && Date.now() - startedAt < 5000) {
                    requestAnimationFrame(fitWhenReady);
                }
            })();

            if (fitObserver) {
                fitObserver.disconnect();
            }

            // Refit when the window resizes, or when the video changes shape
            // (a phone rotating hands over a portrait picture).
            if (window.ResizeObserver) {
                fitObserver = new ResizeObserver(() => apply());
                fitObserver.observe(box);
                fitObserver.observe(element);
            }
        }

        /** Start the live camera in #elementId; onDecode(decodedText) fires per frame. */
        function start(elementId, onDecode, onError) {
            if (typeof Html5Qrcode === 'undefined') {
                if (onError) onError(new Error('Scanner library unavailable — run npm run build.'));

                return Promise.resolve();
            }

            // Fail fast and explicitly: on an insecure origin the camera API is
            // not merely blocked, it does not exist, and html5-qrcode's own error
            // for that is opaque.
            if (! window.isSecureContext || ! navigator.mediaDevices) {
                if (onError) onError(new DOMException('Camera unavailable on an insecure origin.', 'SecurityError'));

                return Promise.resolve();
            }

            queue = queue
                .then(() => stop())
                .then(() => prepareSurface(elementId))
                .then(() => waitForBox(elementId))
                .then(() => launch(elementId, onDecode))
                .then(() => fitSurface(elementId))
                .catch((e) => { scanner = null; if (onError) onError(e); });

            return queue;
        }

        /** Release the camera AND clear the rendered video, so a restart is clean. */
        function stop() {
            const active = scanner;
            scanner = null;

            if (fitObserver) {
                fitObserver.disconnect();
                fitObserver = null;
            }

            document.querySelectorAll('.qr-surface.is-fitted').forEach((surface) => {
                surface.classList.remove('is-fitted');
                surface.style.transform = '';
            });

            if (! active) {
                return Promise.resolve();
            }

            return active.stop()
                .then(() => { try { active.clear(); } catch (e) { /* already torn down */ } })
                .catch(() => {});
        }

        /** Non-prompting camera check: resolves true when a videoinput exists. */
        function hasCamera() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return Promise.resolve(false);
            return navigator.mediaDevices.enumerateDevices()
                .then((devices) => devices.some((d) => d.kind === 'videoinput'))
                .catch(() => false);
        }

        return { TRACKING_RE, FOREIGN_CODE_MESSAGE, extractTracking, isStaffBadge, describe, start, stop, hasCamera };
    })();
</script>
@endonce
