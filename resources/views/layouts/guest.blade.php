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

                /* The frame's two "welcome light" glows over the arrow doodle at
                   15%. They are single-stop radial gradients in the file, so CSS
                   reproduces them exactly and scales to any viewport — a pair of
                   fixed 1156px SVGs would not. */
                background-color: #ffffff;
                background-image:
                    radial-gradient(circle 578px at 26.9% 71px, rgba(132, 255, 94, .5), rgba(104, 244, 61, 0) 100%),
                    radial-gradient(circle 578px at 73.1% -88px, rgba(1, 114, 26, .5), rgba(1, 114, 26, 0) 100%),
                    linear-gradient(rgba(255, 255, 255, .85), rgba(255, 255, 255, .85)),
                    url('{{ asset('images/landing/doodle-pattern.jpg') }}');
                background-size: cover, cover, cover, cover;
                background-position: center, center, center, center;
                background-attachment: fixed, fixed, fixed, fixed;
                background-repeat: no-repeat, no-repeat, no-repeat, no-repeat;
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
                width: min(1100px, 100%);
                border-radius: 50px;
                overflow: hidden;
                /* Lime glass over the glows, rather than the neutral white pane —
                   the card belongs to the same family as the portal's cards. */
                background: rgba(141, 255, 60, .2);
                border: .5px solid rgba(0, 64, 4, .5);
                box-shadow: none;
            }

            .auth-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                /* Holds the card at the height it had before the guest button
                   and reset row were removed, so the brand half keeps its
                   proportions. Only while side-by-side — the stacked layout
                   below 900px sizes to its content. */
                min-height: 700px;
            }

            .auth-left {
                padding: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                /* A hairline rule, not a panel edge: the two halves are one
                   card split down the middle. */
                border-right: 2px solid rgba(0, 0, 0, .2);
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
                font-size: 45px;
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
                color: #1e1e1e;
                font-size: 18px;
                font-weight: 500;
            }

            .auth-right {
                /* 56px gutters hold the fields at the frame's 450px width inside
                   the card's right half, instead of running edge to edge. */
                padding: 28px 56px;
                /* Centred in the taller cell, rather than sitting at the top
                   with the reclaimed space dangling below the Login button. */
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .auth-heading {
                margin: 0;
                font-size: 40px;
                line-height: 1;
                font-weight: 800;
                color: #004004;
            }

            .auth-subheading {
                margin-top: 16px;
                font-size: 20px;
                color: #1e1e1e;
                font-weight: 600;
            }

            .auth-form {
                margin-top: 20px;
            }

            .form-group { margin-bottom: 22px; }

            .form-label {
                display: block;
                font-size: 20px;
                font-weight: 600;
                color: #004004;
                margin-bottom: 8px;
                letter-spacing: .01em;
            }

            /* Pill fields tinted to the card, with no hard border — the fill is
               what marks the input, so the rows read as one soft stack. */
            .form-input {
                width: 100%;
                min-height: 80px;
                border-radius: 60px;
                border: 1px solid transparent;
                background: rgba(1, 114, 26, .3);
                padding: 13px 34px;
                font-size: 20px;
                font-weight: 600;
                color: #004004;
                outline: none;
                transition: background .15s, border-color .15s, box-shadow .15s;
            }

            .form-input::placeholder {
                color: rgba(0, 64, 4, .5);
                font-weight: 600;
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
                left: 34px;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                color: var(--text-main);
                pointer-events: none;
            }

            .form-input-with-icon {
                padding-left: 92px;
            }

            /* Clears the reveal icon parked at the field's right edge. */
            .form-input-with-toggle {
                padding-right: 72px;
            }

            /* Replaces the margin the show-password / reset row used to add. */
            .auth-button-spaced {
                margin-top: 41px;
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
                min-height: 80px;
                border: 0;
                border-radius: 60px;
                background: #01721a;
                color: #fff;
                padding: 15px 16px;
                font-size: 20px;
                font-weight: 900;
                cursor: pointer;
                box-shadow: none;
                transition: background 0.15s;
            }

            .auth-button:hover { background: #004004; }

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
