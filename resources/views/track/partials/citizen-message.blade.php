{{--
    One message in the citizen-visible thread, with its replies nested under it.
    Citizen, staff, and system messages are visually distinct so the citizen can
    tell their own words from the office's answer at a glance.
--}}
@php
    $isMine = $post->isFromCitizen();
    $isSystem = $post->isFromSystem();
    $tone = $isMine
        ? 'border-green/30 bg-green-wash/50'
        : ($isSystem ? 'border-status-amber-wash bg-status-amber-wash/40' : 'border-hairline bg-paper');
@endphp

<div class="rounded-xl border {{ $tone }} p-3" id="cmsg-{{ $post->id }}" x-data="{ replying: false }">
    <div class="flex items-center justify-between gap-3">
        <span class="text-[13px] font-bold text-ink">{{ $post->publicAuthorLabel() }}</span>
        <span class="shrink-0 text-[12px] text-ink-soft">{{ optional($post->created_at)->format('M d, Y h:i A') }}</span>
    </div>

    <p class="mt-1 whitespace-pre-wrap text-sm text-ink">{{ $post->body }}</p>

    @if($post->hasAttachment())
        <a href="{{ route('track.messages.attachment', $post) }}"
           class="mt-2 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-green-deep hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.4 12.8 12 19.2a4.5 4.5 0 0 1-6.4-6.4l7.1-7.1a3 3 0 0 1 4.3 4.3l-7.1 7.1a1.5 1.5 0 0 1-2.2-2.2l6.4-6.3"/></svg>
            {{ $post->attachment_name ?: 'Attachment' }}
        </a>
    @endif

    {{-- Replies to this message (one level, newest last). --}}
    @if($post->replies->isNotEmpty())
        <div data-replies class="mt-3 space-y-2 border-l-2 border-hairline pl-3">
            @foreach($post->replies as $reply)
                <div class="rounded-lg {{ $reply->isFromCitizen() ? 'bg-green-wash/40' : 'bg-hairline/25' }} p-2" id="cmsg-{{ $reply->id }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[12px] font-bold text-ink">{{ $reply->publicAuthorLabel() }}</span>
                        <span class="shrink-0 text-[11px] text-ink-soft">{{ optional($reply->created_at)->format('M d, h:i A') }}</span>
                    </div>
                    <p class="mt-0.5 whitespace-pre-wrap text-[13px] text-ink">{{ $reply->body }}</p>
                    @if($reply->hasAttachment())
                        <a href="{{ route('track.messages.attachment', $reply) }}"
                           class="mt-1 inline-block text-[12px] font-semibold text-green-deep hover:underline">
                            {{ $reply->attachment_name ?: 'Attachment' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Replying keeps a question and its answers together instead of starting a
         new message at the bottom of the thread. System entries are read-only. --}}
    @if($canPost && ! $isSystem)
        <button type="button" @click="replying = !replying"
                class="mt-2 text-[12px] font-semibold text-green-deep hover:underline"
                x-text="replying ? 'Cancel' : 'Reply'"></button>

        <form x-show="replying" x-cloak method="POST"
              action="{{ route('track.messages.store', $document->tracking_number) }}"
              enctype="multipart/form-data" class="mt-2">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $post->id }}">
            <label for="reply-body-{{ $post->id }}" class="sr-only">Your reply</label>
            <textarea id="reply-body-{{ $post->id }}" name="body" rows="2" required maxlength="5000"
                      placeholder="Write a reply…"
                      class="w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2 text-[13px] focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <input type="file" name="attachment" accept="{{ \App\Support\UploadRules::accept() }}"
                       class="block text-xs text-ink-soft file:mr-2 file:rounded-lg file:border-0 file:bg-green-wash file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-green-deep">
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Send reply</button>
            </div>
        </form>
    @endif
</div>
