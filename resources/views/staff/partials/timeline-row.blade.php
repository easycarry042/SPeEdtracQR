{{-- One event on the rail: time, coloured dot, headline, and the detail that
     explains it. `row` comes from App\Support\ActivityNarrative. The dot only
     repeats what the headline already says — it never carries meaning alone. --}}
@php
    $at = $at ?? null;
    $author = $author ?? null;
    // Rows past the first few are in the DOM but folded away; x-show rather
    // than a <template>, so the markup stays a valid list of <li>.
    // Built here rather than with an inline @if: Blade cannot see an @endif
    // glued to the preceding attribute, and silently fails to close the block.
    $foldAttrs = ($folded ?? false) ? ' x-show="open" x-cloak' : '';
@endphp

<li class="gs-tl-row t-{{ $row['tone'] }}"{!! $foldAttrs !!}>
    <time class="gs-tl-time" @if($at) datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('M j, Y g:i A') }}" @endif>
        {{ $at ? $at->format('g:i A') : '—' }}
    </time>

    <p class="gs-tl-headline">{{ $row['headline'] }}</p>

    @if($row['detail'])
        <p class="gs-tl-detail">{{ $row['detail'] }}</p>
    @endif

    @if($author || $at)
        <p class="gs-tl-meta">
            @if($author){{ $author }}@endif
            @if($author && $at) <span aria-hidden="true">·</span> @endif
            @if($at)<span class="gs-num">{{ $at->format('M j, Y') }}</span>@endif
        </p>
    @endif
</li>
