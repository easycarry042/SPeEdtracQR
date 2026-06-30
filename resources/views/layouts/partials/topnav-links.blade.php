@php
    $link = fn (bool $active) => $active ? 'topnav-link on' : 'topnav-link';
@endphp

<a href="{{ $homeRoute }}" class="{{ $link($homeActive) }}">
    @if($isSupervisor)
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 3 3 10v10a1 1 0 0 0 1 1h6v-7h4v7h6a1 1 0 0 0 1-1V10l-9-7z"/></svg>
        Dashboard
    @else
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0"/></svg>
        My Profile
    @endif
</a>

@can('view reports')
<a href="{{ route('analytics') }}" class="{{ $link(request()->routeIs('analytics*')) }}">
    <svg fill="currentColor" viewBox="0 0 24 24"><rect x="3" y="11" width="4" height="10" rx="1"/><rect x="10" y="6" width="4" height="15" rx="1"/><rect x="17" y="3" width="4" height="18" rx="1"/></svg>
    Analytics
</a>
@endcan

@can('scan documents')
<a href="{{ route('track.index') }}" class="{{ $link(request()->routeIs('track.*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M5 4l13 8-13 8V4z"/></svg>
    Track
</a>
<a href="{{ route('scan.index') }}" class="{{ $link(request()->routeIs('scan.*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2m0 10v2a2 2 0 01-2 2h-2M5 19H3a2 2 0 01-2-2v-2m8-4h.01M12 12h.01M16 12h.01M8 12h.01"/></svg>
    Scan
</a>
@endcan

<a href="{{ route('history') }}" class="{{ $link(request()->routeIs('history*')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
    History
</a>

@if($isSupervisor)
    <a href="{{ route('staff.profile', ['user' => auth()->id()]) }}" class="{{ $link(request()->routeIs('staff.profile')) }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0"/></svg>
        My Profile
    </a>
@endif

@can('manage users')
<a href="{{ route('admin.users.index') }}" class="{{ $link(request()->routeIs('admin.users*')) }}">
    <svg fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
    Users
</a>
@endcan

@can('assign documents')
<a href="{{ route('admin.assignments.index') }}" class="{{ $link(request()->routeIs('admin.assignments.index')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
    Assignments
</a>
<a href="{{ route('admin.assignments.unclaimed') }}" class="{{ $link(request()->routeIs('admin.assignments.unclaimed')) }}">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
    Unclaimed
</a>
@endcan
