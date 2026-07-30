@php
    $link = fn (bool $active) => $active ? 'topnav-link on' : 'topnav-link';

    // Administrator configuration pages. Grouped behind one "Setup" menu so the
    // navbar stays readable — a flat row of eleven links was the reason the super
    // admin needed a sidebar in the first place.
    // Each entry carries the permission that gates its page, so this menu can
    // never advertise a destination the user would be 403'd from.
    $setupLinks = collect([
        ['label' => 'Departments', 'route' => 'admin.departments.index', 'pattern' => 'admin.departments*', 'can' => 'manage system'],
        ['label' => 'Request Types', 'route' => 'admin.request-types.index', 'pattern' => 'admin.request-types*', 'can' => 'manage system'],
        ['label' => 'Resources', 'route' => 'admin.resources.index', 'pattern' => 'admin.resources*', 'can' => 'manage system'],
        ['label' => 'Route Templates', 'route' => 'admin.route-templates.index', 'pattern' => 'admin.route-templates*', 'can' => 'manage system'],
        ['label' => 'Users', 'route' => 'admin.users.index', 'pattern' => 'admin.users*', 'can' => 'manage users'],
        ['label' => 'Audit Log', 'route' => 'admin.audit-log.index', 'pattern' => 'admin.audit-log*', 'can' => 'manage system'],
        ['label' => 'System health', 'route' => 'health', 'pattern' => 'health', 'can' => 'manage system'],
    ])->filter(fn (array $item): bool => \Illuminate\Support\Facades\Route::has($item['route'])
        && ($user?->can($item['can']) ?? false));

    $setupActive = $setupLinks->contains(fn (array $item): bool => request()->routeIs($item['pattern']));
@endphp

{{-- Every role's home tab is "Dashboard", so the vocabulary matches across the
     product. For staff that page is their profile, which carries the requests
     assigned to them; My Profile also stays in the account menu. --}}
<a href="{{ $homeRoute }}" class="{{ $link($homeActive) }}">
    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
    Dashboard
</a>

@can('view reports')
<a href="{{ route('analytics') }}" class="{{ $link(request()->routeIs('analytics*')) }}">
    <svg fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
    Analytics
</a>
<a href="{{ route('reports.services') }}" class="{{ $link(request()->routeIs('reports.services')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
    Services
</a>
@endcan

<a href="{{ route('track.index') }}" class="{{ $link(request()->routeIs('track.*') || request()->routeIs('scan.*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
    Requests
</a>

@canany(['act on internal requests', 'create internal requests'])
<a href="{{ route('requests.index') }}" class="{{ $link(request()->routeIs('requests.*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
    Internal
</a>
@endcanany

@can('assign documents')
<a href="{{ route('admin.assignments.index') }}" class="{{ $link(request()->routeIs('admin.assignments*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    Assignments
</a>
@endcan

@can('manage bookings')
<a href="{{ route('bookings.index') }}" class="{{ $link(request()->routeIs('bookings*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    Bookings
</a>
@endcan

@can('manage users')
<a href="{{ route('staff.index') }}" class="{{ $link(request()->routeIs('staff.index')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H2v-2a4 4 0 0 1 3-3.87m10-2.13a4 4 0 1 0-6 0M15 7a3 3 0 1 1 4 2.83"/></svg>
    Staff
</a>
@endcan

@if($setupLinks->isNotEmpty())
<div class="relative" x-data="{ setupOpen: false }" @keydown.escape="setupOpen = false">
    <button type="button" class="{{ $link($setupActive) }}" @click="setupOpen = !setupOpen"
            :aria-expanded="setupOpen ? 'true' : 'false'" aria-haspopup="true">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2-1.55-2-3.45-2.45.7a7.6 7.6 0 0 0-1.75-1.05L14.8 3h-4l-.45 2.65a7.6 7.6 0 0 0-1.75 1.05l-2.45-.7-2 3.45L6.15 11a7.8 7.8 0 0 0 .05 2l-2 1.55 2 3.45 2.45-.7c.53.43 1.12.79 1.75 1.05L10.8 21h4l.45-2.65a7.6 7.6 0 0 0 1.75-1.05l2.45.7 2-3.45-2.05-1.55zM12 15.3A3.3 3.3 0 1 1 12 8.7a3.3 3.3 0 0 1 0 6.6z"/></svg>
        Setup
    </button>
    <div x-show="setupOpen" x-cloak x-transition.opacity @click.outside="setupOpen = false"
         class="absolute right-0 z-[80] mt-2 w-52 overflow-hidden rounded-xl border border-hairline bg-paper py-1 shadow-xl">
        @foreach($setupLinks as $item)
            <a href="{{ route($item['route']) }}"
               class="block px-4 py-2 text-sm font-medium {{ request()->routeIs($item['pattern']) ? 'bg-green-wash text-green-deep' : 'text-ink hover:bg-green-wash/60' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</div>
@endif
