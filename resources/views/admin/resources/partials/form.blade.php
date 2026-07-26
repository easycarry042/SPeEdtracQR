{{-- Shared resource form body. Expects nullable $resource. --}}
<div>
    <label for="res-name" class="block text-[13px] font-semibold text-ink">Resource Name <span class="text-status-red">*</span></label>
    <input id="res-name" type="text" name="name" value="{{ old('name', $resource?->name) }}" required autofocus
           placeholder="e.g. Covered Court"
           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('name') border-status-red @enderror">
    @error('name')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div>
    <label for="res-description" class="block text-[13px] font-semibold text-ink">Description</label>
    <textarea id="res-description" name="description" rows="2" maxlength="500"
              placeholder="What this resource is / any usage notes."
              class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('description') border-status-red @enderror">{{ old('description', $resource?->description) }}</textarea>
    @error('description')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<p class="text-[12px] text-ink-soft">Create a booking-kind <strong>Request Type</strong> pointing at this resource so citizens can reserve it.</p>
