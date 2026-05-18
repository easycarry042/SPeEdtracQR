@props([
    'label' => '',
    'value' => 0,
    'icon' => 'list',
])

@php
    $icons = [
        'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />',
        'hourglass' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6V3.75m0 16.5V18M4.5 5.25h15a.75.75 0 0 1 .54 1.28l-4.5 4.85a.75.75 0 0 0 0 1.04l4.5 4.85a.75.75 0 0 1-.54 1.28h-15a.75.75 0 0 1-.54-1.28l4.5-4.85a.75.75 0 0 0 0-1.04l-4.5-4.85A.75.75 0 0 1 4.5 5.25Z" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />',
    ];
    $path = $icons[$icon] ?? $icons['list'];
@endphp

<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-2xl border border-gray-200/90 bg-white p-6 shadow-md shadow-gray-200/60 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-900/10']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold leading-tight text-emerald-900">{{ $label }}</p>
            <p class="mt-3 text-4xl font-bold tracking-tight text-gray-900 transition-transform duration-300 group-hover:scale-[1.02]">{{ $value }}</p>
        </div>
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-emerald-800 transition-colors duration-300 group-hover:bg-emerald-100">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">{!! $path !!}</svg>
        </div>
    </div>
</div>
