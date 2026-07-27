{{-- Shared request-type form body. Expects nullable $requestType. --}}
@php
    use App\Models\RequestType;

    $initialRequirements = collect(old('requirements', $requestType?->requirements->map(fn ($r) => [
        'label' => $r->label,
        'is_mandatory' => (bool) $r->is_mandatory,
    ])->values()->all() ?? []))->map(fn ($r) => [
        'label' => $r['label'] ?? '',
        'is_mandatory' => (bool) ($r['is_mandatory'] ?? false),
    ])->values()->all();
@endphp

<div>
    <label for="rt-name" class="block text-[13px] font-semibold text-ink">Request Type Name <span class="text-status-red">*</span></label>
    <input id="rt-name" type="text" name="name" value="{{ old('name', $requestType?->name) }}" required autofocus
           placeholder="e.g. Business Permit"
           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('name') border-status-red @enderror">
    @error('name')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div x-data="{ kind: '{{ old('kind', $requestType?->kind ?? RequestType::KIND_DOCUMENT) }}' }">
    <label for="rt-kind" class="block text-[13px] font-semibold text-ink">Kind <span class="text-status-red">*</span></label>
    <select id="rt-kind" name="kind" x-model="kind"
            class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
        <option value="{{ RequestType::KIND_DOCUMENT }}">Document / Permit (produces a document)</option>
        <option value="{{ RequestType::KIND_BOOKING }}">Facility reservation (reserves a place for a time)</option>
        <option value="{{ RequestType::KIND_EQUIPMENT }}">Equipment borrowing (a quantity of items for a date)</option>
        <option value="{{ RequestType::KIND_SERVICE }}">Service / Production (make a quantity by a date — e.g. lei making)</option>
    </select>
    @error('kind')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror

    {{-- Facility bookings and equipment borrowing both reserve a specific resource. --}}
    <div x-show="kind === '{{ RequestType::KIND_BOOKING }}' || kind === '{{ RequestType::KIND_EQUIPMENT }}'" x-cloak class="mt-3">
        <label for="rt-resource" class="block text-[13px] font-semibold text-ink">
            <span x-show="kind === '{{ RequestType::KIND_BOOKING }}'">Facility to reserve</span>
            <span x-show="kind === '{{ RequestType::KIND_EQUIPMENT }}'" x-cloak>Item to borrow</span>
            <span class="text-status-red">*</span>
        </label>
        <select id="rt-resource" name="resource_id"
                class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
            <option value="">Select a resource…</option>
            @foreach($resources as $resource)
                <option value="{{ $resource->id }}" @selected((int) old('resource_id', $requestType?->resource_id) === $resource->id)>{{ $resource->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[12px] text-ink-soft">Manage resources under <a href="{{ route('admin.resources.index') }}" class="text-green underline">Resources</a>. The requirements checklist below applies to every kind.</p>
        @error('resource_id')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
    </div>
</div>

<div>
    <label for="rt-department" class="block text-[13px] font-semibold text-ink">Handling Department</label>
    <select id="rt-department" name="department_id"
            class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('department_id') border-status-red @enderror">
        <option value="">Unassigned — a Super Admin/Supervisor routes it manually</option>
        @foreach($departments as $department)
            <option value="{{ $department->id }}" @selected((int) old('department_id', $requestType?->department_id) === $department->id)>{{ $department->name }} ({{ $department->code }})</option>
        @endforeach
    </select>
    <p class="mt-1 text-[12px] text-ink-soft">Tickets of this type route to this department's queue on submission; the department's Supervisor assigns a staff member.</p>
    @error('department_id')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div>
    <label for="rt-description" class="block text-[13px] font-semibold text-ink">Description</label>
    <textarea id="rt-description" name="description" rows="2" maxlength="500"
              placeholder="Shown to help staff/citizens understand this request."
              class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('description') border-status-red @enderror">{{ old('description', $requestType?->description) }}</textarea>
    @error('description')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div x-data="{
        reqs: {{ Js::from($initialRequirements) }},
        add() { this.reqs.push({ label: '', is_mandatory: true }); },
        remove(i) { this.reqs.splice(i, 1); },
    }">
    <label class="block text-[13px] font-semibold text-ink">Requirements checklist</label>
    <p class="mt-0.5 text-[12px] text-ink-soft">Supporting documents the citizen must present (e.g. Cedula, Barangay Clearance). Staff verify each before approval. Leave empty for types that need none.</p>
    @error('requirements')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror

    <div class="mt-3 space-y-2">
        <template x-for="(req, index) in reqs" :key="index">
            <div class="flex flex-wrap items-center gap-2 rounded-[8px] border border-hairline bg-[#f4f7f5] p-3">
                <div class="min-w-[220px] flex-1">
                    <input type="text" required maxlength="255" x-model="req.label" :name="`requirements[${index}][label]`"
                           placeholder="e.g. Barangay Business Clearance"
                           class="w-full rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                </div>
                <label class="flex items-center gap-1.5 text-[12px] font-medium text-ink">
                    <input type="checkbox" value="1" x-model="req.is_mandatory" :name="`requirements[${index}][is_mandatory]`"
                           class="h-4 w-4 rounded border-hairline-strong text-green focus:ring-green">
                    Required
                </label>
                <button type="button" @click="remove(index)"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-status-red-wash bg-status-red-wash text-status-red transition hover:brightness-95"
                        aria-label="Remove requirement">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
        <p x-show="reqs.length === 0" class="text-[12px] text-ink-soft">No requirements — citizens just submit the request.</p>
    </div>

    <button type="button" @click="add()" class="cr-btn mt-3">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Requirement
    </button>
</div>
