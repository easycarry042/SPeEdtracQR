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
            * { box-sizing: border-box; }

            body.auth-page {
                margin: 0;
                min-height: calc(100vh / var(--ui-zoom));
                font-family: Nunito, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                color: var(--ink);
                /* Plain, quiet canvas — no glows behind the card. */
                background: #f5f8f6;
            }

            main.auth-main {
                display: flex;
                min-height: calc(100vh / var(--ui-zoom));
                align-items: center;
                justify-content: center;
                padding: 32px 20px;
            }

            /* Card + the link above it share one column, so "Back to Homepage"
               lines up with the card's left edge at every width. */
            .auth-shell { width: min(1000px, 100%); }

            .auth-home-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 14px;
                padding: 6px 10px 6px 6px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 700;
                color: var(--green-deep);
                text-decoration: none;
                transition: background-color .15s;
            }
            .auth-home-link svg { width: 18px; height: 18px; }
            .auth-home-link:hover { background: rgba(22, 122, 58, .08); }
            .auth-home-link:focus-visible { outline: none; box-shadow: var(--focus-ring); }

            .auth-card {
                display: grid;
                grid-template-columns: 1fr 1fr;
                min-height: 560px;
                overflow: hidden;
                border: 1px solid var(--hairline);
                border-radius: 24px;
                background: #fff;
                box-shadow: 0 30px 70px -30px rgba(15, 77, 40, .35), 0 2px 6px rgba(15, 77, 40, .05);
            }

            /* Brand half: deep municipal green, the mark on a soft white disc. */
            .auth-left {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 32px;
                padding: 44px 44px 36px;
                overflow: hidden;
                color: #eaf6ee;
                background: linear-gradient(155deg, #167a3a 0%, #0f4d28 45%, #0b3a1e 100%);
            }
            /* Faint QR-grid texture, so the panel is not a flat slab. */
            .auth-left::after {
                content: '';
                position: absolute;
                inset: 0;
                background-image:
                    linear-gradient(rgba(255, 255, 255, .045) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, .045) 1px, transparent 1px);
                background-size: 28px 28px;
                mask-image: linear-gradient(to bottom, transparent, #000 40%, #000 70%, transparent);
                pointer-events: none;
            }
            .auth-left > * { position: relative; z-index: 1; }

            .auth-seals { display: flex; justify-content: center; gap: 12px; }
            .auth-seals img {
                width: 58px; height: 58px; object-fit: contain;
                padding: 3px;
                border-radius: 9999px;
                background: #fff;
                box-shadow: 0 4px 12px -4px rgba(0, 0, 0, .4);
            }

            .auth-brand { display: flex; flex-direction: column; align-items: center; text-align: center; }
            /* The white mark in flight: streaks (after the reference drawing —
               scattered lengths in lime and greens) stream back off the arrow
               on their own speeds, while the arrow surges and lifts. */
            .auth-logo-stage {
                position: relative;
                /* The arrow sits at the right of the stage with the trail to its
                   left; nudged right so the pair is centred in the panel. */
                left: 20px;
                display: flex; width: 360px; height: 190px;
                align-items: center; justify-content: flex-end;
                padding-right: 30px;
            }
            /* Only transform and opacity are animated, on their own layers, so
               the motion is composited — no per-frame repaint, no stutter. */
            .auth-logo-fly {
                position: relative; z-index: 1;
                display: block;
                will-change: transform;
                animation: auth-fly 3.6s ease-in-out infinite;
            }
            .auth-logo {
                display: block;
                width: 185px; height: 185px; object-fit: contain;
                filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .35));
            }
            /* The glow at the surge: a blurred copy fading in and out. */
            .auth-logo-fly::after {
                content: '';
                position: absolute; inset: 18%;
                border-radius: 50%;
                background: rgba(183, 228, 196, .45);
                filter: blur(22px);
                opacity: 0;
                will-change: opacity;
                animation: auth-glow 3.6s ease-in-out infinite;
                z-index: -1;
            }
            .auth-trail {
                position: absolute; top: 14%; bottom: 14%; left: 0;
                width: 47%;
                -webkit-mask-image: linear-gradient(to right, transparent 0%, #000 14%, #000 100%);
                mask-image: linear-gradient(to right, transparent 0%, #000 14%, #000 100%);
            }
            .auth-trail i {
                position: absolute; top: var(--t); right: 0;
                display: block; width: var(--w); height: 5px;
                border-radius: 9999px;
                background: var(--c);
                box-shadow: 0 0 8px color-mix(in srgb, var(--c) 60%, transparent);
                will-change: transform, opacity;
                animation: auth-streak var(--d) cubic-bezier(.3, 0, .7, 1) var(--l) infinite;
            }

            /* Arrow: a forward surge with a slight lift, then settles — a
               little tilt sells the speed. */
            @keyframes auth-fly {
                0%, 100% { transform: translate3d(-6px, 3px, 0) rotate(.6deg); }
                50%      { transform: translate3d(12px, -5px, 0) rotate(-1.6deg); }
            }
            @keyframes auth-glow {
                0%, 100% { opacity: 0; }
                50%      { opacity: 1; }
            }
            /* Each streak shoots out from behind the arrow and fades away. */
            @keyframes auth-streak {
                0%   { transform: translate3d(10%, 0, 0) scaleX(.3); opacity: 0; }
                15%  { opacity: 1; }
                70%  { opacity: .85; }
                100% { transform: translate3d(-90%, 0, 0) scaleX(1); opacity: 0; }
            }
            @media (prefers-reduced-motion: reduce) {
                .auth-logo-fly, .auth-logo-fly::after, .auth-trail i { animation: none; }
                .auth-trail i { transform: translateX(-20%); opacity: .8; }
            }

            .brand-title {
                margin: 18px 0 0;
                font-family: Zain, Nunito, ui-sans-serif, system-ui, sans-serif;
                font-size: 40px;
                font-weight: 800;
                line-height: 1;
                color: #fff;
            }
            .brand-title span { font-family: Nunito, ui-sans-serif, system-ui, sans-serif; font-weight: 500; color: #b7e4c4; }
            .brand-subtitle { margin: 12px 0 0; font-size: 15px; line-height: 1.5; color: rgba(234, 246, 238, .8); }

            .auth-foot { margin: 0; text-align: center; font-size: 13px; color: rgba(234, 246, 238, .6); }

            /* Form half */
            .auth-right {
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 48px 56px;
            }
            .auth-heading {
                margin: 0;
                font-family: Zain, Nunito, ui-sans-serif, system-ui, sans-serif;
                font-size: 34px;
                font-weight: 800;
                line-height: 1.1;
                color: var(--green-deep);
                text-align: left;
                text-transform: none;
                letter-spacing: -.01em;
            }
            .auth-subheading { margin: 8px 0 0; text-align: left; font-size: 16px; color: var(--ink-soft); }

            .auth-status { margin-top: 16px; padding: 10px 14px; border-radius: 10px; background: var(--green-wash); font-size: 14px; color: var(--green-deep); }
            .auth-form { margin-top: 28px; }
            .form-group { margin-bottom: 18px; }
            .form-label { display: block; margin-bottom: 8px; font-size: 15px; font-weight: 700; color: var(--ink); }
            .form-error { margin-top: 6px; font-size: 14px; font-weight: 600; color: var(--red); }

            .field-shell { position: relative; }
            .field-icon {
                position: absolute;
                top: 50%;
                left: 16px;
                display: flex;
                transform: translateY(-50%);
                color: var(--ink-soft);
                pointer-events: none;
                transition: color .15s;
            }
            .auth-card .field-icon svg { width: 20px !important; height: 20px !important; max-width: none; }
            .field-shell:focus-within .field-icon { color: var(--green); }

            .form-input {
                width: 100%;
                height: 52px;
                padding: 0 16px;
                border: 1px solid var(--hairline-strong);
                border-radius: 12px;
                background: #fff;
                font-size: 16px;
                color: var(--ink);
                outline: none;
                box-shadow: 0 1px 2px rgba(15, 77, 40, .04);
                transition: border-color .15s, box-shadow .15s;
            }
            .form-input::placeholder { color: #93a39a; }
            .form-input:hover { border-color: #b4c6bb; }
            .form-input:focus { border-color: var(--green); box-shadow: 0 0 0 4px rgba(22, 122, 58, .14); }
            .form-input-with-icon { padding-left: 48px; }
            .form-input-with-toggle { padding-right: 48px; }

            .auth-button {
                display: flex;
                width: 100%;
                height: 52px;
                margin-top: 26px;
                align-items: center;
                justify-content: center;
                gap: 8px;
                border: 0;
                border-radius: 12px;
                background: var(--green);
                font-size: 16px;
                font-weight: 800;
                color: #fff;
                cursor: pointer;
                box-shadow: 0 10px 22px -12px rgba(22, 122, 58, .8);
                transition: background-color .15s, transform .1s;
            }
            .auth-button svg { width: 18px; height: 18px; transition: transform .15s; }
            .auth-button:hover { background: var(--green-deep); }
            .auth-button:hover svg { transform: translateX(2px); }
            .auth-button:active { transform: scale(.99); }
            .auth-button:focus-visible { outline: none; box-shadow: var(--focus-ring); }


            @media (max-width: 860px) {
                .auth-card { grid-template-columns: 1fr; min-height: 0; }
                .auth-left { gap: 18px; padding: 28px; }
                .auth-foot { display: none; }
                .auth-logo-stage { left: 34px; width: 240px; height: 110px; padding-right: 16px; }
                .auth-logo { width: 120px; height: 120px; }
                .brand-title { margin-top: 18px; font-size: 30px; }
                .auth-right { padding: 32px 24px; }
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
