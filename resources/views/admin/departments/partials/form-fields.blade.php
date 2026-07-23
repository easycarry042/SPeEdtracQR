{{-- Shared fields for department create/edit. Expects nullable $department. --}}
<div>
    <label class="block text-sm font-semibold text-gray-700">Department Name <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $department?->name) }}" required autofocus
           placeholder="e.g. Municipal Budget Office"
           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('name') border-red-400 @enderror">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div class="sm:w-1/2">
    <label class="block text-sm font-semibold text-gray-700">Short Code <span class="text-red-500">*</span></label>
    <input type="text" name="code" value="{{ old('code', $department?->code) }}" required maxlength="10"
           placeholder="e.g. BO"
           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 font-mono text-sm uppercase shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('code') border-red-400 @enderror">
    <p class="mt-1 text-xs text-gray-500">Letters and numbers only, up to 10 characters. Shown on badges and routing chains.</p>
    @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
