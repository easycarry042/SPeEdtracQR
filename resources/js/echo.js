import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

/**
 * Release the pages that were waiting for this file.
 *
 * @vite() emits this bundle as <script type="module">, which the browser
 * defers until the document has been parsed — so every inline <script> in a
 * page body runs BEFORE this file does. A page that subscribed behind a plain
 * `if (window.Echo)` therefore tested an undefined global, skipped silently,
 * and never received a single live update. Pages call window.whenEcho()
 * (defined in the layout <head>, so it exists before any of this) and are
 * woken here.
 */
window.dispatchEvent(new Event('echo:ready'));
