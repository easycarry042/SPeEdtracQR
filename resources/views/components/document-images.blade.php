@props(['document' => null, 'limit' => 4, 'size' => 'sm', 'urls' => null, 'manage' => false])

@php
    $images = collect();
    if ($urls) {
        $images = collect($urls)->map(fn ($u) => (object) ['url' => $u]);
    } elseif ($document && $document->relationLoaded('attachments') && $document->attachments->isNotEmpty()) {
        $images = $document->attachments;
    }
    $thumbClass = $size === 'lg' ? 'h-20 w-20' : 'h-12 w-12';
    $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
@endphp

@if($images->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @foreach($images->take($limit) as $img)
            @php
                // Attachment models are served through the authorized route;
                // pre-resolved URLs (passed via :urls) are used as-is. Don't read
                // $img->url on a model — Eloquent treats it as a relationship.
                $isAttachment = $img instanceof \App\Models\DocumentAttachment;
                $url = $isAttachment ? route('attachments.show', $img) : ($img->url ?? null);
                $downloadUrl = $isAttachment ? $img->downloadUrl() : $url;
                $ext = $isAttachment ? strtolower(pathinfo($img->file_path, PATHINFO_EXTENSION)) : 'img';
                $isImage = ! $isAttachment || in_array($ext, $imageExt, true);
                $canDelete = $manage && $isAttachment;
            @endphp
            @if($url)
                <div class="relative">
                    @if($isImage)
                        {{-- data-lightbox-src opens the in-page viewer; href is the no-JS fallback --}}
                        <a href="{{ $url }}" target="_blank" rel="noopener" data-lightbox-src="{{ $url }}" data-lightbox-download="{{ $downloadUrl }}"
                           class="block overflow-hidden rounded-lg ring-1 ring-hairline transition hover:ring-green-bright"
                           title="View image">
                            <img src="{{ $url }}" alt="Document attachment" class="{{ $thumbClass }} object-cover bg-green-wash"
                                 loading="lazy"
                                 onerror="this.classList.add('opacity-40'); this.alt='Image unavailable';">
                        </a>
                    @else
                        {{-- Non-image (PDF / Word): a file chip that opens/downloads the document. --}}
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="flex {{ $thumbClass }} flex-col items-center justify-center gap-1 rounded-lg bg-green-wash text-ink-soft ring-1 ring-hairline transition hover:ring-green-bright"
                           title="Open {{ strtoupper($ext) }} file">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4"/></svg>
                            <span class="text-[9px] font-bold uppercase">{{ $ext }}</span>
                        </a>
                    @endif

                    @if($isAttachment && ! $isImage)
                        {{-- File chips have no lightbox, so offer a direct download. --}}
                        <a href="{{ $downloadUrl }}" title="Download {{ strtoupper($ext) }} file"
                           class="absolute -bottom-1.5 -left-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-green-deep text-white shadow ring-2 ring-white transition hover:bg-green-bright">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        </a>
                    @endif

                    @if($canDelete)
                        <form method="POST" action="{{ route('attachments.destroy', $img) }}"
                              onsubmit="return confirm('Remove this file from the document?')"
                              class="absolute -right-1.5 -top-1.5">
                            @csrf @method('DELETE')
                            <button type="submit" title="Remove file"
                                    class="flex h-5 w-5 items-center justify-center rounded-full bg-status-red text-white shadow ring-2 ring-white transition hover:bg-[#6e1f1f]">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        @endforeach
        @if($images->count() > $limit)
            <span class="flex {{ $thumbClass }} items-center justify-center rounded-lg bg-green-wash text-xs font-semibold text-ink-soft">+{{ $images->count() - $limit }}</span>
        @endif
    </div>
@endif
