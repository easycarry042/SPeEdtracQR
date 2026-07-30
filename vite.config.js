import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // pdf-editor is its own entry: pdf.js + pdf-lib are heavy, and only
            // the editor page needs them — the rest of the app must not pay for it.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pdf-editor.js'],
            refresh: true,
        }),
    ],
});
