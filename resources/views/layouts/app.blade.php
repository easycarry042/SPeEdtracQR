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
    $useTopNav = auth()->check() && ! $isSystemAdmin;
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
<body class="min-h-screen antialiased @auth bg-paper text-ink @else bg-gradient-to-br from-emerald-50 to-teal-100 text-gray-900 @endauth">
    @auth
        @if($useTopNav)
            {{-- Staff / supervisor: horizontal top navbar, full-width content --}}
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
                                <span class="hidden sm:inline-flex shrink-0 items-center rounded-full bg-green-wash px-2.5 py-0.5 text-xs font-semibold text-green-deep">{{ str_replace('_', ' ', ucwords($roleLabel, '_')) }}</span>
                            @endif
                        </div>
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
        @else
            {{-- Super admin: collapsible sidebar --}}
            <div class="admin-shell flex min-h-screen"
                 :class="mobileNav ? 'mobile-nav-open' : ''"
                 x-data="{
                     pinned: (localStorage.getItem('sidebarPinned') ?? '1') === '1',
                     mobileNav: false,
                     toggleSidebar() {
                         if (window.matchMedia('(min-width: 1024px)').matches) {
                             this.pinned = !this.pinned;
                         } else {
                             this.mobileNav = !this.mobileNav;
                         }
                     }
                 }"
                 x-init="$watch('pinned', v => localStorage.setItem('sidebarPinned', v ? '1' : '0'))">
                <div x-show="mobileNav" x-cloak @click="mobileNav = false" class="sidebar-backdrop lg:hidden"></div>
                <aside :class="pinned ? 'sidebar-pinned' : ''"
                       @click="if ($event.target.closest('a')) mobileNav = false"
                       class="group sticky top-0 z-40 flex h-screen w-[4.5rem] shrink-0 flex-col overflow-hidden border-r border-green-deep nav-bar transition-[width] duration-300 ease-out hover:w-64 hover:shadow-[4px_0_24px_-4px_rgba(15,77,40,0.25)]">
                    <div class="nav-brand flex h-[4.25rem] shrink-0 items-center justify-center gap-0 border-b border-white/10 px-1 transition-all duration-300 ease-out group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/10 ring-1 ring-brass/60">
                            <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-7 w-7">
                        </span>
                        <div class="nav-text min-w-0 max-w-0 overflow-hidden opacity-0 transition-all duration-300 ease-out group-hover:max-w-[200px] group-hover:opacity-100">
                            <p class="truncate whitespace-nowrap text-base font-semibold tracking-tight text-on-green">
                                SPeED <span class="font-bold">TraQR</span>
                            </p>
                            <p class="truncate whitespace-nowrap text-[11px] text-on-green-soft">San Pedro · records office</p>
                        </div>
                    </div>

                    <nav class="nav-scroll flex flex-1 flex-col gap-1 overflow-y-auto overflow-x-hidden px-1 py-4 transition-[padding] duration-300 ease-out group-hover:px-2">
                        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.dashboard') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Dashboard</span>
                        </a>
                        {{-- Analytics is folded into the command-center Dashboard for super admins;
                             the standalone Analytics page stays for Supervisors via the topnav.
                             History moved into the account menu (header-actions). --}}
                        <a href="{{ route('staff.index') }}" class="{{ request()->routeIs('staff.index') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('staff.index') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H2v-2a4 4 0 0 1 3-3.87m10-2.13a4 4 0 1 0-6 0M15 7a3 3 0 1 1 4 2.83"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Staff</span>
                        </a>
                        @can('manage users')
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.users*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Users</span>
                        </a>
                        @endcan
                        @can('assign documents')
                        <a href="{{ route('admin.assignments.index') }}" class="{{ request()->routeIs('admin.assignments*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.assignments*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Assignments</span>
                        </a>
                        <a href="{{ route('bookings.index') }}" class="{{ request()->routeIs('bookings*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('bookings*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Bookings</span>
                        </a>
                        @endcan
                        @can('view reports')
                        <a href="{{ route('reports.services') }}" class="{{ request()->routeIs('reports.services') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('reports.services') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Services report</span>
                        </a>
                        @endcan
                        @can('manage system')
                        <a href="{{ route('requests.index') }}" class="{{ request()->routeIs('requests.*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('requests.*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Internal Requests</span>
                        </a>
                        <a href="{{ route('admin.departments.index') }}" class="{{ request()->routeIs('admin.departments*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.departments*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V7l8-4 8 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Departments</span>
                        </a>
                        <a href="{{ route('admin.route-templates.index') }}" class="{{ request()->routeIs('admin.route-templates*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.route-templates*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5a2 2 0 11-4 0 2 2 0 014 0zm14 14a2 2 0 11-4 0 2 2 0 014 0zM7 5h9a3 3 0 013 3v1m-2 10H8a3 3 0 01-3-3v-1m0-4v2m14-6v2"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Route Templates</span>
                        </a>
                        <a href="{{ route('admin.request-types.index') }}" class="{{ request()->routeIs('admin.request-types*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.request-types*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Request Types</span>
                        </a>
                        <a href="{{ route('admin.resources.index') }}" class="{{ request()->routeIs('admin.resources*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.resources*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Resources</span>
                        </a>
                        <a href="{{ route('admin.audit-log.index') }}" class="{{ request()->routeIs('admin.audit-log*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.audit-log*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M7 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Audit Log</span>
                        </a>
                        @endcan
                    </nav>

                    <div class="shrink-0 border-t border-white/10 p-1 transition-[padding] duration-300 ease-out group-hover:p-2">
                        <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'bg-[#155f37] text-on-green shadow-[inset_3px_0_0_#c79a3e]' : 'text-on-green-soft hover:bg-white/5 hover:text-on-green' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('profile.*') ? 'text-on-green' : 'bg-transparent text-on-green-soft' }}">
                                <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2-1.55-2-3.45-2.45.7a7.6 7.6 0 0 0-1.75-1.05L14.8 3h-4l-.45 2.65a7.6 7.6 0 0 0-1.75 1.05l-2.45-.7-2 3.45L6.15 11a7.8 7.8 0 0 0 .05 2l-2 1.55 2 3.45 2.45-.7c.53.43 1.12.79 1.75 1.05L10.8 21h4l.45-2.65a7.6 7.6 0 0 0 1.75-1.05l2.45.7 2-3.45-2.05-1.55zM12 15.3A3.3 3.3 0 1 1 12 8.7a3.3 3.3 0 0 1 0 6.6z"/></svg>
                            </span>
                            <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Settings</span>
                        </a>
                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col">
                    <header class="sticky top-0 z-30 border-b border-hairline bg-paper/90 py-3 backdrop-blur-md">
                        <div class="app-frame flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" @click="toggleSidebar()"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-green-deep transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green"
                                    :aria-pressed="pinned ? 'true' : 'false'" aria-label="Toggle navigation">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                            </button>
                            <p class="layout-title">{{ $pageTitle }}</p>
                            @if($roleLabel)
                                <span class="hidden sm:inline-flex shrink-0 items-center rounded-full bg-green-wash px-2.5 py-0.5 text-xs font-semibold text-green-deep">{{ str_replace('_', ' ', ucwords($roleLabel, '_')) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
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

                    <main class="flex-1 pb-10 pt-2">
                        <div class="app-frame">
                            {{ $slot }}
                        </div>
                    </main>
                </div>
            </div>
        @endif
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
</body>
</html>
