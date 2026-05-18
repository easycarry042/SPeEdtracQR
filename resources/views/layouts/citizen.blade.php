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
    <header class="sticky top-0 z-40 border-b border-emerald-200/60 bg-white/90 backdrop-blur-md shadow-sm">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            {{-- Brand --}}
            <a href="{{ route('citizen.dashboard') }}" class="flex items-center gap-3 group">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-9 w-9 rounded-lg">
                <span class="text-lg font-extrabold tracking-tight text-emerald-950 group-hover:text-emerald-700 transition">
                    SPeED <span class="text-emerald-600">TraQR</span>
                </span>
            </a>

            {{-- Nav actions --}}
            <div class="flex items-center gap-3">
                <a href="{{ route('citizen.track') }}"
                   class="hidden sm:inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition {{ request()->routeIs('citizen.track') ? 'bg-emerald-50 text-emerald-900' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 4l13 8-13 8V4z"/>
                    </svg>
                    Track Document
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 transition">
                    Staff Login
                </a>
            </div>
        </div>
    </header>

    {{-- Page content --}}
    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    {{-- Footer --}}
    <footer class="mt-16 border-t border-emerald-200/60 bg-white/60 py-6 text-center text-sm text-gray-500">
        &copy; {{ date('Y') }} {{ config('app.name', 'SPeED TraQR') }} &mdash; Citizen Portal
    </footer>

</body>
</html>
