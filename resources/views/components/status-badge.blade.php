@props(['status' => 'pending'])

@php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    // Status colors signal state only — never decorative.
    $map = [
        'in_progress' => ['class' => 'p-amber', 'label' => 'In Progress'],
        'in_transit' => ['class' => 'p-amber', 'label' => 'In Progress'],
        'in_review' => ['class' => 'p-amber', 'label' => 'In Review'],
        'approved' => ['class' => 'p-green', 'label' => 'Approved'],
        'pending' => ['class' => 'p-muted', 'label' => 'Pending'],
        'rejected' => ['class' => 'p-red', 'label' => 'Rejected'],
        'returned' => ['class' => 'p-red', 'label' => 'Returned'],
        'completed' => ['class' => 'p-green', 'label' => 'Completed'],
        // Blocked/waiting — amber warning, distinct from Returned's red.
        'on_hold' => ['class' => 'p-hold', 'label' => 'On Hold'],
    ];
    $style = $map[$normalized] ?? $map['pending'];
@endphp

<span {{ $attributes->merge(['class' => 'pill '.$style['class']]) }}>{{ $style['label'] }}</span>
