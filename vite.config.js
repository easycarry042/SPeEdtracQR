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
});
