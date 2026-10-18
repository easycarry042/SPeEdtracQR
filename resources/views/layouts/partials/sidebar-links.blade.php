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
        <a href="{{ route('admin.resources.index') }}" {!! $row(request()->routeIs('admin.resources*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </span>
            <span class="{{ $label }}">Resources</span>
        </a>
    @endcan
@else
    <a href="{{ $homeRoute }}" {!! $row($homeActive) !!}>
        <span class="{{ $glyph }}">
            <img src="{{ asset('images/staff/nav-home.svg') }}" alt="" class="sb-icon">
        </span>
        <span class="{{ $label }}">Dashboard</span>
    </a>

    {{-- No "Look Up" row: the desk it opened was a second copy of this role's
         own dashboard queue, and the two screens each had half the tools for
         one request. The dashboard carries both halves now. The QR hub it also
         served is still a click away on the header's Scan QR Code button. --}}

    @canany(['act on internal requests', 'create internal requests'])
        <a href="{{ route('requests.index') }}" {!! $row(request()->routeIs('requests.*')) !!}>
            <span class="{{ $glyph }}">
                <img src="{{ asset('images/staff/nav-internal.svg') }}" alt="" class="sb-icon">
            </span>
            <span class="{{ $label }}">Internal</span>
        </a>
    @endcanany

    @can('manage bookings')
        <a href="{{ route('bookings.index') }}" {!! $row(request()->routeIs('bookings*')) !!}>
            <span class="{{ $glyph }}">
                <img src="{{ asset('images/staff/nav-calendar.svg') }}" alt="" class="sb-icon">
            </span>
            <span class="{{ $label }}">Booking</span>
        </a>
    @endcan

    {{-- Next to Booking: the calendar says when a thing is taken, Inventory
         says how much of it there is. Staff-owned for the same reason. --}}
    @can('manage inventory')
        <a href="{{ route('inventory.index') }}" {!! $row(request()->routeIs('inventory*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </span>
            <span class="{{ $label }}">Inventory</span>
        </a>
    @endcan

    @can('view reports')
        <a href="{{ route('analytics') }}" {!! $row(request()->routeIs('analytics*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
            </span>
            <span class="{{ $label }}">Analytics</span>
        </a>
        <a href="{{ route('reports.services') }}" {!! $row(request()->routeIs('reports.services')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </span>
            <span class="{{ $label }}">Services</span>
        </a>
    @endcan

    {{-- No Users link: only super_admin can manage users, and they get the
         configuration set above. --}}
    @can('assign documents')
        <a href="{{ route('admin.assignments.index') }}" {!! $row(request()->routeIs('admin.assignments*')) !!}>
            <span class="{{ $glyph }}">
                <svg class="sb-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </span>
            <span class="{{ $label }}">Assignments</span>
        </a>
    @endcan
@endif
