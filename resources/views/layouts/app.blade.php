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
    $user?->loadMissing('department', 'roles');
    $name = $user->name ?? 'User';
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(
        count($parts) >= 2
            ? mb_substr($parts[0], 0, 1).mb_substr(end($parts), 0, 1)
            : mb_substr($name, 0, 2)
    );
    $departmentName = $user?->department?->name;
    $roleLabel = $user?->roles->first()?->name;
    $roleLabel = $roleLabel ? str_replace('_', ' ', ucwords($roleLabel, '_')) : null;
    $pageTitle = match (true) {
        request()->routeIs('dashboard'), request()->routeIs('admin.dashboard') => 'Dashboard',
        request()->routeIs('analytics*') => 'Analytics',
        request()->routeIs('track.*') => 'Track Document',
        request()->routeIs('scan.*') => 'Scan',
        request()->routeIs('history*') => 'History',
        request()->routeIs('movements*') => 'Movements',
        request()->routeIs('admin.users*') => 'Users',
        request()->routeIs('admin.departments*') => 'Departments',
        request()->routeIs('admin.audit-log*') => 'Audit Log',
        request()->routeIs('profile.*') => 'Settings',
        request()->routeIs('documents.*') => 'Documents',
        default => config('app.name', 'SPeED TraQR'),
    };
@endphp
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">
    <div class="flex min-h-screen"
         x-data="{ pinned: (localStorage.getItem('sidebarPinned') ?? '1') === '1' }"
         x-init="$watch('pinned', v => localStorage.setItem('sidebarPinned', v ? '1' : '0'))">
        @auth
            {{-- Sidebar: pinned (expanded, default) shows labels; unpinned is an icon rail
                 that still expands on hover for a quick peek. Toggle lives in the header. --}}
            <aside :class="pinned ? 'sidebar-pinned' : ''"
                   class="group sticky top-0 z-40 flex h-screen w-[4.5rem] shrink-0 flex-col overflow-hidden border-r border-emerald-200/80 nav-bar transition-[width] duration-300 ease-out hover:w-64 hover:shadow-[4px_0_24px_-4px_rgba(20,83,45,0.15)]">
                <div class="nav-brand flex h-[4.25rem] shrink-0 items-center justify-center gap-0 border-b border-emerald-200/60 px-1 transition-all duration-300 ease-out group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR Logo" class="nav-icon">
                    <div class="nav-text min-w-0 max-w-0 overflow-hidden opacity-0 transition-all duration-300 ease-out group-hover:max-w-[200px] group-hover:opacity-100">
                        <p class="truncate whitespace-nowrap text-base font-extrabold tracking-tight text-emerald-950">
                            SPeED <span class="font-bold text-emerald-800">TraQR</span>
                        </p>
                    </div>
                </div>

                <nav class="nav-scroll flex flex-1 flex-col gap-1 overflow-y-auto overflow-x-hidden px-1 py-4 transition-[padding] duration-300 ease-out group-hover:px-2">
                    @php
                        $isSystemAdmin = $user?->can('manage system') ?? false;
                        $dashboardRoute = $isSystemAdmin ? route('admin.dashboard') : route('dashboard');
                        $dashboardActive = $isSystemAdmin
                            ? request()->routeIs('admin.dashboard')
                            : request()->routeIs('dashboard');
                    @endphp
                    <a href="{{ $dashboardRoute }}" class="{{ $dashboardActive ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $dashboardActive ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Dashboard</span>
                    </a>
                    @can('view reports')
                    <a href="{{ route('analytics') }}" class="{{ request()->routeIs('analytics*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('analytics*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Analytics</span>
                    </a>
                    @endcan
                    @can('scan documents')
                    @unless($isSystemAdmin)
                    <a href="{{ route('track.index') }}" class="{{ request()->routeIs('track.*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('track.*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 4l13 8-13 8V4z"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Track Document</span>
                    </a>
                    <a href="{{ route('scan.index') }}" class="{{ request()->routeIs('scan.*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('scan.*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 19H3a2 2 0 01-2-2v-2m8-4h.01M12 12h.01M16 12h.01M8 12h.01"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Scan</span>
                    </a>
                    @endunless
                    @endcan
                    <a href="{{ route('history') }}" class="{{ request()->routeIs('history*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('history*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">History</span>
                    </a>
                    <a href="{{ route('movements.index') }}" class="{{ request()->routeIs('movements*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('movements*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h11M3 14h7m7-8l4 4-4 4"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Movements</span>
                    </a>

                    @can('manage users')
                    <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.users*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Users</span>
                    </a>
                    @endcan
                    @can('manage system')
                    <a href="{{ route('admin.departments.index') }}" class="{{ request()->routeIs('admin.departments*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.departments*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3L2 9v2h20V9L12 3zM4 13v5h3v-5H4zm5 0v5h3v-5H9zm5 0v5h3v-5h-3zm5 0v5h-2v-5h2zm-15 7h16v2H4v-2z"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Departments</span>
                    </a>
                    <a href="{{ route('admin.audit-log.index') }}" class="{{ request()->routeIs('admin.audit-log*') ? 'bg-emerald-600/15 text-emerald-950 shadow-sm ring-1 ring-emerald-600/20' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('admin.audit-log*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M7 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Audit Log</span>
                    </a>
                    @endcan
                </nav>

                <div class="shrink-0 border-t border-emerald-200/60 p-1 transition-[padding] duration-300 ease-out group-hover:p-2">
                    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'bg-emerald-600/15 text-emerald-950' : 'text-emerald-900 hover:bg-emerald-600/10' }} nav-link flex w-full items-center justify-center gap-0 rounded-xl py-3 pl-0 pr-0 transition-all duration-200 group-hover:justify-start group-hover:gap-3 group-hover:px-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ request()->routeIs('profile.*') ? 'text-emerald-600' : 'bg-transparent text-emerald-800' }}">
                            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2-1.55-2-3.45-2.45.7a7.6 7.6 0 0 0-1.75-1.05L14.8 3h-4l-.45 2.65a7.6 7.6 0 0 0-1.75 1.05l-2.45-.7-2 3.45L6.15 11a7.8 7.8 0 0 0 .05 2l-2 1.55 2 3.45 2.45-.7c.53.43 1.12.79 1.75 1.05L10.8 21h4l.45-2.65a7.6 7.6 0 0 0 1.75-1.05l2.45.7 2-3.45-2.05-1.55zM12 15.3A3.3 3.3 0 1 1 12 8.7a3.3 3.3 0 0 1 0 6.6z"/></svg>
                        </span>
                        <span class="nav-text max-w-0 overflow-hidden whitespace-nowrap text-sm font-semibold opacity-0 transition-all duration-300 ease-out group-hover:max-w-[240px] group-hover:opacity-100">Settings</span>
                    </a>
                </div>
            </aside>
        @endauth

        <div class="flex min-w-0 flex-1 flex-col">
            @auth
                <header class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-[#f1f2f1]/90 px-4 py-3 backdrop-blur-md sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <p class="truncate text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">{{ $pageTitle }}</p>
                        @if($departmentName)
                            <span class="inline-flex max-w-[14rem] shrink-0 items-center truncate rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900 sm:max-w-xs" title="Your department">
                                {{ $departmentName }}
                            </span>
                        @elseif($roleLabel && ($user?->can('manage system') ?? false))
                            <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">All departments</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                    {{-- Only on the Users tab; the page itself is already gated by "manage users".
                         Opens the modal on the page; falls back to the create page without JS. --}}
                    @if(request()->routeIs('admin.users.index'))
                    <a href="{{ route('admin.users.create') }}"
                       onclick="if (window.openAddUserModal) { event.preventDefault(); openAddUserModal(); }"
                       class="inline-flex h-11 items-center gap-2 rounded-full bg-emerald-700 px-4 text-sm font-bold text-white shadow-sm ring-1 ring-emerald-600/30 transition hover:scale-105 hover:bg-emerald-800 hover:shadow-md active:scale-95">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Add User
                    </a>
                    @endif

                    {{-- Only on the Departments tab; gated by "manage system" on the route --}}
                    @if(request()->routeIs('admin.departments.index'))
                    <a href="{{ route('admin.departments.create') }}"
                       onclick="if (window.openAddDepartmentModal) { event.preventDefault(); openAddDepartmentModal(); }"
                       class="inline-flex h-11 items-center gap-2 rounded-full bg-emerald-700 px-4 text-sm font-bold text-white shadow-sm ring-1 ring-emerald-600/30 transition hover:scale-105 hover:bg-emerald-800 hover:shadow-md active:scale-95">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Add Department
                    </a>
                    @endif
                    @if($showCreateDocumentModal ?? false)
                    <button type="button" onclick="openCreateDocumentModal()" class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-200/90 text-emerald-900 shadow-sm ring-1 ring-emerald-300/40 transition hover:scale-105 hover:bg-emerald-300/90 hover:shadow-md active:scale-95" title="New document">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6M9 14h6"/>
                        </svg>
                    </button>
                    @endif

                    {{-- Notification dropdown --}}
                    <div class="relative" id="notifDropdown">
                        <button type="button"
                                id="notifBtn"
                                onclick="toggleHeaderDropdown('notifPanel', 'profilePanel')"
                                class="relative flex h-11 w-11 items-center justify-center rounded-full bg-emerald-200/90 text-emerald-900 shadow-sm ring-1 ring-emerald-300/40 transition hover:scale-105 hover:bg-emerald-300/90 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                                title="Notifications"
                                aria-haspopup="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2z"/></svg>
                            @if(($headerNotifications ?? collect())->isNotEmpty())
                                <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $headerNotifications->count() }}</span>
                            @endif
                        </button>
                        <div id="notifPanel"
                             class="dropdown-panel hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white py-2 shadow-xl shadow-gray-900/15"
                             style="z-index:9999;">
                            <p class="border-b border-gray-100 px-4 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Notifications</p>
                            @forelse($headerNotifications ?? [] as $notification)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}" class="border-b border-gray-50 last:border-0">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="w-full px-4 py-3 text-left hover:bg-emerald-50">
                                        <p class="text-sm font-semibold text-gray-900">{{ $notification->message }}</p>
                                        @if($notification->document)
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $notification->document->document_type }}</p>
                                        @endif
                                        <p class="mt-1 text-[11px] text-emerald-700">Tap to open inbox</p>
                                    </button>
                                </form>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-gray-500">You&apos;re all caught up — no new notifications.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Profile dropdown --}}
                    <div class="relative" id="profileDropdown">
                        <button type="button"
                                id="profileBtn"
                                onclick="toggleHeaderDropdown('profilePanel', 'notifPanel')"
                                class="inline-flex items-center gap-2 rounded-full bg-emerald-200/90 py-1.5 pl-1.5 pr-3 text-emerald-950 shadow-sm ring-1 ring-emerald-300/40 transition hover:bg-emerald-300/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
                                aria-haspopup="true">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">{{ $initials }}</span>
                            <svg class="h-4 w-4 text-emerald-900/70" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="profilePanel"
                             class="dropdown-panel hidden absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-gray-100 py-1 shadow-xl shadow-gray-900/10"
                             style="z-index:9999;">
                            <div class="border-b border-gray-200 px-4 py-3">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $name }}</p>
                                @if($departmentName)
                                    <p class="mt-0.5 text-xs text-emerald-700">{{ $departmentName }}</p>
                                @endif
                                @if($roleLabel)
                                    <p class="text-xs text-gray-500">{{ $roleLabel }}</p>
                                @endif
                            </div>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">Manage Profile</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50">Logout</button>
                            </form>
                        </div>
                    </div>

                    <script>
                        function toggleHeaderDropdown(showId, hideId) {
                            const show = document.getElementById(showId);
                            const hide = document.getElementById(hideId);
                            if (hide) hide.classList.add('hidden');
                            if (show) show.classList.toggle('hidden');
                        }

                        document.addEventListener('click', function (e) {
                            ['notifDropdown', 'profileDropdown'].forEach(function (wrapperId) {
                                const wrapper = document.getElementById(wrapperId);
                                if (wrapper && !wrapper.contains(e.target)) {
                                    const panel = wrapper.querySelector('.dropdown-panel');
                                    if (panel) panel.classList.add('hidden');
                                }
                            });
                        });
                    </script>
                    </div>{{-- end flex items-center gap-3 --}}
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

    @if($showCreateDocumentModal ?? false)
        @include('documents.partials.create-modal')
    @endif

    <x-image-view-modal />
</body>
</html>
