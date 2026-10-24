@props([
    'type' => '',
    'size' => 30,
])

@php
    /**
     * Request-type glyphs exported from the Figma board. Request types are rows
     * in the database, not a fixed list, so the frame's icons are matched on
     * keywords rather than exact names — a new "Borrowing of Tarpaulin" still
     * lands on the banner glyph instead of the generic fallback.
     *
     * @var array<int, array{0: list<string>, 1: string}>
     */
    $rules = [
        [['lei', 'flower', 'wreath'], 'color-triangle.svg'],
        [['plaza', 'hall', 'venue', 'court', 'gym', 'facility', 'building'], 'office-building.svg'],
        [['flag'], 'flag.png'],
        [['banner', 'tarpaulin', 'spider', 'streamer'], 'web.png'],
    ];

    $needle = Str::lower($type);
    $file = 'task-list-pen.svg';

    foreach ($rules as [$keywords, $candidate]) {
        foreach ($keywords as $keyword) {
            if (str_contains($needle, $keyword)) {
                $file = $candidate;

                break 2;
            }
        }
    }
@endphp

<img src="{{ asset('images/request-types/'.$file) }}" alt=""
     {{ $attributes->merge(['class' => 'object-contain']) }}
     style="width: {{ $size }}px; height: {{ $size }}px;">
