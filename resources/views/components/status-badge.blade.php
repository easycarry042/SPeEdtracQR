@props(['status' => 'pending'])

@php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    // Status colors signal state only — never decorative.
    $map = [
        'in_progress' => ['class' => 'p-amber', 'label' => 'In progress'],
        'in_transit' => ['class' => 'p-amber', 'label' => 'In progress'],
        'pending' => ['class' => 'p-muted', 'label' => 'Pending'],
        'rejected' => ['class' => 'p-red', 'label' => 'Rejected'],
        'returned' => ['class' => 'p-red', 'label' => 'Rejected'],
        'completed' => ['class' => 'p-green', 'label' => 'Completed'],
    ];
    $style = $map[$normalized] ?? $map['pending'];
@endphp

<span {{ $attributes->merge(['class' => 'pill '.$style['class']]) }}>{{ $style['label'] }}</span>
