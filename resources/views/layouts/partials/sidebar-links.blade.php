{{-- Sidebar navigation, shared by every signed-in role. System admins get the
     configuration set; everyone else gets the operational set that used to live
     in the topnav. Expects $isSystemAdmin, $isSupervisor, $homeRoute and
     $homeActive from the layout. --}}
@php
    /**
     * One nav row. The active row takes a lighter panel plus a brass marker
     * flush to the sidebar's edge (hence -left-3, clearing the nav's px-3), so
     * the current page reads at a glance and never by colour alone.
     *
     * @param  bool  $active
     */
    $row = fn (bool $active): string => 'nav-link relative flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-colors duration-200 '
        .($active
            ? "bg-white/15 font-bold text-white before:absolute before:-left-3 before:top-1/2 before:h-9 before:w-1.5 before:-translate-y-1/2 before:rounded-r-full before:bg-brass before:content-['']"
            : 'font-semibold text-white/85 hover:bg-white/5 hover:text-white');

    $glyph = 'flex h-10 w-10 shrink-0 items-center justify-center';
    $label = 'nav-text whitespace-nowrap text-sm';
@endphp

@if($isSystemAdmin)
    <a href="{{ route('admin.dashboard') }}" class="{{ $row(request()->routeIs('admin.dashboard')) }}">
        <span class="{{ $glyph }}">
            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
        </span>
        <span class="{{ $label }}">Dashboard</span>
    </a>
    {{-- Analytics is folded into the command-center Dashboard for super admins.
         History moved into the account menu (header-actions). --}}
    <a href="{{ route('staff.index') }}" class="{{ $row(request()->routeIs('staff.index')) }}">
        <span class="{{ $glyph }}">
            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H2v-2a4 4 0 0 1 3-3.87m10-2.13a4 4 0 1 0-6 0M15 7a3 3 0 1 1 4 2.83"/></svg>
        </span>
        <span class="{{ $label }}">Staff</span>
    </a>
    @can('manage users')
        <a href="{{ route('admin.users.index') }}" class="{{ $row(request()->routeIs('admin.users*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </span>
            <span class="{{ $label }}">Users</span>
        </a>
    @endcan
    {{-- Assignments + Bookings intentionally omitted: assignment is handled
         inline on the command-center dashboard, and Bookings is owned by staff. --}}
    @can('view reports')
        <a href="{{ route('reports.services') }}" class="{{ $row(request()->routeIs('reports.services')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </span>
            <span class="{{ $label }}">Services report</span>
        </a>
    @endcan
    @can('manage system')
        <a href="{{ route('requests.index') }}" class="{{ $row(request()->routeIs('requests.*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
            </span>
            <span class="{{ $label }}">Internal Requests</span>
        </a>
        <a href="{{ route('admin.departments.index') }}" class="{{ $row(request()->routeIs('admin.departments*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V7l8-4 8 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1"/></svg>
            </span>
            <span class="{{ $label }}">Departments</span>
        </a>
        <a href="{{ route('admin.route-templates.index') }}" class="{{ $row(request()->routeIs('admin.route-templates*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5a2 2 0 11-4 0 2 2 0 014 0zm14 14a2 2 0 11-4 0 2 2 0 014 0zM7 5h9a3 3 0 013 3v1m-2 10H8a3 3 0 01-3-3v-1m0-4v2m14-6v2"/></svg>
            </span>
            <span class="{{ $label }}">Route Templates</span>
        </a>
        <a href="{{ route('admin.request-types.index') }}" class="{{ $row(request()->routeIs('admin.request-types*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            </span>
            <span class="{{ $label }}">Request Types</span>
        </a>
        <a href="{{ route('admin.resources.index') }}" class="{{ $row(request()->routeIs('admin.resources*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </span>
            <span class="{{ $label }}">Resources</span>
        </a>
        <a href="{{ route('admin.audit-log.index') }}" class="{{ $row(request()->routeIs('admin.audit-log*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M7 4H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </span>
            <span class="{{ $label }}">Audit Log</span>
        </a>
    @endcan
@else
    <a href="{{ $homeRoute }}" class="{{ $row($homeActive) }}">
        <span class="{{ $glyph }}">
            <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
        </span>
        <span class="{{ $label }}">Dashboard</span>
    </a>

    <a href="{{ route('track.index') }}" class="{{ $row(request()->routeIs('track.*') || request()->routeIs('scan.*')) }}">
        <span class="{{ $glyph }}">
            <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
        </span>
        <span class="{{ $label }}">Look Up</span>
    </a>

    @canany(['act on internal requests', 'create internal requests'])
        <a href="{{ route('requests.index') }}" class="{{ $row(request()->routeIs('requests.*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
            </span>
            <span class="{{ $label }}">Internal</span>
        </a>
    @endcanany

    @can('manage bookings')
        <a href="{{ route('bookings.index') }}" class="{{ $row(request()->routeIs('bookings*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <span class="{{ $label }}">Booking</span>
        </a>
    @endcan

    @can('view reports')
        <a href="{{ route('analytics') }}" class="{{ $row(request()->routeIs('analytics*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
            </span>
            <span class="{{ $label }}">Analytics</span>
        </a>
        <a href="{{ route('reports.services') }}" class="{{ $row(request()->routeIs('reports.services')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </span>
            <span class="{{ $label }}">Services</span>
        </a>
    @endcan

    {{-- No Users link: only super_admin can manage users, and they get the
         configuration set above. --}}
    @can('assign documents')
        <a href="{{ route('admin.assignments.index') }}" class="{{ $row(request()->routeIs('admin.assignments*')) }}">
            <span class="{{ $glyph }}">
                <svg class="h-[25px] w-[25px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </span>
            <span class="{{ $label }}">Assignments</span>
        </a>
    @endcan
@endif
