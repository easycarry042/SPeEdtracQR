{{-- Highlight composer — own profile only. Links to one of the author's own
     documents (a reference, not an upload). --}}
<div x-data="{ open: false, body: '', type: 'note' }" class="panel sp-composer">
    {{-- Collapsed prompt --}}
    <button type="button" x-show="!open" @click="open = true"
            class="sp-composer-prompt">
        <span class="sp-avatar sp-avatar-sm">{{ collect(explode(' ', auth()->user()->name))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
        <span style="color:var(--ink-soft);">Share an accomplishment…</span>
    </button>

    {{-- Expanded form --}}
    <form x-show="open" x-cloak method="POST" action="{{ route('staff.highlights.store') }}" style="display:flex;flex-direction:column;gap:12px;">
        @csrf
        <input type="hidden" name="highlight_type" :value="type">

        <textarea name="body" x-model="body" rows="3" maxlength="2000" required
                  placeholder="Share an accomplishment, milestone, or note…"
                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"></textarea>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
            <div style="display:flex;align-items:center;gap:6px;">
                @foreach(['note' => 'Note', 'completed' => 'Completed', 'milestone' => 'Milestone'] as $val => $label)
                    <button type="button" @click="type = '{{ $val }}'"
                            class="sp-typepill" :class="type === '{{ $val }}' ? 'on' : ''">{{ $label }}</button>
                @endforeach
            </div>
            <span class="text-xs text-gray-400" x-text="body.length + ' / 2000'"></span>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
            <select name="document_id" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" style="max-width:320px;">
                <option value="">Attach a document (optional)…</option>
                @foreach($ownDocuments as $doc)
                    <option value="{{ $doc->id }}">{{ $doc->tracking_number }} · {{ $doc->document_type }}</option>
                @endforeach
            </select>

            <div style="display:flex;align-items:center;gap:8px;">
                <span class="text-[11px] text-gray-400">Visible to staff</span>
                <button type="button" @click="open = false" class="cr-btn cr-btn-sm">Cancel</button>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm" :disabled="body.trim().length === 0">Post</button>
            </div>
        </div>
    </form>
</div>
