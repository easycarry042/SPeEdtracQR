import './bootstrap';
import './echo';

import Alpine from 'alpinejs';

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
