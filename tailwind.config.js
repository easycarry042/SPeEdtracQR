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

// Civic neutral ramp — replaces Tailwind's cool stock gray with green-tinted
// neutrals derived from the Civic Record tokens (paper / hairline / ink), so
// the ~400 legacy `gray-*` classes scattered through the views converge on the
// design system without a mass find-and-replace. Text steps are contrast-checked
// against white (400 ≈ 4.95:1) so muted copy still passes WCAG AA.
const civicNeutral = {
    50: '#f6f9f7',
    100: '#eef4f0',
    200: '#e6ece8', // --hairline
    300: '#cdd9d2', // --hairline-strong
    400: '#657468', // muted text (AA on white)
    500: '#5b6b62', // --ink-soft
    600: '#4a594f',
    700: '#374139',
    800: '#232e28',
    900: '#16211b', // --ink
    950: '#0d1410',
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
                // Base — white cards on a subtly tinted canvas
                paper: 'var(--paper)',
                canvas: 'var(--canvas)',
                hairline: {
                    DEFAULT: 'var(--hairline)',
                    strong: 'var(--hairline-strong)',
                },
                // Municipal green — authority
                green: {
                    // Numeric ramp so legacy `green-100/500/700` classes render
                    // municipal green instead of Tailwind's stock mint, plus the
                    // semantic aliases for new work.
                    ...municipalGreen,
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
                // Stock cool gray → green-tinted civic neutrals (single source of truth).
                gray: civicNeutral,
            },
        },
    },

    plugins: [forms],
};
