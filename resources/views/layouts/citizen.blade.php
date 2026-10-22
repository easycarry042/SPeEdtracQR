<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'City of San Pedro' }} — {{ config('app.name', 'SPeED TraQR') }}</title>
    @include('layouts.partials.echo-ready')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    @include('layouts.partials.accessibility-widget')
</head>
{{-- White paper. The redesign dropped the doodle wash from the portal — it is
     plain white here now, and a test holds that. --}}
{{-- Column flex, so a short page still pushes the footer to the bottom of the
     viewport instead of leaving it floating mid-screen. --}}
<body class="relative flex min-h-screen flex-col bg-white antialiased text-ink">
    {{-- First tab stop: jumps the header. --}}
    <a href="#main" class="skip-link">Skip to content</a>

    @include('layouts.partials.portal-header')

    {{-- 1209.5px is the redesign's frame — the old 1512 scaled to 0.8. The
         same container and gutters as the header, so page content lines up
         under the seal exactly as it does on the landing page. --}}
    <main id="main" class="mx-auto w-full max-w-[1209.5px] flex-1 px-4 pb-10 pt-10 sm:px-6 sm:pt-14 lg:px-8">
        {{ $slot }}
    </main>

    {{-- The footer closes the page off, and carries the office's contact
         details for a visitor who got here from a QR code and has no other
         way back to them. --}}
    <x-site-footer />

    {{-- Speedy, parked bottom-right on every citizen page (see
         components/speedy.blade.php). A page about one request passes it as
         speedy-document, and Speedy opens already talking about it. --}}
    @unless($attributes->has('hide-speedy'))
        <x-speedy :document="$speedyDocument" />
    @endunless

    {{-- The shared pop-up error report (window.ErrorAlert). --}}
    <x-error-alert />

    @include('layouts.partials.bfcache-guard')
</body>
</html>
