<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Citizen Portal' }} — {{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    @include('layouts.partials.accessibility-widget')
</head>
<body class="relative min-h-screen antialiased text-gray-900"
      {{-- Shared public wash (--page-wash in app.css) over the arrow doodle —
           the same treatment the login page uses. Fixed, so the green band
           stays at the top of the viewport as the page scrolls. --}}
      style="background-color: var(--page-wash-base);
             background-image: var(--page-wash), url('{{ asset('images/doodle-bg.png') }}');
             background-size: cover, cover;
             background-position: center, center;
             background-attachment: fixed, fixed;
             background-repeat: no-repeat, no-repeat;">

    {{-- Top navigation bar. The bar sits on the wash's deep-green band here, so
         the brand needs light type to stay readable. --}}
    @include('layouts.partials.public-header', ['onDarkWash' => true])

    {{-- Page content. No footer: the wash runs to the bottom of the page. --}}
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    @include('layouts.partials.bfcache-guard')
</body>
</html>
