{{--
    One message in the staff Collaboration feed, with its replies nested under it.

    The tag says which thread a message belongs to and who wrote it:
      internal — staff-only note (a question here notifies the assignee)
      citizen  — the requester wrote this; a reply goes straight back to them
      public   — staff wrote this to the citizen
      system   — automatic status entry, read-only
--}}
@php
    $tag = match (true) {
        $comment->isFromSystem() => 'system',
        $comment->isFromCitizen() => 'citizen',
        default => $comment->visibility,
    };
    $badge = [
        'internal' => 'background:#eef2ef;color:#5b6b62;',
        'public' => 'background:#eef5f0;color:#0f5c2e;',
        'citizen' => 'background:#e7f0fb;color:#1d4e89;',
        'system' => 'background:#fbf3e0;color:#8a6d1f;',
    ][$tag] ?? 'background:#eef2ef;color:#5b6b62;';

    // A citizen message is the one kind staff must answer, so it is outlined.
    $shell = $comment->isFromCitizen()
        ? 'rounded-xl border-2 border-[#bcd6f5] bg-[#f7fbff] p-3'
        : 'rounded-xl border border-hairline bg-paper p-3';
@endphp
<div class="{{ $shell }}" id="comment-{{ $comment->id }}" x-data="{ replying: false }">
    <div class="flex items-center justify-between">
        <span class="text-[13px] font-bold text-ink">
            {{ $comment->authorLabel() }}
            <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold" style="{{ $badge }}">{{ $tag }}</span>
        </span>
        <span class="text-[12px] text-ink-soft">{{ optional($comment->created_at)->format('M d, Y h:i A') }}</span>
    </div>
    <p class="mt-1 whitespace-pre-wrap text-[14px] text-ink">{{ $comment->body }}</p>

    @if($comment->hasAttachment())
        <a href="{{ route('documents.comments.attachment', $comment) }}"
           class="mt-2 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-green-deep hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.4 12.8 12 19.2a4.5 4.5 0 0 1-6.4-6.4l7.1-7.1a3 3 0 0 1 4.3 4.3l-7.1 7.1a1.5 1.5 0 0 1-2.2-2.2l6.4-6.3"/></svg>
            {{ $comment->attachment_name ?: 'Attachment' }}
        </a>
    @endif

    {{-- Replies --}}
    @if($comment->replies->isNotEmpty())
        <div data-replies class="mt-3 space-y-2 border-l-2 border-hairline pl-3">
            @foreach($comment->replies as $reply)
                @php
                    $rtag = match (true) {
                        $reply->isFromSystem() => 'system',
                        $reply->isFromCitizen() => 'citizen',
                        default => $reply->visibility,
                    };
                @endphp
                <div class="rounded-lg {{ $reply->isFromCitizen() ? 'bg-[#eef5fd]' : 'bg-green-wash/40' }} p-2" id="comment-{{ $reply->id }}">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-bold text-ink">{{ $reply->authorLabel() }}
                            <span class="ml-1 text-[10px] font-semibold text-ink-soft">· {{ $rtag }}</span></span>
                        <span class="text-[11px] text-ink-soft">{{ optional($reply->created_at)->format('M d, h:i A') }}</span>
                    </div>
                    <p class="mt-0.5 whitespace-pre-wrap text-[13px] text-ink">{{ $reply->body }}</p>
                    @if($reply->hasAttachment())
                        <a href="{{ route('documents.comments.attachment', $reply) }}"
                           class="mt-1 inline-block text-[12px] font-semibold text-green-deep hover:underline">
                            {{ $reply->attachment_name ?: 'Attachment' }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($canPost)
        <button type="button" @click="replying = !replying" class="mt-2 text-[12px] font-semibold text-green-deep hover:underline">Reply</button>
        <form x-show="replying" x-cloak method="POST" action="{{ route('documents.comments.store', $document) }}"
              enctype="multipart/form-data" class="mt-2">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <label for="reply-body-{{ $comment->id }}" class="sr-only">Your reply</label>
            <textarea id="reply-body-{{ $comment->id }}" name="body" rows="2" required maxlength="5000"
                      placeholder="{{ $comment->isFromCitizen() ? 'Answer the citizen…' : 'Write a reply…' }}"
                      class="w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2 text-[13px] focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                {{-- A reply in the citizen thread defaults to citizen-visible:
                     answering the requester with an internal note would silently
                     go nowhere. Replies to internal notes default to internal. --}}
                <div class="flex items-center gap-3 text-[12px] text-ink">
                    <label class="flex items-center gap-1">
                        <input type="radio" name="visibility" value="internal" class="accent-green" @checked(! $comment->isPublic())> Internal
                    </label>
                    <label class="flex items-center gap-1">
                        <input type="radio" name="visibility" value="public" class="accent-green" @checked($comment->isPublic())> Visible to citizen
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <input type="file" name="attachment" accept="{{ \App\Support\UploadRules::accept() }}"
                           class="block text-xs text-ink-soft file:mr-2 file:rounded-lg file:border-0 file:bg-green-wash file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-green-deep">
                    <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Reply</button>
                </div>
            </div>
        </form>
    @endif
</div>
