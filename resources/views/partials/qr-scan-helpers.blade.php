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

        /** Where an internal request's QR link points (route internal.track). */
        const INTERNAL_TRACKER_PATH = '/internal-requests/track';

        /** Signed request QRs (see App\Support\TrackingQr). */
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

            // An internal request's QR: a link to our internal tracker carrying
            // the signed payload (App\Support\TrackingQr::content()).
            if (url.pathname.replace(/\/+$/, '').endsWith(INTERNAL_TRACKER_PATH)) {
                const signed = (url.searchParams.get('qr') || '').toUpperCase();

                return signed.startsWith(SIGNED_PREFIX) ? decodeSigned(signed) : null;
            }

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

        /**
         * Read a QR from a picture instead of the camera — the QR image of a
         * soft-copy request, dropped onto the page or chosen from disk. Uses a
         * hidden reader of its own, so it never disturbs a running camera.
         *
         * @returns {Promise<string>} the decoded text
         */
        function decodeImage(file) {
            let box = document.getElementById('speedqr-image-reader');
            if (! box) {
                box = document.createElement('div');
                box.id = 'speedqr-image-reader';
                box.hidden = true;
                document.body.appendChild(box);
            }

            const reader = new Html5Qrcode(box.id, scannerOptions());

            return reader.scanFile(file, false)
                .catch(() => {
                    throw new Error('No QR code could be read from that picture. Use a clear, straight-on picture of the request\'s QR.');
                })
                .finally(() => {
                    try { reader.clear(); } catch (e) { /* nothing was drawn */ }
                });
        }

        /**
         * The picture in a drop: an image file dragged in from the desktop or
         * the downloads bar, or an image dragged from this page itself (the
         * browser hands over its URL, which must be one of ours).
         *
         * @returns {Promise<File>}
         */
        async function imageFromDrop(dataTransfer) {
            const files = [...(dataTransfer?.files || [])];
            const image = files.find((file) => file.type.startsWith('image/'));
            if (image) return image;
            if (files.length) {
                throw new Error('That file is not a picture. Drop the QR image (PNG or JPG) — a PDF can\'t be read here.');
            }

            const link = (dataTransfer?.getData('text/uri-list') || dataTransfer?.getData('text/plain') || '')
                .split(/\r?\n/).find((line) => line && ! line.startsWith('#'));
            if (! link) throw new Error('Drop the request\'s QR image here.');

            const url = new URL(link.trim(), window.location.href);
            if (url.origin !== window.location.origin) {
                throw new Error('That picture is from another site. Use this request\'s own QR image.');
            }

            const response = await fetch(url, { credentials: 'same-origin' });
            if (! response.ok) throw new Error('That picture could not be opened.');
            const blob = await response.blob();

            return new File([blob], 'qr-image', { type: blob.type || 'image/png' });
        }

        // ─── Snip a QR off the screen ───────────────────────────────────────
        // Like a browser authenticator's "scan QR code": the page dims, the
        // pointer becomes a crosshair, and the box dragged around a QR is read
        // as if it had been scanned.

        /**
         * Dim the page and let the user drag a box. Resolves with the box in
         * viewport pixels, or null on Esc / a click without a drag.
         *
         * @param {HTMLCanvasElement|null} frame — show this captured frame
         *        instead of the live page (a capture of another window)
         */
        function selectRegion(frame = null) {
            return new Promise((resolve) => {
                const layer = document.createElement('div');
                layer.className = 'qr-snip';
                layer.setAttribute('role', 'dialog');
                layer.setAttribute('aria-label', 'Select the QR code: drag a box around it, or press Escape to cancel');
                if (frame) {
                    frame.className = 'qr-snip-frame';
                    layer.appendChild(frame);
                }
                const hint = document.createElement('p');
                hint.className = 'qr-snip-hint';
                hint.textContent = 'Drag a box around the QR code · Esc to cancel';
                const box = document.createElement('div');
                box.className = 'qr-snip-box';
                box.hidden = true;
                layer.append(hint, box);
                document.body.appendChild(layer);

                // Fit a captured frame to the window, and remember where it sits
                // so the box can be mapped back onto the frame's own pixels.
                let shown = null;
                if (frame) {
                    const k = Math.min(window.innerWidth / frame.width, window.innerHeight / frame.height);
                    Object.assign(frame.style, { width: frame.width * k + 'px', height: frame.height * k + 'px' });
                    shown = frame.getBoundingClientRect();
                }

                let start = null;
                const rectFrom = (event) => {
                    const left = Math.min(start.x, event.clientX);
                    const top = Math.min(start.y, event.clientY);

                    return { left, top, width: Math.abs(event.clientX - start.x), height: Math.abs(event.clientY - start.y) };
                };
                const finish = (rect) => {
                    window.removeEventListener('keydown', onKey, true);
                    layer.remove();
                    resolve(rect);
                };
                const onKey = (event) => {
                    if (event.key === 'Escape') {
                        // Ours alone: the modal underneath must not close too.
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        finish(null);
                    }
                };

                window.addEventListener('keydown', onKey, true);
                layer.addEventListener('pointerdown', (event) => {
                    event.preventDefault();
                    layer.setPointerCapture(event.pointerId);
                    start = { x: event.clientX, y: event.clientY };
                    hint.hidden = true;
                });
                layer.addEventListener('pointermove', (event) => {
                    if (! start) return;
                    const r = rectFrom(event);
                    Object.assign(box.style, { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' });
                    box.hidden = false;
                });
                layer.addEventListener('pointerup', (event) => {
                    if (! start) return;
                    const r = rectFrom(event);
                    r.shown = shown;
                    finish(r.width >= 12 && r.height >= 12 ? r : null);
                });
            });
        }

        /** A canvas for the box, with a white margin: decoders need a quiet zone round a QR. */
        function paddedCanvas(width, height, scale) {
            const pad = Math.round(Math.max(width, height) * scale * 0.12);
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(width * scale) + pad * 2;
            canvas.height = Math.round(height * scale) + pad * 2;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            return { canvas, ctx, pad };
        }

        function canvasToFile(canvas) {
            return new Promise((resolve, reject) => {
                try {
                    canvas.toBlob((blob) => (blob ? resolve(new File([blob], 'qr-snip.png', { type: 'image/png' })) : reject(new Error('empty'))), 'image/png');
                } catch (e) {
                    reject(e);
                }
            });
        }

        /**
         * The box, rebuilt from the pictures on this page that it covers — the
         * QR viewer, a QR-stamped scan, an inline QR. No permission needed.
         * Null when no picture lies under the box.
         */
        async function regionFromPage(rect) {
            const scale = Math.max(2, window.devicePixelRatio || 1);
            const { canvas, ctx, pad } = paddedCanvas(rect.width, rect.height, scale);
            let drew = false;

            const sources = [...document.querySelectorAll('img, svg, canvas')].filter((el) => ! el.closest('.qr-snip'));
            for (const el of sources) {
                const r = el.getBoundingClientRect();
                const left = Math.max(r.left, rect.left);
                const top = Math.max(r.top, rect.top);
                const right = Math.min(r.right, rect.left + rect.width);
                const bottom = Math.min(r.bottom, rect.top + rect.height);
                if (right <= left || bottom <= top || r.width < 8 || r.height < 8) continue;
                if (getComputedStyle(el).visibility === 'hidden') continue;

                let source = el;
                let naturalWidth = el.naturalWidth || el.width;
                let naturalHeight = el.naturalHeight || el.height;

                if (el instanceof SVGElement) {
                    // Inline SVG (some pages draw the QR this way): rasterise it.
                    if (! el.querySelector('path, rect')) continue;
                    const svg = new Blob([new XMLSerializer().serializeToString(el)], { type: 'image/svg+xml' });
                    source = new Image();
                    source.src = URL.createObjectURL(svg);
                    try { await source.decode(); } catch (e) { continue; }
                    naturalWidth = r.width * scale;
                    naturalHeight = r.height * scale;
                } else if (el instanceof HTMLImageElement && (! el.complete || ! el.naturalWidth)) {
                    continue;
                }

                const sx = (left - r.left) * naturalWidth / r.width;
                const sy = (top - r.top) * naturalHeight / r.height;
                const sw = (right - left) * naturalWidth / r.width;
                const sh = (bottom - top) * naturalHeight / r.height;
                try {
                    ctx.drawImage(source, sx, sy, sw, sh,
                        pad + (left - rect.left) * scale, pad + (top - rect.top) * scale,
                        (right - left) * scale, (bottom - top) * scale);
                    drew = true;
                } catch (e) { /* a picture from another site — skip it */ }
            }

            if (! drew) return null;
            try {
                return await canvasToFile(canvas);
            } catch (e) {
                return null;
            }
        }

        /** One still frame of a screen capture (the browser asks which tab / window first). */
        async function captureFrame(preferCurrentTab) {
            if (! navigator.mediaDevices?.getDisplayMedia) {
                throw new Error('This browser cannot capture the screen. Open the QR on this page, or scan it with the camera.');
            }

            let stream;
            try {
                stream = await navigator.mediaDevices.getDisplayMedia({
                    video: { frameRate: 5 },
                    audio: false,
                    preferCurrentTab,
                    selfBrowserSurface: 'include',
                });
            } catch (e) {
                throw new Error('Screen capture was not allowed, so the QR could not be read.');
            }

            try {
                const video = document.createElement('video');
                video.muted = true;
                video.srcObject = stream;
                await video.play();
                await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
                const frame = document.createElement('canvas');
                frame.width = video.videoWidth;
                frame.height = video.videoHeight;
                frame.getContext('2d').drawImage(video, 0, 0);

                return frame;
            } finally {
                stream.getTracks().forEach((track) => track.stop());
            }
        }

        /** Crop the box out of a frame shown at `shown` (its on-screen rect). */
        function cropFrame(frame, rect, shown) {
            const kx = frame.width / shown.width;
            const ky = frame.height / shown.height;
            const { canvas, ctx, pad } = paddedCanvas(rect.width * kx, rect.height * ky, 1);
            ctx.drawImage(frame,
                (rect.left - shown.left) * kx, (rect.top - shown.top) * ky, rect.width * kx, rect.height * ky,
                pad, pad, rect.width * kx, rect.height * ky);

            return canvasToFile(canvas);
        }

        /**
         * Snip a QR off the screen and decode it.
         *
         * 'page' (default): box drawn over this page; read from its pictures,
         * no permission prompt — the QR has to be a picture on this page (the
         * request's QR viewer is).
         * 'any': capture a tab, window or screen first, then draw the box on
         * that frozen frame — for a QR open somewhere else.
         *
         * @returns {Promise<string|null>} decoded text, or null if cancelled
         */
        async function scanFromScreen(mode = 'page') {
            if (mode === 'any') {
                const frame = await captureFrame(false);
                const rect = await selectRegion(frame);
                if (! rect) return null;

                return decodeImage(await cropFrame(frame, rect, rect.shown));
            }

            const rect = await selectRegion();
            if (! rect) return null;

            // Read from the page's own pictures only. Falling back to a screen
            // capture here sprang a browser permission prompt on anyone who
            // just missed the QR; a QR in another window is mode 'any'.
            const fromPage = await regionFromPage(rect);
            if (! fromPage) {
                throw new Error('No QR code was found in that box. Drag it around the whole QR — or, if the QR is open in another window, use that option instead.');
            }

            try {
                return await decodeImage(fromPage);
            } catch (e) {
                throw new Error('No QR code could be read in that box. Drag it around the whole QR, corners included.');
            }
        }

        return { TRACKING_RE, FOREIGN_CODE_MESSAGE, extractTracking, isStaffBadge, describe, start, stop, hasCamera, decodeImage, imageFromDrop, scanFromScreen };
    })();
</script>
@endonce
