<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Pages pass their own name (Login, Register, …); the brand leads so
             a row of pinned tabs stays identifiable. --}}
        <title>{{ config('app.name', 'SPeED TraQR') }}@isset($title) — {{ $title }}@endisset</title>

        <!-- Fonts (Zain + Nunito) are imported by app.css -->

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                /* Civic Record — white = purity, deep green = authority. */
                --page-bg: #f3f8f5;
                --card-bg: #ffffff;
                --text-main: #0f4d28;
                --text-sub: #51625a;
                --input-bg: #ffffff;
                --input-border: #cdd9d2;
                --accent: #167a3a;
                --accent-hover: #0f4d28;
                --brass: #c79a3e;
            }

            * { box-sizing: border-box; }

            body.auth-page {
                margin: 0;
                min-height: 100vh;
                font-family: Nunito, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                color: #1f2937;

                /* Shared public wash (--page-wash in app.css) over the arrow
                   doodle — the same treatment the citizen portal uses. */
                background-color: var(--page-wash-base);
                background-image:
                    var(--page-wash),
                    url('{{ asset('images/doodle-bg.png') }}');
                background-size: cover, cover;
                background-position: center, center;
                background-attachment: fixed, fixed;
                background-repeat: no-repeat, no-repeat;
            }

            main.auth-main {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            /* Frosted glass: the mesh behind shows through, blurred, so the
               card reads as a pane over the gradient rather than a flat block.
               Alpha stays high enough (.72) to keep form text legible. */
            /* Glass treatment comes from .glass-panel in app.css (background,
               blur, lit rim, shadow, and the no-backdrop-filter fallback). */
            .auth-card {
                width: min(880px, 100%);
                border-radius: 26px;
                overflow: hidden;
                /* Green-tinted glass over the wash, rather than the neutral
                   white pane — the card belongs to the same family as the
                   portal's cards. */
                background: rgba(198, 238, 213, .46);
                border-color: rgba(255, 255, 255, .55);
            }

            .auth-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                /* Holds the card at the height it had before the guest button
                   and reset row were removed, so the brand half keeps its
                   proportions. Only while side-by-side — the stacked layout
                   below 900px sizes to its content. */
                min-height: 508px;
            }

            .auth-left {
                padding: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                /* A hairline rule, not a panel edge: the two halves are one
                   card split down the middle. */
                border-right: 1px solid rgba(15, 77, 40, .18);
                background: transparent;
            }

            .auth-brand {
                text-align: center;
            }

            .brand-badge {
                width: 64px;
                height: 64px;
                margin: 0 auto 20px;
                border-radius: 50%;
                border: 2px solid var(--brass);
            }

            /* Wordmark: "SPeED" in Zain, "TraQR" in Nunito. */
            .brand-title {
                margin: 0;
                font-size: 36px;
                font-weight: 600;
                line-height: 1;
                color: var(--text-main);
                font-family: Zain, Nunito, ui-sans-serif, system-ui, sans-serif;
            }

            .brand-title span {
                color: var(--accent);
                font-family: Nunito, ui-sans-serif, system-ui, sans-serif;
            }

            .brand-subtitle {
                margin-top: 10px;
                color: var(--text-sub);
                font-size: 14px;
            }

            .auth-right {
                padding: 28px 32px;
                /* Centred in the taller cell, rather than sitting at the top
                   with the reclaimed space dangling below the Login button. */
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .auth-heading {
                margin: 0;
                font-size: 52px;
                line-height: 1;
                font-weight: 700;
                color: var(--text-main);
            }

            .auth-subheading {
                margin-top: 8px;
                font-size: 36px;
                color: var(--text-main);
                font-weight: 600;
            }

            .auth-form {
                margin-top: 20px;
            }

            .form-group { margin-bottom: 16px; }

            .form-label {
                display: block;
                font-size: 30px;
                font-weight: 700;
                color: var(--text-main);
                margin-bottom: 8px;
                letter-spacing: .01em;
            }

            /* Pill fields tinted to the card, with no hard border — the fill is
               what marks the input, so the rows read as one soft stack. */
            .form-input {
                width: 100%;
                border-radius: 999px;
                border: 1px solid transparent;
                background: rgba(151, 214, 175, .42);
                padding: 13px 18px;
                font-size: 16px;
                color: #16211b;
                outline: none;
                transition: background .15s, border-color .15s, box-shadow .15s;
            }

            .form-input::placeholder {
                color: rgba(22, 33, 27, .5);
            }

            .form-input:focus {
                background: rgba(255, 255, 255, .72);
                border-color: var(--accent);
                box-shadow: 0 0 0 3px rgba(22, 122, 58, .22);
            }

            /* Fields with a leading glyph. The icon is decorative — the label
               above already names the field — so it stays out of the a11y tree. */
            .field-shell { position: relative; }

            .field-icon {
                position: absolute;
                left: 18px;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                color: var(--text-main);
                pointer-events: none;
            }

            .form-input-with-icon {
                padding-left: 52px;
            }

            /* Clears the reveal icon parked at the field's right edge. */
            .form-input-with-toggle {
                padding-right: 48px;
            }

            /* Replaces the margin the show-password / reset row used to add. */
            .auth-button-spaced {
                margin-top: 20px;
            }

            .auth-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                margin: 10px 0 14px;
            }

            .checkbox-wrap {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--text-main);
                font-size: 30px;
            }

            .checkbox-wrap input { width: 20px; height: 20px; }

            .auth-link {
                color: var(--text-main);
                font-size: 30px;
                text-decoration: none;
            }

            .auth-link:hover { text-decoration: underline; }

            .auth-button {
                width: 100%;
                border: 0;
                border-radius: 999px;
                background: var(--text-main);
                color: #fff;
                padding: 15px 16px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 8px 20px -10px rgba(15, 77, 40, .8);
                transition: background 0.15s;
            }

            .auth-button:hover { background: var(--accent); }

            .switch-link {
                display: inline-block;
                margin-top: 16px;
                color: var(--text-sub);
                font-size: 22px;
                text-decoration: none;
            }

            .switch-link:hover { text-decoration: underline; }

            .auth-divider {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 12px 0 10px;
                color: var(--text-sub);
                font-size: 14px;
            }

            .auth-divider::before,
            .auth-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #e6ece8;
            }

            .citizen-button {
                display: block;
                width: 100%;
                border: 1px solid var(--accent);
                border-radius: 7px;
                background: transparent;
                color: var(--accent);
                padding: 10px 16px;
                font-size: 16px;
                font-weight: 600;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                transition: background 0.15s, color 0.15s;
            }

            .citizen-button:hover {
                background: var(--accent);
                color: #fff;
            }

            @media (max-width: 900px) {
                .auth-grid { grid-template-columns: 1fr; min-height: 0; }
                .auth-left {
                    border-right: 0;
                    border-bottom: 1px solid #d8e8c0;
                    padding: 30px 22px;
                }
                .auth-right { padding: 26px 20px; }
                .auth-heading { font-size: 42px; }
                .auth-subheading { font-size: 28px; }
                .form-label, .form-input, .checkbox-wrap, .auth-link, .auth-button { font-size: 22px; }
            }
        </style>
        @include('layouts.partials.accessibility-widget')
    </head>
    <body class="auth-page">
        <main class="auth-main">
            {{ $slot }}
        </main>
        @include('layouts.partials.bfcache-guard')
    </body>
</html>
