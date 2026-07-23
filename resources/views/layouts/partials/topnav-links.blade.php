@php
    $link = fn (bool $active) => $active ? 'topnav-link on' : 'topnav-link';
@endphp

<a href="{{ $homeRoute }}" class="{{ $link($homeActive) }}">
    @if($isSupervisor)
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
        Dashboard
    @else
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Requests
    @endif
</a>

@can('view reports')
<a href="{{ route('analytics') }}" class="{{ $link(request()->routeIs('analytics*')) }}">
    <svg fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
    Analytics
</a>
@endcan

<a href="{{ route('track.index') }}" class="{{ $link(request()->routeIs('track.*') || request()->routeIs('scan.*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
    Look up
</a>

{{-- No Users link here: only super_admin can manage users, and they get the
     sidebar shell (never this topnav) — the link was dead weight. --}}

@can('assign documents')
<a href="{{ route('admin.assignments.index') }}" class="{{ $link(request()->routeIs('admin.assignments*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    Assignments
</a>
@endcan
