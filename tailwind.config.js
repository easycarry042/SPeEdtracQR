import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * "Civic Record" palette. Semantic tokens map to the CSS variables in
 * resources/css/app.css (single source of truth). The built-in `emerald` and
 * `teal` ramps are remapped onto the true municipal green so the legacy
 * mint/teal classes already scattered through the views shift over without a
 * 500-line find-and-replace. New work should prefer the semantic names
 * (green-deep, green, ink, hairline, …).
 *
 * @type {import('tailwindcss').Config}
 */

// True deep-green ramp (replaces the old pale mint emerald/teal).
const municipalGreen = {
    50: '#f3f8f5',
    100: '#eaf4ee', // --green-wash
    200: '#cfe6d8',
    300: '#a9d2ba',
    400: '#4fae6f',
    500: '#2a9d4f', // --green-bright
    600: '#167a3a', // --green
    700: '#136a33',
    800: '#10592c',
    900: '#0f4d28', // --green-deep
    950: '#0a3a1e',
};

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Base — white = purity
                paper: 'var(--paper)',
                hairline: {
                    DEFAULT: 'var(--hairline)',
                    strong: 'var(--hairline-strong)',
                },
                // Municipal green — authority
                green: {
                    deep: 'var(--green-deep)',
                    DEFAULT: 'var(--green)',
                    bright: 'var(--green-bright)',
                    wash: 'var(--green-wash)',
                },
                // Text
                ink: {
                    DEFAULT: 'var(--ink)',
                    soft: 'var(--ink-soft)',
                },
                'on-green': {
                    DEFAULT: 'var(--on-green)',
                    soft: 'var(--on-green-soft)',
                },
                // Civic accent
                brass: 'var(--brass)',
                // Functional status washes (meaning only, never decorative)
                'status-amber': { DEFAULT: 'var(--amber)', wash: 'var(--amber-wash)' },
                'status-red': { DEFAULT: 'var(--red)', wash: 'var(--red-wash)' },

                // Legacy ramps remapped onto the municipal green.
                emerald: municipalGreen,
                teal: municipalGreen,
            },
        },
    },

    plugins: [forms],
};
