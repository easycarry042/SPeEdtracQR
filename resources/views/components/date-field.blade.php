@props([
    'name',
    'label' => 'Date',
    'value' => '',
    'min' => null,
    'id' => null,
    'invalid' => false,
    // Toolbar sizing: a filter bar needs the control at the height of the
    // selects beside it, not at full form-field height.
    'compact' => false,
    // Empty-state text. A filter reads "any date"; a form field asks for one.
    'placeholder' => null,
])

@php
    $fieldId = $id ?? $name;
@endphp

{{-- A date field that opens the system's own calendar instead of the browser's.

     Chrome, Firefox and Safari each draw <input type="date"> differently and
     none of them can be styled, so the one control a citizen is most likely to
     open looked like it belonged to a different site. This is the Look Up
     desk's claiming-date calendar (.dp-* in app.css) in a popover: same month
     header, weekday band, red Sundays, today dot and green picked day.

     The submitted value is a hidden ISO field, exactly what type="date" posted
     before, so validation and the controllers are untouched. --}}
<div x-data="dateField('{{ $value }}', '{{ $min }}', {{ Js::from($placeholder ?? ($compact ? 'Any date' : 'Select a date')) }})"
     {{ $attributes->merge(['class' => 'relative']) }}>
    {{-- On a filter bar the label rides inside the control, the way the selects
         beside it do — a label stacked above would make this one control taller
         than the row and wrap the toolbar. It stays a real <label> either way,
         so clicking it still opens the picker. --}}
    <label for="{{ $fieldId }}" class="{{ $compact ? 'sr-only' : 'req-label text-sm' }}">{{ $label }}</label>

    <input type="hidden" name="{{ $name }}" :value="value">

    <button type="button" id="{{ $fieldId }}" @click="toggle()"
            class="req-field cz-date-trigger @if($compact) cz-date-compact @else mt-2 @endif @if($invalid) is-invalid @endif"
            :class="{ 'is-open': open }"
            aria-haspopup="dialog" :aria-expanded="open">
        @if($compact)
            <span class="cz-date-prefix" aria-hidden="true">{{ $label }}</span>
        @endif
        <span :class="value || 'is-empty'" x-text="displayLabel()"></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="5" width="18" height="16" rx="3"/>
            <path stroke-linecap="round" d="M8 3v4M16 3v4M3 10h18"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
         @click.outside="open = false" @keydown.escape.window="open = false"
         class="cz-datepicker absolute z-30 mt-2" role="dialog"
         :aria-label="'Choose ' + {{ Js::from(strtolower($label)) }}">

        <div class="dp-head">
            <p class="dp-month" x-text="monthLabel()" aria-live="polite"></p>
            <div class="bk-nav" role="group" aria-label="Change month">
                <button type="button" @click="shiftMonth(-1)" aria-label="Previous month">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                </button>
                <button type="button" @click="toToday()">Today</button>
                <button type="button" @click="shiftMonth(1)" aria-label="Next month">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
        </div>

        <div class="dp-dows" aria-hidden="true">
            <template x-for="(dow, i) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="dow">
                <span :class="i === 0 && 'is-sunday'" x-text="dow"></span>
            </template>
        </div>

        <div class="dp-grid" role="grid" :aria-label="{{ Js::from($label) }}">
            <template x-for="(week, w) in calendarWeeks()" :key="'w' + w">
                <div class="dp-week" role="row">
                    <template x-for="cell in week" :key="cell.iso">
                        <div class="dp-cell" role="gridcell">
                            <button type="button" @click="pick(cell)"
                                    :disabled="cell.blocked"
                                    class="dp-day"
                                    :class="{
                                        'is-picked': value === cell.iso,
                                        'is-today': cell.today,
                                        'is-outside': ! cell.inMonth,
                                        'is-sunday': cell.sunday,
                                    }"
                                    :aria-pressed="value === cell.iso"
                                    :aria-current="cell.today ? 'date' : null"
                                    :aria-label="cell.label + (cell.blocked ? ', unavailable' : '')">
                                <span x-text="cell.day"></span>
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="dp-foot">
            <p class="dp-picked">
                <template x-if="value">
                    <span>Picked <strong x-text="displayLabel()"></strong></span>
                </template>
                <template x-if="! value">
                    <span>No date picked yet</span>
                </template>
            </p>
            <button type="button" @click="clear()" x-show="value" class="cz-date-clear">Clear</button>
        </div>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dateField', (initial, min, placeholder) => ({
        open: false,
        value: initial || '',
        min: min || '',
        placeholder: placeholder || 'Select a date',
        calYear: 0,
        calMonth: 0,

        init() {
            this.toMonthOf(this.value || this.isoOf(new Date()));
        },

        /* ── ISO helpers ──────────────────────────────────────────────────
           Built from local parts throughout. `new Date('2026-08-07')` is
           parsed as UTC, which lands on the 6th for anyone west of Greenwich
           and on the 7th here — the kind of off-by-one-day that only shows up
           for some citizens. */
        isoOf(d) {
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        },

        fromIso(iso) {
            const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');

            return parts ? new Date(+parts[1], +parts[2] - 1, +parts[3]) : null;
        },

        toMonthOf(iso) {
            const d = this.fromIso(iso) || new Date();

            this.calYear = d.getFullYear();
            this.calMonth = d.getMonth();
        },

        /** What the closed field reads: "August 7, 2026", or the placeholder. */
        displayLabel() {
            const d = this.fromIso(this.value);

            return d
                ? d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
                : this.placeholder;
        },

        monthLabel() {
            return new Date(this.calYear, this.calMonth, 1)
                .toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },

        toggle() {
            this.open = ! this.open;
            if (this.open) { this.toMonthOf(this.value); }
        },

        shiftMonth(delta) {
            const moved = new Date(this.calYear, this.calMonth + delta, 1);

            this.calYear = moved.getFullYear();
            this.calMonth = moved.getMonth();
        },

        toToday() { this.toMonthOf(this.isoOf(new Date())); },

        pick(cell) {
            if (cell.blocked) { return; }

            this.value = cell.iso;
            // A single date is settled the moment it is tapped; keeping the
            // sheet open would only ask for a second, redundant dismissal.
            this.open = false;
        },

        clear() {
            this.value = '';
            this.open = false;
        },

        /**
         * The month as whole weeks, padded with the neighbouring months' days
         * — only as many rows as the month actually spans, so a five-week
         * month does not draw an empty sixth.
         */
        calendarWeeks() {
            const first = new Date(this.calYear, this.calMonth, 1);
            const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
            const weeks = Math.ceil((first.getDay() + daysInMonth) / 7);
            const today = this.isoOf(new Date());
            const out = [];

            for (let w = 0; w < weeks; w++) {
                const row = [];

                for (let d = 0; d < 7; d++) {
                    const day = new Date(this.calYear, this.calMonth, 1 - first.getDay() + (w * 7) + d);
                    const iso = this.isoOf(day);

                    row.push({
                        day: day.getDate(),
                        iso: iso,
                        label: day.toLocaleDateString(undefined, { dateStyle: 'long' }),
                        inMonth: day.getMonth() === this.calMonth,
                        sunday: day.getDay() === 0,
                        today: iso === today,
                        // Same floor the field carried as min="": a request
                        // cannot be filed for a day that has already passed.
                        blocked: this.min ? iso < this.min : false,
                    });
                }

                out.push(row);
            }

            return out;
        },
    }));
});
</script>
@endonce
