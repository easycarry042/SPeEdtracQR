{{-- Shared resource form body. Expects nullable $resource, and $kindLocked on
     edit (the item has bookings, so it can no longer change kind). Counts are
     only asked for on create — after that they are kept on the Resources page,
     where the checks against what is out on loan live. --}}
@use('App\Models\Resource')
@php
    $kindLocked ??= false;
    $field = 'mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25';
@endphp

<div x-data="{ kind: @js(old('kind', $resource?->kind ?? Resource::KIND_EQUIPMENT)) }" class="space-y-5">
    <div>
        <label for="res-name" class="block text-[13px] font-semibold text-ink">Name <span class="text-status-red">*</span></label>
        <input id="res-name" type="text" name="name" value="{{ old('name', $resource?->name) }}" required autofocus
               placeholder="e.g. Monoblock Chairs, or Covered Court"
               class="{{ $field }} @error('name') border-status-red @enderror">
        @error('name')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
    </div>

    <fieldset>
        <legend class="block text-[13px] font-semibold text-ink">What kind of thing is it? <span class="text-status-red">*</span></legend>
        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-2 rounded-[8px] border border-hairline-strong bg-white p-3"
                   :class="kind === @js(Resource::KIND_EQUIPMENT) && 'border-green ring-2 ring-green/20'">
                <input type="radio" name="kind" value="{{ Resource::KIND_EQUIPMENT }}" x-model="kind" @disabled($kindLocked) class="mt-0.5">
                <span>
                    <span class="block text-[13px] font-semibold text-ink">Equipment</span>
                    <span class="block text-[12px] text-ink-soft">Counted and lent out — chairs, tables, tents. Several people can borrow from the pile.</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-2 rounded-[8px] border border-hairline-strong bg-white p-3"
                   :class="kind === @js(Resource::KIND_FACILITY) && 'border-green ring-2 ring-green/20'">
                <input type="radio" name="kind" value="{{ Resource::KIND_FACILITY }}" x-model="kind" @disabled($kindLocked) class="mt-0.5">
                <span>
                    <span class="block text-[13px] font-semibold text-ink">Facility</span>
                    <span class="block text-[12px] text-ink-soft">One venue, reserved by time — a court, a hall, a plaza.</span>
                </span>
            </label>
        </div>
        @if($kindLocked)
            {{-- Disabled radios are not submitted; send the current kind. --}}
            <input type="hidden" name="kind" value="{{ $resource->kind }}">
            <p class="mt-1 text-[12px] text-ink-soft">This item already has bookings, so its kind is fixed.</p>
        @endif
        @error('kind')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
    </fieldset>

    <div>
        <label for="res-location" class="block text-[13px] font-semibold text-ink">
            <span x-show="kind === @js(Resource::KIND_EQUIPMENT)">Where it is kept</span>
            <span x-show="kind !== @js(Resource::KIND_EQUIPMENT)" x-cloak>Where it is</span>
        </label>
        <input id="res-location" type="text" name="location" value="{{ old('location', $resource?->location) }}"
               placeholder="e.g. GSO stockroom" class="{{ $field }} @error('location') border-status-red @enderror">
        @error('location')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
    </div>

    @unless($resource)
        <div x-show="kind === @js(Resource::KIND_EQUIPMENT)">
            <label for="res-owned" class="block text-[13px] font-semibold text-ink">How many the office owns</label>
            <input id="res-owned" type="number" name="total_quantity" min="0" step="1" inputmode="numeric"
                   value="{{ old('total_quantity') }}" placeholder="e.g. 100"
                   class="{{ $field }} max-w-[12rem] @error('total_quantity') border-status-red @enderror">
            <p class="mt-1 text-[12px] text-ink-soft">Leave it empty if nobody has counted yet — staff can set it on the Resources page. Nothing can be lent until it is set.</p>
            @error('total_quantity')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
        </div>
        <div x-show="kind === @js(Resource::KIND_FACILITY)" x-cloak>
            <label for="res-capacity" class="block text-[13px] font-semibold text-ink">How many people it holds</label>
            <input id="res-capacity" type="number" name="capacity" min="0" step="1" inputmode="numeric"
                   value="{{ old('capacity') }}" class="{{ $field }} max-w-[12rem] @error('capacity') border-status-red @enderror">
            @error('capacity')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
        </div>
    @endunless

    <div>
        <label for="res-description" class="block text-[13px] font-semibold text-ink">Description</label>
        <textarea id="res-description" name="description" rows="2" maxlength="500"
                  placeholder="What this is / any usage notes."
                  class="{{ $field }} @error('description') border-status-red @enderror">{{ old('description', $resource?->description) }}</textarea>
        @error('description')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
    </div>

    <p class="text-[12px] text-ink-soft">
        So citizens can ask for it, add a <a href="{{ route('admin.request-types.index') }}" class="text-green underline">Request Type</a>
        pointing at this item — an <strong>equipment borrowing</strong> type for equipment, a <strong>facility booking</strong> type for a facility.
    </p>
</div>
