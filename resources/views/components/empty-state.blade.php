@props([
    'title' => 'Nothing here yet',
    'icon' => 'inbox',
])
{{-- Teaching empty state (Civic Record). Pass a plain-language sentence as the
     default slot explaining what this list holds and how to fill it, and an
     optional `action` slot for a primary next step. --}}
@php
    $icons = [
        'inbox'    => 'M3 13h4l1.5 3h7L17 13h4M5 5h14l1 8v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4L5 5z',
        'users'    => 'M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H2v-2a4 4 0 0 1 3-3.87m10-2.13a4 4 0 1 0-6 0M15 7a3 3 0 1 1 4 2.83',
        'search'   => 'M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z',
        'document' => 'M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM14 3v4h4',
        'clock'    => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    ];
    $path = $icons[$icon] ?? $icons['inbox'];
@endphp
<div {{ $attributes->merge(['class' => 'cr-empty']) }}>
    <span class="cr-empty-ico" aria-hidden="true">
        <svg fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
    </span>
    <p class="cr-empty-title">{{ $title }}</p>
    @if(trim($slot))
        <p class="cr-empty-msg">{{ $slot }}</p>
    @endif
    @isset($action)
        <div class="cr-empty-action">{{ $action }}</div>
    @endisset
</div>
