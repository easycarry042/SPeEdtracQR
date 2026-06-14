<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --page-bg: #d9efc4;
                --card-bg: #efefef;
                --text-main: #245f1f;
                --text-sub: #3d6f33;
                --input-bg: #f7fbf1;
                --input-border: #b8c9a8;
                --accent: #46a542;
                --accent-hover: #3b9237;
            }

            * { box-sizing: border-box; }

            body.auth-page {
                margin: 0;
                min-height: 100vh;
                font-family: Figtree, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                background: var(--page-bg);
                color: #1f2937;
            }

            main.auth-main {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            .auth-card {
                width: min(780px, 100%);
                background: var(--card-bg);
                border-radius: 8px;
                box-shadow: 0 15px 35px rgba(91, 128, 71, 0.25);
                overflow: hidden;
            }

            .auth-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .auth-left {
                padding: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-right: 1px solid #d8e8c0;
            }

            .auth-brand {
                text-align: center;
            }

            .brand-badge {
                width: 64px;
                height: 64px;
                margin: 0 auto 20px;
                border-radius: 50%;
                border: 4px solid #3f8f32;
            }

            .brand-title {
                margin: 0;
                font-size: 36px;
                font-weight: 700;
                line-height: 1;
                color: #3f8f32;
            }

            .brand-title span {
                color: #516b42;
            }

            .brand-subtitle {
                margin-top: 10px;
                color: #6c7f5e;
                font-size: 14px;
            }

            .auth-right {
                padding: 28px 32px;
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

            .form-group { margin-bottom: 14px; }

            .form-label {
                display: block;
                font-size: 30px;
                font-weight: 500;
                color: var(--text-main);
                margin-bottom: 8px;
                letter-spacing: .01em;
            }

            .form-input {
                width: 100%;
                border-radius: 7px;
                border: 1px solid var(--input-border);
                background: var(--input-bg);
                padding: 10px 12px;
                font-size: 16px;
                color: #355c2f;
                outline: none;
            }

            .form-input:focus {
                border-color: #5ea44e;
                box-shadow: 0 0 0 2px rgba(94, 164, 78, 0.25);
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
                border-radius: 7px;
                background: linear-gradient(90deg, #2ea04b 0%, #77b53f 100%);
                color: #fff;
                padding: 11px 16px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }

            .auth-button:hover { background: linear-gradient(90deg, #278e40 0%, #689d36 100%); }

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
                color: #8ca07e;
                font-size: 14px;
            }

            .auth-divider::before,
            .auth-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #d8e8c0;
            }

            .citizen-button {
                display: block;
                width: 100%;
                border: 2px solid #46a542;
                border-radius: 7px;
                background: transparent;
                color: #2d7a2a;
                padding: 10px 16px;
                font-size: 16px;
                font-weight: 600;
                text-align: center;
                text-decoration: none;
                cursor: pointer;
                transition: background 0.15s, color 0.15s;
            }

            .citizen-button:hover {
                background: #46a542;
                color: #fff;
            }

            @media (max-width: 900px) {
                .auth-grid { grid-template-columns: 1fr; }
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
    </head>
    <body class="auth-page">
        <main class="auth-main">
            {{ $slot }}
        </main>
    </body>
</html>
