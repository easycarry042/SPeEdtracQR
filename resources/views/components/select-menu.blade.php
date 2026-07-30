{{--
    Filter dropdown styled as the account menu is.

    A native <select> renders its popup through the OS, so its list cannot be
    themed — this is a listbox built from a button + panel instead, sharing the
    account menu's panel treatment (rounded-xl / bg-gray-100 / shadow-xl). The
    chosen value rides along in a hidden input, so the surrounding GET form
    submits exactly as it did with a <select>.

    @param string      $name     Submitted field name.
    @param array       $options  value => label, in menu order.
    @param string|null $selected Currently applied value.
    @param string|null $label    Small caption inside the trigger ("Range").
    @param bool     $submitOnChange Submit the enclosing form on pick (was onchange="this.form.submit()").
--}}
@props([
    'name',
    'options' => [],
    'selected' => null,
    'label' => null,
    'placeholder' => 'All',
    'submitOnChange' => false,
    'panelWidth' => 'w-56',
    'variant' => 'field',
])

@php
    /** @var array<int, array{value: string, label: string}> $items */
    $items = collect($options)
        ->map(fn ($optionLabel, $optionValue) => [
            'value' => (string) $optionValue,
            'label' => (string) $optionLabel,
        ])
        ->values()
        ->all();

    $current = (string) ($selected ?? '');
    $currentLabel = collect($items)->firstWhere('value', $current)['label'] ?? $placeholder;

    /* "field" wears the toolbar pill; "plain" brings only layout, so a caller
       can style the trigger to match a row of white Tailwind controls. */
    $triggerBase = $variant === 'plain'
        ? 'inline-flex h-ctl w-full cursor-pointer items-center justify-between gap-2 text-left'
        : 'field w-full cursor-pointer justify-between gap-2 text-left';
@endphp

<div class="relative"
     x-data="{
         open: false,
         value: @js($current),
         items: @js($items),
         submitOnChange: @js((bool) $submitOnChange),

         label() {
             const match = this.items.find((item) => item.value === this.value);

             return match ? match.label : @js($placeholder);
         },

         toggle() {
             this.open = ! this.open;

             if (this.open) {
                 this.$nextTick(() => this.focusOption(0));
             }
         },

         close(refocus = true) {
             this.open = false;

             if (refocus) {
                 this.$refs.trigger.focus();
             }
         },

         choose(item) {
             this.value = item.value;
             this.close();

             this.$nextTick(() => {
                 /* Setting the hidden input through Alpine fires no event, and
                    the live-filter scripts listen for `change` on the form's
                    controls — so raise it by hand. */
                 this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));

                 if (this.submitOnChange) {
                     this.$root.closest('form')?.submit();
                 }
             });
         },

         /** Roving focus through the rendered options. */
         options() {
             return Array.from(this.$refs.panel?.querySelectorAll('[role=option]') ?? []);
         },

         focusOption(index) {
             const options = this.options();

             if (options.length === 0) {
                 return;
             }

             const wrapped = (index + options.length) % options.length;
             options[wrapped].focus();
         },

         move(step) {
             const options = this.options();
             const from = options.indexOf(document.activeElement);

             this.focusOption(from === -1 ? 0 : from + step);
         },
     }"
     @keydown.escape.stop="open && close()"
     @click.outside="open = false">
    <input type="hidden" x-ref="input" data-filter-control name="{{ $name }}" :value="value" value="{{ $current }}">

    <button type="button"
            x-ref="trigger"
            @click="toggle()"
            @keydown.down.prevent="open ? move(1) : toggle()"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="listbox"
            @if($label) aria-label="{{ $label }}" @endif
            {{ $attributes->merge(['class' => $triggerBase]) }}>
        <span class="flex min-w-0 items-center gap-2">
            @if($label)
                <span class="shrink-0 text-[11px] text-ink-soft">{{ $label }}</span>
            @endif
            <span class="truncate text-[13px] text-ink" x-text="label()">{{ $currentLabel }}</span>
        </span>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
             class="h-4 w-4 shrink-0 text-ink-soft transition-transform duration-150" :class="open && 'rotate-180'">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    <div x-show="open"
         x-cloak
         x-ref="panel"
         role="listbox"
         @keydown.down.prevent="move(1)"
         @keydown.up.prevent="move(-1)"
         @keydown.home.prevent="focusOption(0)"
         @keydown.end.prevent="focusOption(-1)"
         class="dropdown-panel absolute left-0 z-50 mt-2 {{ $panelWidth }} max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-gray-100 py-1 shadow-xl shadow-gray-900/10">
        <template x-for="item in items" :key="item.value">
            <button type="button"
                    role="option"
                    :aria-selected="item.value === value ? 'true' : 'false'"
                    @click="choose(item)"
                    class="block w-full px-4 py-2.5 text-left text-sm font-medium transition hover:bg-gray-200/80 focus:bg-gray-200/80 focus:outline-none"
                    :class="item.value === value ? 'bg-gray-200/70 text-green-deep' : 'text-gray-700'"
                    x-text="item.label"></button>
        </template>
    </div>
</div>
