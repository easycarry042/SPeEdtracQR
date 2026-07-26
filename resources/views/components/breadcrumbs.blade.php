@props(['items' => []])
{{-- Consistent breadcrumb trail (User Control & Freedom). Pass an array of
     ['label' => string, 'url' => ?string]; the last item is the current page. --}}
<nav aria-label="Breadcrumb" {{ $attributes->merge(['class' => 'cr-crumbs']) }}>
    <ol>
        @foreach($items as $item)
            <li>
                @if(! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    <svg aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                @else
                    <span aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
