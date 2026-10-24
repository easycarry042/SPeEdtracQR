{{-- Sidebar navigation, shared by every signed-in role. System admins get the
     configuration set; everyone else gets the operational set that used to live
     in the topnav. Expects $isSystemAdmin, $isSupervisor, $homeRoute and
     $homeActive from the layout. --}}
@php
    /**
     * One nav row's attributes. The active row takes a lighter panel plus a
     * brass marker flush to the sidebar's edge (.sb-row.is-active), so the
     * current page reads at a glance and never by colour alone, and is named
     * to screen readers with aria-current. data-soft-nav-link lets the row
     * swap only the main area instead of reloading the page.
     *
     * @param  bool  $active
     */
    $row = fn (bool $active): string => 'class="nav-link sb-row'.($active ? ' is-active' : '').'"'
        .($active ? ' aria-current="page"' : '')
        .' data-soft-nav-link';

    $glyph = 'sb-glyph';
    $label = 'nav-text whitespace-nowrap';

    /*
     * The count of requests that have come to this person (App\Support\NavBadges).
     * Rendered fresh with every page — the sidebar is swapped on each soft
     * navigation — and kept current in between by the layout's poller, which
     * broadcasts `nav-badges` every minute.
     */
    $badges = \App\Support\NavBadges::for(auth()->user());
    $badge = fn (string $key, string $what): string => '<span class="sb-badge" x-data="{ n: '.$badges[$key].' }"'
        .' @nav-badges.window="n = $event.detail.'.$key.'" x-show="n > 0"'.($badges[$key] > 0 ? '' : ' x-cloak')
        .' x-text="n > 99 ? \'99+\' : n" aria-live="polite">'.$badges[$key].'</span>'
        .'<span class="sr-only" x-data="{ n: '.$badges[$key].' }" @nav-badges.window="n = $event.detail.'.$key.'"'
        .' x-text="n > 0 ? \' (\' + n + \' '.$what.')\' : \'\'"></span>';
@endphp

@if($isSystemAdmin)
    <a href="{{ route('admin.dashboard') }}" {!! $row(request()->routeIs('admin.dashboard')) !!}>
        <span class="{{ $glyph }}">
            <img src="{{ asset('images/staff/nav-home.svg') }}" alt="" class="sb-icon">
        </span>
        <span class="{{ $label }}">Dashboard</span>
    </a>
    {{-- Analytics is folded into the command-center Dashboard for super admins.
         History moved into the account menu (header-actions). The Staff
         directory was folded into Users (same people, now with their
         workload), and the Services report into the Dashboard and Resources. --}}
    {{-- Every citizen request and where it stands. --}}
    <a href="{{ route('admin.requests.index') }}" {!! $row(request()->routeIs('admin.requests*')) !!}>
        <span class="{{ $glyph }}">
            <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 12h6M9 16h4"/></svg>
        </span>
        <span class="{{ $label }}">Requests</span>
    </a>
    @can('manage users')
        <a href="{{ route('admin.users.index') }}" {!! $row(request()->routeIs('admin.users*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </span>
            <span class="{{ $label }}">Users</span>
        </a>
    @endcan
    {{-- Assignments + Bookings intentionally omitted: assignment is handled
         inline on the command-center dashboard, and Bookings is owned by staff. --}}
    @can('manage system')
        <a href="{{ route('requests.index') }}" {!! $row(request()->routeIs('requests.*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
            </span>
            <span class="{{ $label }}">Internal Requests</span>
        </a>
        {{-- The same Resources page staff and supervisors run (lending desk,
             stock, facilities), plus the catalog controls only a super admin
             has: add, edit, deactivate, delete. --}}
        <a href="{{ route('inventory.index') }}" {!! $row(request()->routeIs('inventory*') || request()->routeIs('admin.resources*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            <span class="{{ $label }}">Resources</span>
        </a>
        <a href="{{ route('admin.audit-log.index') }}" {!! $row(request()->routeIs('admin.audit-log*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M7 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </span>
            <span class="{{ $label }}">Audit Log</span>
        </a>

        {{-- Configuration, visited when something is being set up rather
             than every day — grouped under its own heading. --}}
        <p class="sb-section sb-subsection">Setup</p>
        <a href="{{ route('admin.departments.index') }}" {!! $row(request()->routeIs('admin.departments*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V7l8-4 8 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1"/></svg>
            </span>
            <span class="{{ $label }}">Departments</span>
        </a>
        <a href="{{ route('admin.route-templates.index') }}" {!! $row(request()->routeIs('admin.route-templates*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5a2 2 0 11-4 0 2 2 0 014 0zm14 14a2 2 0 11-4 0 2 2 0 014 0zM7 5h9a3 3 0 013 3v1m-2 10H8a3 3 0 01-3-3v-1m0-4v2m14-6v2"/></svg>
            </span>
            <span class="{{ $label }}">Route Templates</span>
        </a>
        <a href="{{ route('admin.request-types.index') }}" {!! $row(request()->routeIs('admin.request-types*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </span>
            <span class="{{ $label }}">Request Types</span>
        </a>
    @endcan
@else
    <a href="{{ $homeRoute }}" {!! $row($homeActive) !!}>
        <span class="{{ $glyph }}">
            <img src="{{ asset('images/staff/nav-home.svg') }}" alt="" class="sb-icon">
        </span>
        <span class="{{ $label }}">Dashboard</span>
        {!! $badge('home', 'new') !!}
    </a>

    {{-- No "Look Up" row: the desk it opened was a second copy of this role's
         own dashboard queue, and the two screens each had half the tools for
         one request. The dashboard carries both halves now. The QR hub it also
         served is still a click away on the header's Scan QR Code button. --}}

    @if(auth()->user()?->canAny(['act on internal requests', 'create internal requests']) ?? false)
        <a href="{{ route('requests.index') }}" {!! $row(request()->routeIs('requests.*')) !!}>
            <span class="{{ $glyph }}">
                <img src="{{ asset('images/staff/nav-internal.svg') }}" alt="" class="sb-icon">
            </span>
            <span class="{{ $label }}">Internal</span>
            {!! $badge('internal', 'waiting') !!}
        </a>
    @endif

    @can('manage bookings')
        <a href="{{ route('bookings.index') }}" {!! $row(request()->routeIs('bookings*')) !!}>
            <span class="{{ $glyph }}">
                <img src="{{ asset('images/staff/nav-calendar.svg') }}" alt="" class="sb-icon">
            </span>
            <span class="{{ $label }}">Booking</span>
        </a>
    @endcan

    {{-- Resources, next to Booking: the calendar says when a thing is taken,
         this says how much of it there is. Staff and supervisors both have it
         (the team asked for it back); it is meant to grow into the office's
         inventory management. --}}
    @can('manage inventory')
        <a href="{{ route('inventory.index') }}" {!! $row(request()->routeIs('inventory*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            <span class="{{ $label }}">Resources</span>
            {!! $badge('resources', 'waiting') !!}
        </a>
    @endcan

    {{-- One Reports row: the office's numbers on the command-center page
         (citizen / internal sides). It replaced Analytics and the Services
         report, whose figures disagreed with the dashboard. --}}
    @can('view reports')
        <a href="{{ route('reports.index') }}" {!! $row(request()->routeIs('reports.*') || request()->routeIs('analytics*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
            </span>
            <span class="{{ $label }}">Reports</span>
        </a>
    @endcan

    {{-- No Users link: only super_admin can manage users, and they get the
         configuration set above. No Assignments link either: the dashboard's
         Unassigned / In Progress / Completed tabs and its Review modal
         (assign, reassign) replaced that page. --}}
@endif
