@php
    /* <x-app-layout fixed-height> — the page fills the viewport and never
       scrolls; its own panels scroll internally. Needed this early because the
       lock is applied to <html> and <body>, not just the shell. */
    $fixedHeight = $attributes->has('fixed-height') ? 'main-fixed' : '';
    $fixedHeightRoot = $fixedHeight ? 'app-fixed-height' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $fixedHeightRoot }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    {{-- Page-specific assets (e.g. the PDF editor's own bundle). --}}
    @stack('head')
    @include('layouts.partials.accessibility-widget', ['hideLauncher' => auth()->check()])
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
    $roleLabel = $user?->roles->first()?->name;
    $roleLabel = $roleLabel ? str_replace('_', ' ', ucwords($roleLabel, '_')) : null;
    $isSystemAdmin = $user?->can('manage system') ?? false;
    $isSupervisor = $user?->hasRole('Supervisor') ?? false;
    // Guests can reach app-layout pages (e.g. the public /track page), so fall
    // back to the site root when there is no authenticated user to link to.
    $homeRoute = match (true) {
        $isSupervisor => route('dashboard'),
        (bool) $user => route('staff.dashboard'),
        default => url('/'),
    };
    $homeActive = $isSupervisor ? request()->routeIs('dashboard') : request()->routeIs('staff.dashboard');
    $pageTitle = match (true) {
        request()->routeIs('staff.profile') => 'My Profile',
        request()->routeIs('staff.index') => 'Staff directory',
        request()->routeIs('staff.dashboard') => 'Requests',
        request()->routeIs('dashboard'), request()->routeIs('admin.dashboard') => 'Dashboard',
        request()->routeIs('analytics*') => 'Analytics',
        request()->routeIs('track.*'), request()->routeIs('scan.*') => 'Look up',
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
<body class="{{ $fixedHeightRoot }} min-h-screen antialiased @auth text-ink @else bg-gradient-to-br from-emerald-50 to-teal-100 text-gray-900 @endauth"
      @auth
      {{-- The arrow doodle only: the public pages' colour ramp would fight the
           green sidebar and the white panels this shell is built from. --}}
      style="background-color: var(--page-wash-base);
             background-image: var(--page-wash-veil), url('{{ asset('images/doodle-bg.png') }}');
             background-size: cover, cover;
             background-position: center, center;
             background-attachment: fixed, fixed;
             background-repeat: no-repeat, no-repeat;"
      @endauth>
    @auth
        {{-- One shell for every signed-in role: a pinned sidebar whose
             link set is chosen in partials/sidebar-links. --}}
            <div class="admin-shell flex min-h-screen"
                 :class="mobileNav ? 'mobile-nav-open' : ''"
                 x-data="{ mobileNav: false }">
                <div x-show="mobileNav" x-cloak @click="mobileNav = false" class="sidebar-backdrop lg:hidden"></div>
                {{-- Fixed, always-expanded sidebar (no hover-collapse animation on navigate).
                     The mobile drawer still slides in via .mobile-nav-open. --}}
                <aside @click="if ($event.target.closest('a')) mobileNav = false"
                       class="sidebar-pinned sticky top-0 z-40 flex h-screen w-64 shrink-0 flex-col overflow-hidden nav-bar">
                    {{-- Brand: the white mark on deep green, no strapline — the
                         page bar below already names where you are. --}}
                    <div class="nav-brand flex h-[4.75rem] shrink-0 items-center justify-center gap-3 px-4">
                        <img src="{{ asset('images/icon-white.png') }}" alt="" class="h-8 w-8 shrink-0 object-contain">
                        <p class="nav-text truncate whitespace-nowrap text-lg font-extrabold tracking-tight text-white">
                            <span class="font-display">SPeED</span> <span class="font-sans">TraQR</span>
                        </p>
                    </div>

                    <p class="px-5 pb-2 pt-3 text-sm font-semibold text-white/75">Main Menu</p>

                    <nav class="nav-scroll flex flex-1 flex-col gap-1 overflow-y-auto overflow-x-hidden px-3 py-1">
                        @include('layouts.partials.sidebar-links')
                    </nav>

                    {{-- Opens the Sienna toolbar. Its own launcher is parked at the
                         screen edge where it is easy to miss, so the sidebar gives
                         it a named row. Hidden when the widget is switched off. --}}
                    @if(config('app.accessibility_widget', true))
                        <div class="shrink-0 border-t border-white/10 px-3 py-4">
                            <button type="button"
                                    onclick="document.querySelector('.asw-menu-btn')?.click()"
                                    class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left text-brass transition hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brass">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center">
                                    <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="4" r="2"/>
                                        <path d="M20 8.5c-2.4.8-5.1 1.2-8 1.2s-5.6-.4-8-1.2L4.5 10c1.8.6 3.7 1 5.7 1.2l-.6 3.3L7 21.2l1.9.7 2.3-6h1.6l2.3 6 1.9-.7-2.6-6.7-.6-3.3c2-.2 3.9-.6 5.7-1.2L20 8.5z"/>
                                    </svg>
                                </span>
                                <span class="nav-text whitespace-nowrap text-sm font-bold">Accessibility</span>
                            </button>
                        </div>
                    @endif

                    {{-- No Settings entry here: it lives in the profile dropdown
                         (layouts/partials/header-actions) for every role. --}}
                </aside>

                <div class="flex min-w-0 flex-1 flex-col">
                    {{-- No rule under the bar: it fades from solid white into the
                         page, which is what separates it from what scrolls
                         beneath. --}}
                    <header class="sticky top-0 z-30 bg-gradient-to-b from-white via-white/85 to-transparent pb-6 pt-3">
                        <div class="app-frame flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" @click="mobileNav = !mobileNav"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-green-deep transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green lg:hidden"
                                    aria-label="Open navigation">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                            </button>
                            {{-- City and Tourism Office seals lead the page bar; they
                                 are branding, so the title carries the alt text. --}}
                            <span class="hidden shrink-0 items-center gap-1.5 sm:flex">
                                <img src="{{ asset('images/SPL-logo.png') }}" alt="" class="h-10 w-10 object-contain">
                                <img src="{{ asset('images/TOURISM-logo.png') }}" alt="" class="h-10 w-10 object-contain">
                            </span>
                            <span class="hidden h-9 w-1 shrink-0 rounded-full bg-brass sm:block" aria-hidden="true"></span>
                            {{-- No role badge here: the identity chip on the right
                                 already names the role and the desk. --}}
                            <h1 class="layout-title">{{ $pageTitle }}</h1>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- Same slot as the topnav shell, so a page's title-row
                                 action is not lost for sidebar users (super admins). --}}
                            @isset($pageActions)
                                <div class="flex min-w-0 shrink items-center gap-3">
                                    {{ $pageActions }}
                                </div>
                            @endisset
                            @include('layouts.partials.header-actions')
                        </div>
                        </div>
                    </header>

                    @isset($header)
                        <div class="border-b border-transparent">
                            <div class="app-frame pb-2 pt-4">
                                {{ $header }}
                            </div>
                        </div>
                    @endisset

                    <main class="flex-1 pb-10 pt-2 {{ $fixedHeight }}">
                        <div class="app-frame">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
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
