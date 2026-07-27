@props([
    'name',
    'label' => 'Time',
    'value' => '',
    'required' => false,
    'default' => '09:00',
])

{{-- Interactive time picker: type a time in the field, or open the analog clock
     and drag the hands. The native <input type="time"> is the source submitted
     with the form; the clock manipulates it. Alpine drives the geometry. --}}
<div x-data="timeClock('{{ $value !== '' ? $value : '' }}', '{{ $default }}')" class="relative">
    <label class="mb-1 block text-xs font-semibold text-gray-700">{{ $label }}</label>
    <div class="flex items-center gap-2">
        <input type="time" name="{{ $name }}" @if($required) required @endif
               :value="value"
               @input="parse($event.target.value)"
               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30">
        <button type="button" @click="toggle()" aria-label="Open clock"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-700 transition hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
        </button>
    </div>

    {{-- Clock popover --}}
    <div x-show="open" x-cloak x-transition.opacity
         @click.outside="open = false" @keydown.escape.window="open = false"
         class="absolute z-30 mt-2 w-[248px] rounded-2xl border border-emerald-200 bg-white p-4 shadow-xl">
        {{-- Readout + unit selectors --}}
        <div class="mb-3 flex items-center justify-center gap-1 text-2xl font-bold tabular-nums">
            <button type="button" @click="unit = 'hour'"
                    :class="unit === 'hour' ? 'bg-emerald-600 text-white' : 'text-gray-800 hover:bg-emerald-50'"
                    class="rounded-lg px-2 py-0.5 transition" x-text="display12()"></button>
            <span class="text-gray-400">:</span>
            <button type="button" @click="unit = 'minute'"
                    :class="unit === 'minute' ? 'bg-emerald-600 text-white' : 'text-gray-800 hover:bg-emerald-50'"
                    class="rounded-lg px-2 py-0.5 transition" x-text="pad(m)"></button>
            <div class="ml-2 flex flex-col overflow-hidden rounded-lg border border-emerald-200 text-xs font-semibold">
                <button type="button" @click="setAmPm(false)" :class="!pm ? 'bg-emerald-600 text-white' : 'text-gray-600'" class="px-2 py-0.5 transition">AM</button>
                <button type="button" @click="setAmPm(true)" :class="pm ? 'bg-emerald-600 text-white' : 'text-gray-600'" class="px-2 py-0.5 transition">PM</button>
            </div>
        </div>

        {{-- Analog face --}}
        <svg viewBox="0 0 200 200" class="mx-auto block h-52 w-52 touch-none select-none"
             @pointerdown="startDrag($event)" @pointermove="onDrag($event)"
             @pointerup="endDrag($event)" @pointercancel="endDrag($event)"
             x-ref="face">
            <circle cx="100" cy="100" r="96" fill="#f0fdf4" stroke="#a7f3d0" stroke-width="2"/>
            {{-- Hour numbers --}}
            <template x-for="n in 12" :key="n">
                <text :x="100 + 78 * Math.sin(n * Math.PI / 6)"
                      :y="100 - 78 * Math.cos(n * Math.PI / 6) + 5"
                      text-anchor="middle" class="fill-emerald-800 text-[13px] font-semibold" x-text="n"></text>
            </template>
            {{-- Minute ticks --}}
            <template x-for="t in 60" :key="'t'+t">
                <circle :cx="100 + 92 * Math.sin(t * Math.PI / 30)"
                        :cy="100 - 92 * Math.cos(t * Math.PI / 30)"
                        :r="t % 5 === 0 ? 1.6 : 0.7" fill="#34d399"/>
            </template>
            {{-- Active hand --}}
            <line x1="100" y1="100"
                  :x2="100 + handLen() * Math.sin(handAngle() * Math.PI / 180)"
                  :y2="100 - handLen() * Math.cos(handAngle() * Math.PI / 180)"
                  stroke="#059669" stroke-width="4" stroke-linecap="round"/>
            <circle :cx="100 + handLen() * Math.sin(handAngle() * Math.PI / 180)"
                    :cy="100 - handLen() * Math.cos(handAngle() * Math.PI / 180)"
                    r="12" fill="#059669" opacity="0.9"/>
            <circle cx="100" cy="100" r="4" fill="#065f46"/>
        </svg>

        <div class="mt-2 flex items-center justify-between">
            <p class="text-[11px] text-gray-500">Drag the hand, or tap Hour / Minute.</p>
            <button type="button" @click="open = false"
                    class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-emerald-700">Done</button>
        </div>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('timeClock', (initial, fallback) => ({
        open: false,
        unit: 'hour',
        h: 9,   // 0-23
        m: 0,

        init() {
            this.parse(initial || fallback || '09:00');
            // An empty field stays empty until the user commits a value.
            if (!initial) { this._pristine = true; }
        },

        get pm() { return this.h >= 12; },

        get value() {
            if (this._pristine) { return ''; }
            return this.pad(this.h) + ':' + this.pad(this.m);
        },

        pad(n) { return String(n).padStart(2, '0'); },

        display12() {
            const twelve = this.h % 12;
            return this.pad(twelve === 0 ? 12 : twelve);
        },

        parse(str) {
            const match = /^(\d{1,2}):(\d{2})$/.exec((str || '').trim());
            if (!match) { return; }
            this.h = Math.min(23, parseInt(match[1], 10));
            this.m = Math.min(59, parseInt(match[2], 10));
            this._pristine = false;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) { this.unit = 'hour'; this._pristine = false; }
        },

        setAmPm(wantPm) {
            const twelve = this.h % 12;
            this.h = wantPm ? twelve + 12 : twelve;
            this._pristine = false;
        },

        // Angle (degrees, clockwise from 12 o'clock) for the currently-shown hand.
        handAngle() {
            return this.unit === 'minute'
                ? this.m * 6
                : (this.h % 12) * 30 + this.m * 0.5;
        },

        handLen() { return this.unit === 'minute' ? 68 : 50; },

        startDrag(e) { this._dragging = true; this.setFromEvent(e); },
        onDrag(e) { if (this._dragging) { this.setFromEvent(e); } },
        endDrag() { this._dragging = false; },

        setFromEvent(e) {
            const rect = this.$refs.face.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width * 200 - 100;
            const py = (e.clientY - rect.top) / rect.height * 200 - 100;
            let deg = Math.atan2(px, -py) * 180 / Math.PI;
            if (deg < 0) { deg += 360; }
            this._pristine = false;

            if (this.unit === 'minute') {
                this.m = Math.round(deg / 6) % 60;
            } else {
                const twelve = Math.round(deg / 30) % 12; // 0..11, where 0 == 12 o'clock
                this.h = (this.pm ? 12 : 0) + twelve;
                // After choosing the hour, jump to minutes for a natural flow.
                this.unit = 'minute';
            }
        },
    }));
});
</script>
@endonce
