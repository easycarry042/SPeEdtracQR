@props([
    'user' => null,          // App\Models\User, or null when only the pieces are known
    'name' => null,          // falls back to this when no user model is passed
    'avatarPath' => null,
    'id' => null,
])

@php
    $displayName = $user?->name ?? $name ?? '';
    $hasPicture = (bool) ($user?->avatar_path ?? $avatarPath);
    $userId = $user?->id ?? $id;

    $initials = collect(preg_split('/\s+/', trim($displayName)))
        ->filter()
        ->take(2)
        ->map(fn ($part): string => mb_strtoupper(mb_substr((string) $part, 0, 1)))
        ->implode('');

    // The stored filename carries the upload timestamp, so the URL changes
    // when the picture does and a cached copy is never shown in its place.
    $src = $hasPicture && $userId ? route('staff.avatar', $userId) : null;
@endphp

@if($src)
    {{-- Decorative: the name is always written beside it, so a screen reader
         announcing "photo of Ana Cruz" next to "Ana Cruz" is just noise. --}}
    <img src="{{ $src }}" alt="" aria-hidden="true" loading="lazy" decoding="async"
         {{ $attributes->merge(['class' => 'object-cover']) }}>
@else
    <span aria-hidden="true" {{ $attributes }}>{{ $initials ?: '?' }}</span>
@endif
