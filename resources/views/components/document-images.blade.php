@props(['document', 'limit' => 4, 'size' => 'sm', 'urls' => null])

@php
    $images = collect();
    if ($urls) {
        $images = collect($urls)->map(fn ($u) => (object) ['url' => $u]);
    } elseif ($document->relationLoaded('attachments') && $document->attachments->isNotEmpty()) {
        $images = $document->attachments;
    }
    $thumbClass = $size === 'lg' ? 'h-20 w-20' : 'h-12 w-12';
@endphp

@if($images->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @foreach($images->take($limit) as $img)
            @php
                // Attachment models are served through the authorized route;
                // pre-resolved URLs (passed via :urls) are used as-is. Don't read
                // $img->url on a model — Eloquent treats it as a relationship.
                $url = $img instanceof \App\Models\DocumentAttachment
                    ? route('attachments.show', $img)
                    : ($img->url ?? null);
            @endphp
            @if($url)
                {{-- data-lightbox-src opens the in-page viewer; href is the no-JS fallback --}}
                <a href="{{ $url }}" target="_blank" rel="noopener" data-lightbox-src="{{ $url }}"
                   class="block overflow-hidden rounded-lg ring-1 ring-gray-200 transition hover:ring-emerald-400"
                   title="View image">
                    <img src="{{ $url }}" alt="Document attachment" class="{{ $thumbClass }} object-cover bg-gray-100"
                         loading="lazy"
                         onerror="this.classList.add('opacity-40'); this.alt='Image unavailable';">
                </a>
            @endif
        @endforeach
        @if($images->count() > $limit)
            <span class="flex {{ $thumbClass }} items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold text-gray-600">+{{ $images->count() - $limit }}</span>
        @endif
    </div>
@endif
