@props(['status' => 'pending'])

@php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $map = [
        'in_progress' => ['class' => 'bg-amber-100 text-amber-900 ring-amber-200/80', 'label' => 'In Progress'],
        'in_transit' => ['class' => 'bg-amber-100 text-amber-900 ring-amber-200/80', 'label' => 'In Progress'],
        'pending' => ['class' => 'bg-sky-100 text-sky-900 ring-sky-200/80', 'label' => 'Pending'],
        'rejected' => ['class' => 'bg-rose-100 text-rose-900 ring-rose-200/80', 'label' => 'Rejected'],
        'returned' => ['class' => 'bg-rose-100 text-rose-900 ring-rose-200/80', 'label' => 'Rejected'],
        'completed' => ['class' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80', 'label' => 'Completed'],
    ];
    $style = $map[$normalized] ?? $map['pending'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset '.$style['class']]) }}>
    {{ $style['label'] }}
</span>
