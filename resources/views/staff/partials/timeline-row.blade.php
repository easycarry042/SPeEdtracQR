{{-- One event on the rail: time, coloured dot, headline, and the detail that
     explains it. `row` comes from App\Support\ActivityNarrative. --}}
@php
    $at = $at ?? null;
    $author = $author ?? null;
    // Rows past the first few are in the DOM but folded away; x-show rather
    // than a <template>, so the markup stays a valid list of <li>.
    // Built here rather than with an inline @if: Blade cannot see an @endif
    // glued to the preceding attribute, and silently fails to close the block.
    $foldAttrs = ($folded ?? false) ? ' x-show="open" x-cloak' : '';
@endphp

<li class="sp-tl-row t-{{ $row['tone'] }}"{!! $foldAttrs !!}>
    <time class="sp-tl-time" @if($at) datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('M d, Y g:i A') }}" @endif>
        {{ $at ? $at->format('g:i A') : '—' }}
    </time>

    <p class="sp-tl-headline">{{ $row['headline'] }}</p>

    @if($row['detail'])
        <p class="sp-tl-detail">{{ $row['detail'] }}</p>
    @endif

    @if($author || $at)
        <p class="sp-tl-meta">
            @if($author){{ $author }}@endif
            @if($author && $at) · @endif
            @if($at){{ $at->format('M d, Y') }}@endif
        </p>
    @endif
</li>
