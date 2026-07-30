<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    {{-- Page-specific assets (e.g. the PDF editor's own bundle). --}}
    @stack('head')
</head>
@php
    $user = auth()->user();
    $user?->loadMissing('roles');
    $name = $user->name ?? 'User';
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(
        count($parts) >= 2
            ? mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1)
            : mb_substr($name, 0, 2)
    );
    // One canonical label per role (Staff / Supervisor / Administrator).
    $roleLabel = \App\Support\RoleLabel::forUser($user);
    $isSystemAdmin = $user?->can('manage system') ?? false;
    $isSupervisor = $user?->hasRole('Supervisor') ?? false;
    // One navigation style for every role: the horizontal top navbar. The old
    // super-admin-only sidebar is gone — a different shell per role made the
    // product feel like two different apps.
    $useTopNav = auth()->check();
    // Guests can reach app-layout pages (e.g. the public /track page), so fall
    // back to the site root when there is no authenticated user to link to.
    // Staff have no separate Requests tab any more: their assigned work lives in
    // My Profile, so that page is their home.
    $homeRoute = match (true) {
        $isSystemAdmin => route('admin.dashboard'),
        $isSupervisor => route('dashboard'),
        (bool) $user => route('staff.profile', $user->id),
        default => url('/'),
    };
    $homeActive = match (true) {
        $isSystemAdmin => request()->routeIs('admin.dashboard'),
        $isSupervisor => request()->routeIs('dashboard'),
        default => request()->routeIs('staff.profile')
            && (int) request()->route('user')?->id === (int) $user?->id,
    };
    $pageTitle = match (true) {
        // Own profile == the staff Dashboard; someone else's is a staff profile.
        request()->routeIs('staff.profile') => (int) request()->route('user')?->id === (int) $user?->id
            ? 'Dashboard'
            : 'Staff profile',
        request()->routeIs('staff.index') => 'Staff directory',

        request()->routeIs('dashboard'), request()->routeIs('admin.dashboard') => 'Dashboard',
        request()->routeIs('analytics*') => 'Analytics',
        request()->routeIs('track.*'), request()->routeIs('scan.*') => 'Requests',
        request()->routeIs('history*') => 'History',
        request()->routeIs('admin.users*') => 'Users',
        request()->routeIs('admin.assignments*') => 'Assignments',
        request()->routeIs('bookings*') => 'Bookings',
        request()->routeIs('reports.services') => 'Services report',
        request()->routeIs('admin.audit-log*') => 'Audit Log',
        request()->routeIs('admin.departments*') => 'Departments',
        request()->routeIs('admin.route-templates*') => 'Route Templates',
        request()->routeIs('admin.request-types*') => 'Request Types',
        request()->routeIs('admin.resources*') => 'Resources',
        request()->routeIs('profile.*') => 'Settings',
        request()->routeIs('documents.*') => 'Documents',
        request()->routeIs('requests.*') => 'Internal Requests',
        default => config('app.name', 'SPeED TraQR'),
    };
@endphp
<body class="min-h-screen antialiased @auth bg-canvas text-ink @else bg-gradient-to-br from-emerald-50 to-teal-100 text-gray-900 @endauth">
    @auth
        {{-- One shell for every signed-in role: horizontal top navbar,
             full-width content. Nav items themselves are permission-gated. --}}
            <div class="topnav-shell" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
                <header class="topnav-bar">
                    <div class="topnav-inner app-frame">
                        <a href="{{ $homeRoute }}" class="topnav-brand">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 ring-1 ring-brass/60">
                                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR">
                            </span>
                            <div class="min-w-0">
                                <p class="wordmark">SPeED <b>TraQR</b></p>
                                <p class="sub">San Pedro · records office</p>
                            </div>
                        </a>

                        <nav class="topnav-links" aria-label="Main">
                            @include('layouts.partials.topnav-links')
                        </nav>

                        <button type="button" class="topnav-burger" @click="navOpen = !navOpen" :aria-expanded="navOpen ? 'true' : 'false'" aria-label="Open menu">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>

                        <div class="topnav-actions">
                            @include('layouts.partials.header-actions')
                        </div>
                    </div>

                    <div x-show="navOpen" x-cloak class="topnav-drawer md:hidden">
                        <nav class="topnav-drawer-inner app-frame" aria-label="Main mobile" @click="navOpen = false">
                            @include('layouts.partials.topnav-links')
                        </nav>
                    </div>
                </header>

                @unless(request()->routeIs('staff.profile'))
                <header class="topnav-pagebar">
                    <div class="app-frame">
                        <div class="flex min-w-0 items-center gap-3">
                            <h1 class="truncate">{{ $pageTitle }}</h1>
                            @if($roleLabel)
                                <span class="hidden sm:inline-flex shrink-0 items-center rounded-full bg-green-wash px-2.5 py-0.5 text-xs font-semibold text-green-deep">{{ $roleLabel }}</span>
                            @endif
                        </div>
                        {{-- Page-specific context, right-aligned on the title row. --}}
                        @isset($pageActions)
                            <div class="flex min-w-0 shrink items-center gap-3">
                                {{ $pageActions }}
                            </div>
                        @endisset
                    </div>
                </header>
                @endunless

                @isset($header)
                    <div class="border-b border-transparent">
                        <div class="app-frame pb-2 pt-2">
                            {{ $header }}
                        </div>
                    </div>
                @endisset

                <main class="topnav-main">
                    <div class="app-frame">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        @include('layouts.partials.keyboard-shortcuts')
    @else
        {{-- Guests can reach a few app-layout pages (public /track lookup and the
             tracking result). Use the same public portal header as /citizen. --}}
        @include('layouts.partials.public-header')

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>
    @endauth

    @if($showCreateDocumentModal ?? false)
        @include('documents.partials.create-modal')
    @endif

    <x-image-view-modal />

    @include('layouts.partials.bfcache-guard')
</body>
</html>
