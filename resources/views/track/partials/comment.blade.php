@php
    $tag = $comment->author_type === 'system' ? 'system' : $comment->visibility;
    $badge = [
        'internal' => 'background:#eef2ef;color:#5b6b62;',
        'public' => 'background:#eef5f0;color:#0f5c2e;',
        'system' => 'background:#fbf3e0;color:#8a6d1f;',
    ][$tag] ?? 'background:#eef2ef;color:#5b6b62;';
@endphp
<div class="rounded-xl border border-[#e6ece8] bg-white p-3" id="comment-{{ $comment->id }}" x-data="{ replying: false }">
    <div class="flex items-center justify-between">
        <span class="text-[13px] font-bold text-[#16211b]">
            {{ $comment->authorLabel() }}
            <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold" style="{{ $badge }}">{{ $tag }}</span>
        </span>
        <span class="text-[12px] text-[#51625a]">{{ optional($comment->created_at)->format('M d, Y h:i A') }}</span>
    </div>
    <p class="mt-1 whitespace-pre-wrap text-[14px] text-[#33433b]">{{ $comment->body }}</p>

    {{-- Replies --}}
    @if($comment->replies->isNotEmpty())
        <div class="mt-3 space-y-2 border-l-2 border-[#eaf4ee] pl-3">
            @foreach($comment->replies as $reply)
                @php $rtag = $reply->author_type === 'system' ? 'system' : $reply->visibility; @endphp
                <div class="rounded-lg bg-[#f7faf8] p-2" id="comment-{{ $reply->id }}">
                    <div class="flex items-center justify-between">
                        <span class="text-[12px] font-bold text-[#16211b]">{{ $reply->authorLabel() }}
                            <span class="ml-1 text-[10px] font-semibold text-[#5b6b62]">· {{ $rtag }}</span></span>
                        <span class="text-[11px] text-[#51625a]">{{ optional($reply->created_at)->format('M d, h:i A') }}</span>
                    </div>
                    <p class="mt-0.5 whitespace-pre-wrap text-[13px] text-[#33433b]">{{ $reply->body }}</p>
                </div>
            @endforeach
        </div>
    @endif

    @if($canPost)
        <button type="button" @click="replying = !replying" class="mt-2 text-[12px] font-semibold text-[#0f4d28] hover:underline">Reply</button>
        <form x-show="replying" x-cloak method="POST" action="{{ route('documents.comments.store', $document) }}" class="mt-2">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <textarea name="body" rows="2" required maxlength="5000" placeholder="Write a reply…"
                      class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"></textarea>
            <div class="mt-2 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-[12px]">
                    <label class="flex items-center gap-1"><input type="radio" name="visibility" value="internal" checked> Internal</label>
                    <label class="flex items-center gap-1"><input type="radio" name="visibility" value="public"> Visible to citizen</label>
                </div>
                <button type="submit" class="rounded-lg bg-[#0f4d28] px-3 py-1.5 text-[12px] font-bold text-white hover:bg-[#0b3a1e]">Reply</button>
            </div>
        </form>
    @endif
</div>
