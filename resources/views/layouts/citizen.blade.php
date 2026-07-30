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
<body class="civic-mesh min-h-screen antialiased text-gray-900">

    {{-- Top navigation bar --}}
    @include('layouts.partials.public-header')

    {{-- Page content. No footer: the wash runs to the bottom of the page. --}}
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    @include('layouts.partials.bfcache-guard')
</body>
</html>
