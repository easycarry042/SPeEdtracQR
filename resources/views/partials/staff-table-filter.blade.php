{{-- Filter button + panel for the staff data tables (.st-card): narrow the
     rows to one request type and/or a submitted-date range.

     The surrounding Alpine scope spreads in window.stFilterMixin() (defined
     below) and provides typeOptions() — the request types to offer — and a
     `page` to reset. Rows are matched with passesFilter(type, 'YYYY-MM-DD'). --}}
<div class="relative" @click.outside="filterOpen = false" @keydown.escape.window="filterOpen = false">
    <button type="button" @click="filterOpen = ! filterOpen"
            class="st-tool-btn" :class="filterCount() > 0 && 'is-active'"
            title="Filter" aria-label="Filter requests" aria-haspopup="dialog" :aria-expanded="filterOpen">
        <img src="{{ asset('images/staff/icon-filter.svg') }}" alt="">
        <span x-show="filterCount() > 0" x-cloak class="st-filter-badge" x-text="filterCount()"></span>
    </button>

    <div x-show="filterOpen" x-cloak x-transition.opacity.duration.150ms
         class="st-filter-pop" role="dialog" aria-label="Filter requests">
        <p class="st-filter-title">Filter requests</p>

        <label class="st-filter-label" for="{{ $id }}-type">{{ $typeLabel ?? 'Request type' }}</label>
        <select id="{{ $id }}-type" x-model="fType" @change="page = 1" class="st-filter-field">
            <option value="">{{ $allTypesLabel ?? 'All request types' }}</option>
            <template x-for="type in typeOptions()" :key="type">
                <option :value="type" x-text="type" :selected="type === fType"></option>
            </template>
        </select>

        <p class="st-filter-label mt-4">Date submitted</p>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="st-filter-sub" for="{{ $id }}-from">From</label>
                <input id="{{ $id }}-from" type="date" x-model="fFrom" @change="page = 1" :max="fTo || null" class="st-filter-field">
            </div>
            <div>
                <label class="st-filter-sub" for="{{ $id }}-to">To</label>
                <input id="{{ $id }}-to" type="date" x-model="fTo" @change="page = 1" :min="fFrom || null" class="st-filter-field">
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            <button type="button" class="st-filter-chip" @click="presetDays(0)">Today</button>
            <button type="button" class="st-filter-chip" @click="presetDays(6)">Last 7 days</button>
            <button type="button" class="st-filter-chip" @click="presetDays(29)">Last 30 days</button>
            <button type="button" class="st-filter-chip" @click="presetThisMonth()">This month</button>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-hairline pt-3">
            <button type="button" @click="resetFilters()" class="text-[length:var(--fs-small)] font-semibold text-ink-soft hover:text-green-deep">Reset</button>
            <button type="button" @click="filterOpen = false" class="cr-btn cr-btn-primary cr-btn-sm">Done</button>
        </div>
    </div>
</div>

@once
<script>
    /**
     * Filter state + matching shared by the staff tables. Plain fields and
     * methods only (no getters), so it can be spread into an Alpine object.
     */
    window.stFilterMixin = function () {
        const iso = (date) => date.getFullYear() + '-'
            + String(date.getMonth() + 1).padStart(2, '0') + '-'
            + String(date.getDate()).padStart(2, '0');

        return {
            filterOpen: false,
            fType: '',
            fFrom: '',
            fTo: '',

            /** True when a row of this type, submitted on this day (YYYY-MM-DD), should show. */
            passesFilter(type, day) {
                if (this.fType && type !== this.fType) {
                    return false;
                }
                if (this.fFrom && (! day || day < this.fFrom)) {
                    return false;
                }
                if (this.fTo && (! day || day > this.fTo)) {
                    return false;
                }

                return true;
            },

            filterCount() {
                return (this.fType ? 1 : 0) + (this.fFrom || this.fTo ? 1 : 0);
            },

            /** Distinct, sorted request types — for typeOptions(). */
            uniqueTypes(types) {
                return [...new Set(types.filter(Boolean))].sort((a, b) => a.localeCompare(b));
            },

            presetDays(daysBack) {
                const today = new Date();
                const from = new Date(today.getFullYear(), today.getMonth(), today.getDate() - daysBack);
                this.fFrom = iso(from);
                this.fTo = iso(today);
                this.page = 1;
            },

            presetThisMonth() {
                const today = new Date();
                this.fFrom = iso(new Date(today.getFullYear(), today.getMonth(), 1));
                this.fTo = iso(today);
                this.page = 1;
            },

            resetFilters() {
                this.fType = '';
                this.fFrom = '';
                this.fTo = '';
                this.page = 1;
            },
        };
    };
</script>
@endonce
