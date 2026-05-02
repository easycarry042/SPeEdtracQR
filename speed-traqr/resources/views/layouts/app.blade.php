<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
@php
    $user = auth()->user();
    $name = $user->name ?? 'User';
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(
        count($parts) >= 2
            ? mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1)
            : mb_substr($name, 0, 2)
    );
@endphp
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">
    <div class="flex min-h-screen">
        @auth
            {{-- Sidebar: collapsed = icons centered; hover expands and left-aligns labels --}}
            <aside class="group sticky top-0 z-40 flex h-screen w-[4.5rem] shrink-0 flex-col overflow-hidden border-r border-emerald-200/80 bg-emerald-50 transition-[width] duration-300 ease-out hover:w-64 hover:shadow-[4px_0_24px_-4px_rgba(20,83,45,0.15)]">
                <div class="flex h-[4.25rem] shrink-0 items-center justify-center gap-0 border-b border-emerald-200/60 px-1 transition-all duration-300 ease-out group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-200/70 text-emerald-900 shadow-sm ring-1 ring-emerald-300/50 transition-transform duration-300 group-hover:scale-105">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3 11.5 21 3v7l-8 2.5L3 11.5zm2.2 1.6 5.3 1.7 9.5-8.5v5.5l-6 4.5v-6l-8.8-2.8z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 max-w-0 overflow-hidden opacity-0 transition-all duration-300 ease-out group-hover:max-w-[200px] group-hover:opacity-100">
                        <p class="truncate whitespace-nowrap text-base font-extrabold tracking-tight text-emerald-950">
                            SPeED <span class="font-bold text-emerald-800">TraQR</span>
                        </p>
                    </div>
                </div>

                <nav class="flex flex-1 flex-col gap-1 overflow-y-auto overflow-x-hidden px-1 py-4 transition-[padding] duration-300 ease-out group-hover:px-2">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-emerald-300/70 text-emerald-950 shadow-sm ring-1 ring-emerald-400/40' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('dashboard') ? 'bg-emerald-600/25 text-emerald-950' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Dashboard</span>
                    </a>
                    <a href="{{ route('analytics') }}" class="{{ request()->routeIs('analytics*') ? 'bg-emerald-300/70 text-emerald-950 shadow-sm ring-1 ring-emerald-400/40' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('analytics*') ? 'bg-emerald-600/25 text-emerald-950' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Analytics</span>
                    </a>
                    <a href="{{ route('track.index') }}" class="{{ request()->routeIs('track.*') ? 'bg-emerald-300/70 text-emerald-950 shadow-sm ring-1 ring-emerald-400/40' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('track.*') ? 'bg-emerald-600/25 text-emerald-950' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 4l13 8-13 8V4z"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Track Document</span>
                    </a>
                    <a href="{{ route('scan.index') }}" class="{{ request()->routeIs('scan.*') ? 'bg-emerald-300/70 text-emerald-950 shadow-sm ring-1 ring-emerald-400/40' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('scan.*') ? 'bg-emerald-600/25 text-emerald-950' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 19H3a2 2 0 01-2-2v-2m8-4h.01M12 12h.01M16 12h.01M8 12h.01"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Scan</span>
                    </a>
                    <a href="{{ route('history') }}" class="{{ request()->routeIs('history*') ? 'bg-emerald-300/70 text-emerald-950 shadow-sm ring-1 ring-emerald-400/40' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('history*') ? 'bg-emerald-600/25 text-emerald-950' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">History</span>
                    </a>
                </nav>

                <div class="shrink-0 border-t border-emerald-200/60 p-1 transition-[padding] duration-300 ease-out group-hover:p-2">
                    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'bg-emerald-300/70 text-emerald-950' : 'text-emerald-900 hover:bg-emerald-200/60' }} flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-emerald-800">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2-1.55-2-3.45-2.45.7a7.6 7.6 0 0 0-1.75-1.05L14.8 3h-4l-.45 2.65a7.6 7.6 0 0 0-1.75 1.05l-2.45-.7-2 3.45L6.15 11a7.8 7.8 0 0 0 .05 2l-2 1.55 2 3.45 2.45-.7c.53.43 1.12.79 1.75 1.05L10.8 21h4l.45-2.65a7.6 7.6 0 0 0 1.75-1.05l2.45.7 2-3.45-2.05-1.55zM12 15.3A3.3 3.3 0 1 1 12 8.7a3.3 3.3 0 0 1 0 6.6z"/></svg>
                        </span>
                        <span class="max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Settings</span>
                    </a>
                </div>
            </aside>
        @endauth

        <div class="flex min-w-0 flex-1 flex-col">
            @auth
                <header class="sticky top-0 z-30 flex items-center justify-end gap-3 border-b border-emerald-200/40 bg-[#f1f2f1]/90 px-4 py-3 backdrop-blur-md sm:px-6 lg:px-8">
                    <a href="{{ route('documents.create') }}" class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-200/90 text-emerald-900 shadow-sm ring-1 ring-emerald-300/40 transition hover:scale-105 hover:bg-emerald-300/90 hover:shadow-md active:scale-95" title="New document">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6M9 14h6"/>
                        </svg>
                    </a>

                    <div class="relative" x-data="{ notificationsOpen: false }" @click.outside="notificationsOpen = false">
                        <button type="button" @click="notificationsOpen = !notificationsOpen" class="relative flex h-11 w-11 items-center justify-center rounded-full bg-emerald-200/90 text-emerald-900 shadow-sm ring-1 ring-emerald-300/40 transition hover:scale-105 hover:bg-emerald-300/90 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2" title="Notifications" :aria-expanded="notificationsOpen">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2z"/></svg>
                        </button>
                        <div x-show="notificationsOpen"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                             x-cloak
                             class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white py-2 shadow-xl shadow-gray-900/15">
                            <p class="border-b border-gray-100 px-4 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Notifications</p>
                            <p class="px-4 py-6 text-center text-sm text-gray-500">You&apos;re all caught up — no new notifications.</p>
                        </div>
                    </div>

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-full bg-emerald-200/90 py-1.5 pl-1.5 pr-3 text-emerald-950 shadow-sm ring-1 ring-emerald-300/40 transition hover:bg-emerald-300/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">{{ $initials }}</span>
                            <svg class="h-4 w-4 text-emerald-900/70" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                             x-cloak
                             class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-gray-200 bg-gray-100 py-1 shadow-xl shadow-gray-900/10">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">Manage Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50">Logout</button>
                            </form>
                        </div>
                    </div>
                </header>
            @endauth

            @isset($header)
                <div class="border-b border-transparent px-4 pb-2 pt-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            @endisset

            <main class="flex-1 px-4 pb-10 pt-2 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
