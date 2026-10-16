import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // The PDF entries are separate: pdf.js + pdf-lib are heavy, and only
            // the pages that read or write PDFs need them — the rest of the app
            // must not pay for it.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pdf-editor.js',
                'resources/js/pdf-scan-preview.js',
                'resources/js/pdf-qr-stamp.js',
            ],
            refresh: true,
        }),
    ],
    // Pin the dev server to one address. Left to itself, Vite binds whatever
    // "localhost" resolves to — on Windows that is often IPv6, so the hot file
    // says http://[::1]:5173, which the CSP (SecurityHeaders) does not expect,
    // and the whole site renders as unstyled HTML. strictPort makes a busy
    // port fail loudly instead of drifting to 5174 for the same reason.
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        hmr: { host: '127.0.0.1' },
    },
});
