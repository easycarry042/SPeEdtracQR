<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Citizen Portal' }} — {{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-100 antialiased text-gray-900">

    {{-- Top navigation bar --}}
    @include('layouts.partials.public-header')

    {{-- Page content --}}
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-16 border-t border-emerald-200/60 bg-white/60 py-6 text-center text-sm text-gray-500">
        &copy; {{ date('Y') }} {{ config('app.name', 'SPeED TraQR') }} &mdash; Citizen Portal
    </footer>

    @include('layouts.partials.bfcache-guard')
</body>
</html>
