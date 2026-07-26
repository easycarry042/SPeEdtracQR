{{-- Shared route template form body. Expects nullable $routeTemplate plus $departments. --}}
@php
    use App\Models\RouteTemplateStep;

    $initialSteps = old('steps', $routeTemplate?->steps->map(fn ($step) => [
        'step_order' => $step->step_order,
        'department_id' => (string) $step->department_id,
        'action' => $step->action,
        'condition' => $step->condition ?? '',
    ])->values()->all() ?? []);

    if (empty($initialSteps)) {
        $initialSteps = [['step_order' => 1, 'department_id' => '', 'action' => '', 'condition' => '']];
    }
@endphp

<div>
    <label for="tpl-name" class="block text-[13px] font-semibold text-ink">Template Name <span class="text-status-red">*</span></label>
    <input id="tpl-name" type="text" name="name" value="{{ old('name', $routeTemplate?->name) }}" required autofocus
           placeholder="e.g. Procurement Request"
           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('name') border-status-red @enderror">
    @error('name')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div>
    <label for="tpl-description" class="block text-[13px] font-semibold text-ink">Description</label>
    <textarea id="tpl-description" name="description" rows="2" maxlength="500"
              placeholder="When should supervisors pick this route?"
              class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('description') border-status-red @enderror">{{ old('description', $routeTemplate?->description) }}</textarea>
    @error('description')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
</div>

<div x-data="{
        steps: {{ Js::from($initialSteps) }},
        add() {
            const last = this.steps[this.steps.length - 1];
            this.steps.push({ step_order: last ? Number(last.step_order) + 1 : 1, department_id: '', action: '', condition: '' });
        },
        remove(index) {
            if (this.steps.length > 1) { this.steps.splice(index, 1); }
        },
    }">
    <label class="block text-[13px] font-semibold text-ink">Endorsement Steps <span class="text-status-red">*</span></label>
    @error('steps')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror

    {{-- The branch mechanic is expert logic; give it a real callout, not a faint hint. --}}
    <div class="mt-2 flex items-start gap-2 rounded-[8px] border border-hairline bg-green-wash/50 px-3 py-2 text-[12px] text-ink">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519A3 3 0 116 10.5m3.879-2.981L15 12m-5.121-4.481L6 5m9 7 3.121-3.519A3 3 0 1015 5m0 7v7"/></svg>
        <span>To <strong>branch</strong> a route (e.g. Small Value Procurement vs Public Bidding), give two steps the <strong>same step number</strong> with opposite amount conditions. Only the one matching the request's amount is used.</span>
    </div>

    <div class="mt-3 space-y-2">
        <template x-for="(step, index) in steps" :key="index">
            <div class="flex flex-wrap items-start gap-2 rounded-[8px] border border-hairline bg-[#f4f7f5] p-3">
                <div class="w-16">
                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">Step</label>
                    <input type="number" min="1" max="50" required x-model="step.step_order" :name="`steps[${index}][step_order]`"
                           class="mt-0.5 w-full rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-center text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                </div>
                <div class="min-w-[180px] flex-1">
                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">Department</label>
                    <select required x-model="step.department_id" :name="`steps[${index}][department_id]`"
                            class="mt-0.5 w-full rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                        <option value="">Select office…</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[160px] flex-1">
                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">Action</label>
                    <input type="text" required maxlength="100" x-model="step.action" :name="`steps[${index}][action]`"
                           placeholder="e.g. Approve request"
                           class="mt-0.5 w-full rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-ink-soft">Condition</label>
                    <select x-model="step.condition" :name="`steps[${index}][condition]`"
                            class="mt-0.5 w-full rounded-[6px] border border-hairline-strong bg-white px-2 py-2 text-[13px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                        <option value="">Always</option>
                        @foreach(RouteTemplateStep::CONDITIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" @click="remove(index)" x-show="steps.length > 1"
                        class="mt-5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[6px] border border-status-red-wash bg-status-red-wash text-status-red transition hover:brightness-95"
                        aria-label="Remove step">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <button type="button" @click="add()" class="cr-btn mt-3">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Step
    </button>
</div>
