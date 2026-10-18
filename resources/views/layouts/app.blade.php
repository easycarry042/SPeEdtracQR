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
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- "Accurate page titles" — the distinguishing part leads, so a row of
         pinned tabs is still readable once the names are truncated. Pages opt
         in with <x-app-layout title="Assignments">; the shell name alone is the
         fallback for any page that has not been given one yet. --}}
    <title>{{ $attributes->get('title') ? $attributes->get('title').' — ' : '' }}{{ config('app.name', 'SPeED TraQR') }}</title>
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
        request()->routeIs('admin.requests*') => 'Requests',
        request()->routeIs('admin.assignments*') => 'Assignments',
        request()->routeIs('bookings*') => 'Booking',
        request()->routeIs('reports.services') => 'Services report',
        request()->routeIs('admin.audit-log*') => 'Audit Log',
        request()->routeIs('admin.departments*') => 'Departments',
        request()->routeIs('admin.route-templates*') => 'Route Templates',
        request()->routeIs('admin.request-types*') => 'Request Types',
        request()->routeIs('admin.resources*') => 'Resources',
        request()->routeIs('profile.*') => 'Settings',
        request()->routeIs('documents.*') => 'Documents',
        request()->routeIs('requests.*') => 'Internal',
        default => config('app.name', 'SPeED TraQR'),
    };
@endphp
{{-- The guest branch is a column so a short public page still drops the footer
     to the bottom of the viewport; the staff shell does its own flex layout. --}}
<body class="{{ $fixedHeightRoot }} min-h-screen antialiased @auth bg-[var(--page-wash-base)] text-ink @else flex flex-col bg-white text-gray-900 @endauth">
    {{-- First tab stop on the page: a keyboard user would otherwise have to
         walk the whole sidebar and header before reaching the content. Hidden
         off-screen until it takes focus (see .skip-link). --}}
    <a href="#main" class="skip-link">Skip to content</a>
    @auth
        {{-- One shell for every signed-in role: a pinned sidebar whose
             link set is chosen in partials/sidebar-links. --}}
            <div class="admin-shell flex min-h-screen" data-soft-nav-root
                 :class="mobileNav ? 'mobile-nav-open' : ''"
                 x-data="{ mobileNav: false }">
                <div x-show="mobileNav" x-cloak @click="mobileNav = false" class="sidebar-backdrop lg:hidden"></div>
                {{-- Fixed, always-expanded sidebar (no hover-collapse animation on navigate).
                     The mobile drawer still slides in via .mobile-nav-open. --}}
                <aside @click="if ($event.target.closest('a')) mobileNav = false"
                       class="sidebar-pinned sticky top-0 z-40 flex h-screen w-[232px] shrink-0 flex-col overflow-hidden nav-bar xl:w-[297px]">
                    {{-- Brand: the white mark on deep green, linking home. Brand,
                         section label, nav icons and the Accessibility row all
                         share one left edge (.sb-inset). --}}
                    <a href="{{ $homeRoute }}" data-soft-nav-link
                       class="sb-brand nav-brand" aria-label="{{ config('app.name', 'SPeED TraQR') }} home">
                        <img src="{{ asset('images/staff/logo-white.png') }}" alt="" class="sb-brand-mark">
                        <span class="nav-text truncate whitespace-nowrap tracking-tight text-white">
                            <span class="font-display font-black text-[#f9fff9]">SPeED</span> <span class="font-sans font-medium">TraQR</span>
                        </span>
                    </a>

                    <p class="sb-section" id="sb-menu-label">Menu</p>

                    <nav class="nav-scroll sb-nav" aria-labelledby="sb-menu-label" data-soft-nav="links">
                        @include('layouts.partials.sidebar-links')
                    </nav>

                    {{-- Opens the Sienna toolbar. Its own launcher is parked at the
                         screen edge where it is easy to miss, so the sidebar gives
                         it a named row. Hidden when the widget is switched off. --}}
                    @if(config('app.accessibility_widget', true))
                        <div class="sb-foot">
                            <button type="button"
                                    onclick="document.querySelector('.asw-menu-btn')?.click()"
                                    class="sb-row sb-row-accent">
                                <img src="{{ asset('images/staff/nav-accessibility.svg') }}" alt="" class="sb-icon">
                                <span class="nav-text whitespace-nowrap">Accessibility</span>
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
                            <span class="hidden shrink-0 items-center gap-[5px] sm:flex">
                                <img src="{{ asset('images/staff/seal-1.png') }}" alt="" class="h-[40px] w-[40px] object-contain">
                                <img src="{{ asset('images/staff/seal-2.png') }}" alt="" class="h-[42.5px] w-[42.5px] object-contain">
                            </span>
                            <img src="{{ asset('images/staff/title-divider.svg') }}" alt="" aria-hidden="true"
                                 class="hidden h-[40px] w-[5px] shrink-0 sm:block">
                            {{-- No role badge here: the identity chip on the right
                                 already names the role and the desk. --}}
                            <h1 class="layout-title" data-soft-nav="title" tabindex="-1">{{ $pageTitle }}</h1>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- Same slot as the topnav shell, so a page's title-row
                                 action is not lost for sidebar users (super admins). --}}
                            {{-- Always rendered (empty when unset), so soft navigation
                                 has a place to swap a page's actions in and out. --}}
                            <div class="flex min-w-0 shrink items-center gap-3 empty:hidden" data-soft-nav="actions">@isset($pageActions){{ $pageActions }}@endisset</div>
                            @include('layouts.partials.header-actions')
                        </div>
                        </div>
                    </header>

                    <div data-soft-nav="header">
                        @isset($header)
                            <div class="border-b border-transparent">
                                <div class="app-frame pb-2 pt-4">
                                    {{ $header }}
                                </div>
                            </div>
                        @endisset
                    </div>

                    <main id="main" class="flex-1 pb-10 pt-2 {{ $fixedHeight }}" data-soft-nav="main">
                        <div class="app-frame">
                            {{ $slot }}
                        </div>
                    </main>

                    {{-- No site footer inside the signed-in shell. The footer is
                         wayfinding for visitors — the office's contact details,
                         the privacy notice, the legal line — and staff already
                         have all of it in the sidebar. On a work queue it is
                         only padding plus a dark plate between the user and the
                         bottom of their list. It still closes every public page,
                         including the guest branch below. --}}
                </div>
            </div>
        @include('layouts.partials.keyboard-shortcuts')
    @else
        {{-- Guests can reach a few app-layout pages; they get the same citizen
             header as every other public page. --}}
        @include('layouts.partials.portal-header')

        <main id="main" class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>

        <x-site-footer />
    @endauth

    @if($fileRequestForm ?? null)
        {{-- Internal-request filing form, opened by the header's "File Request"
             button on any page. Reopens by itself when filing failed
             validation, so the errors are not lost behind a closed modal. --}}
        <div x-data="{ open: @js($errors->hasAny(['first_department_id', 'purpose', 'paper_scan', 'qr_x', 'qr_y', 'qr_size', 'qr_page'])) }"
             @open-file-request.window="open = true" @close-file-request.window="open = false">
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                 class="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
                 @click.self="open = false" role="dialog" aria-modal="true" aria-label="File internal request">
                <div class="dm-card dm-fixed dm-fit mx-auto w-full max-w-[940px]">
                    @include('requests.partials.file-form', $fileRequestForm + ['inModal' => true])
                </div>
            </div>
        </div>
    @endif

    @if($showCreateDocumentModal ?? false)
        @include('documents.partials.create-modal')
    @endif

    <x-image-view-modal />

    {{-- The shared pop-up error report (window.ErrorAlert). --}}
    <x-error-alert />

    @include('layouts.partials.bfcache-guard')
</body>
</html>
