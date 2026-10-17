{{-- The opening of a citizen page: eyebrow pill, display title and one lead
     paragraph — the landing page hero's stack, so every page after it starts
     the same way. Styles live in resources/css/app.css ("Citizen surface set").

     <x-page-intro eyebrow="Online request" title="Submit a request">
         One sentence on what this page is for.
     </x-page-intro> --}}
@props(['eyebrow' => null, 'title'])

<div {{ $attributes->merge(['class' => 'max-w-[760px]']) }}>
    @if($eyebrow)
        <p class="cz-eyebrow">{{ $eyebrow }}</p>
    @endif

    <h1 @class(['cz-title', 'mt-4' => $eyebrow])>{{ $title }}</h1>

    @if($slot->isNotEmpty())
        <p class="cz-lead mt-3">{{ $slot }}</p>
    @endif
</div>
