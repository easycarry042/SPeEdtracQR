{{-- Pager for the staff data tables (.st-card). Always shown, pinned under the
     rows, so the card keeps one fixed shape whatever the row count.

     Expects these in the surrounding Alpine scope: total, current, perPage,
     pageCount, pageList (page numbers with 'gap' markers) and go(page). --}}
<nav aria-label="{{ $label ?? 'Table pages' }}" class="st-pager">
    <p class="st-pager-info" aria-live="polite"
       x-text="total === 0 ? 'No requests' : `Showing ${(current - 1) * perPage + 1}–${Math.min(current * perPage, total)} of ${total}`"></p>

    <button type="button" @click="go(current - 1)" :disabled="current === 1"
            class="st-page-step" aria-label="Previous page">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>

    <template x-for="(entry, i) in pageList" :key="i">
        <span>
            <span x-show="entry === 'gap'" class="px-1 text-ink-soft" aria-hidden="true">…</span>
            <button x-show="entry !== 'gap'" type="button" @click="go(entry)"
                    :aria-current="entry === current ? 'page' : null"
                    class="st-page" :class="entry === current ? 'on' : ''"
                    x-text="entry"></button>
        </span>
    </template>

    <button type="button" @click="go(current + 1)" :disabled="current === pageCount"
            class="st-page-step" aria-label="Next page">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>
</nav>
