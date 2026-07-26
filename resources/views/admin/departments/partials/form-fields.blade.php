{{-- Shared fields for department create/edit. Expects nullable $department. --}}
<div>
    <label for="dept-name" class="block text-[13px] font-semibold text-ink">Department Name <span class="text-status-red">*</span></label>
    <input id="dept-name" type="text" name="name" value="{{ old('name', $department?->name) }}" required autofocus
           placeholder="e.g. Municipal Budget Office"
           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('name') border-status-red @enderror">
    @error('name')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div class="sm:w-1/2">
    <label for="dept-code" class="block text-[13px] font-semibold text-ink">Short Code <span class="text-status-red">*</span></label>
    <input id="dept-code" type="text" name="code" value="{{ old('code', $department?->code) }}" required maxlength="10"
           placeholder="e.g. BO"
           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 font-mono text-[13px] uppercase text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('code') border-status-red @enderror">
    <p class="mt-1 text-[12px] text-ink-soft">Letters and numbers only, up to 10 characters. Shown on badges and routing chains.</p>
    @error('code')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>
