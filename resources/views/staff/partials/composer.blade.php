{{-- Highlight composer — own profile only. Links to one of the author's own
     documents (a reference, not an upload).

     Submit stays enabled while the box is empty: a disabled button gives no
     reason, so the server's validation message is the better teacher. The
     button only goes dead once a request is actually in flight, to stop a
     double post. --}}
<section class="gs-surface"
         x-data="{
            open: false,
            body: '',
            type: 'note',
            sending: false,
            get dirty() { return this.body.trim().length > 0 },
         }"
         x-on:beforeunload.window="if (dirty && ! sending) $event.preventDefault()">

    {{-- Collapsed prompt --}}
    <button type="button" x-show="! open" @click="open = true; $nextTick(() => $refs.body.focus())"
            class="gs-composer-prompt">
        <x-user-avatar :user="auth()->user()" class="gs-avatar gs-avatar-sm" />
        <span>Share an accomplishment…</span>
    </button>

    {{-- Expanded form --}}
    <form x-show="open" x-cloak method="POST" action="{{ route('staff.highlights.store') }}"
          @submit="sending = true"
          class="gs-surface-body" style="display:flex;flex-direction:column;gap:12px;">
        @csrf
        <input type="hidden" name="highlight_type" :value="type">

        <div>
            <label for="gs-highlight-body" class="sr-only">Highlight</label>
            {{-- ⌘/⌃+Enter submits; plain Enter keeps inserting newlines. --}}
            <textarea id="gs-highlight-body" name="body" x-ref="body" x-model="body" rows="3" maxlength="2000" required
                      @keydown.enter.meta="$el.form.requestSubmit()"
                      @keydown.enter.ctrl="$el.form.requestSubmit()"
                      placeholder="Share an accomplishment, milestone, or note…"
                      autocomplete="off"
                      class="gs-field" style="resize:vertical;"></textarea>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
            <div class="gs-seg" role="group" aria-label="Highlight type">
                @foreach(['note' => 'Note', 'completed' => 'Completed', 'milestone' => 'Milestone'] as $val => $label)
                    <button type="button" @click="type = '{{ $val }}'"
                            class="gs-seg-btn"
                            :aria-pressed="type === '{{ $val }}' ? 'true' : 'false'">{{ $label }}</button>
                @endforeach
            </div>
            <span class="gs-num" style="font-size:12px;color:var(--gs-mute);" aria-live="polite">
                <span x-text="body.length"></span> / 2000
            </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
            <div>
                <label for="gs-highlight-doc" class="sr-only">Attach a request</label>
                <select id="gs-highlight-doc" name="document_id" class="gs-field" style="max-width:320px;">
                    <option value="">Attach a request (optional)…</option>
                    @foreach($ownDocuments as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->tracking_number }} · {{ $doc->document_type }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;color:var(--gs-mute);">Visible to staff</span>
                <button type="button" @click="open = false" class="gs-btn">Cancel</button>
                {{-- The label is preserved while sending, per the loading-button rule. --}}
                <button type="submit" class="gs-btn gs-btn-primary" :disabled="sending">
                    <span x-text="sending ? 'Posting…' : 'Post'">Post</span>
                </button>
            </div>
        </div>
    </form>
</section>
