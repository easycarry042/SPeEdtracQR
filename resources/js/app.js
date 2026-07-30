import './bootstrap';
import './echo';

import Alpine from 'alpinejs';

// QR scanning library, bundled rather than pulled from a CDN so the scanner also
// works on an offline/locked-down office LAN. Exposed globally for the Blade
// scan helpers (see partials/qr-scan-helpers.blade.php).
import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;

window.Alpine = Alpine;

Alpine.start();

// ── Back/forward cache (bfcache) guard ──────────────────────────────────────
// Loaded on every page via @vite, so it covers standalone pages (the public
// Submit-a-Request page, the landing page) that don't share a layout. Modern
// Chrome will place even `Cache-Control: no-store` pages into the bfcache and
// restore them on Back/Forward WITHOUT contacting the server — which is how a
// protected dashboard could reappear after logout, or a public page could
// linger after login. `pageshow` with `event.persisted === true` means the page
// was restored from history; reloading forces a real request so the server's
// auth + guest/authed redirects run again.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.location.reload();
    }
});
