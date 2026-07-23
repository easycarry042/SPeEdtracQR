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
    <label class="block text-sm font-semibold text-gray-700">Template Name <span class="text-red-500">*</span></label>
    <input type="text" name="name" value="{{ old('name', $routeTemplate?->name) }}" required autofocus
           placeholder="e.g. Procurement Request"
           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('name') border-red-400 @enderror">
    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label class="block text-sm font-semibold text-gray-700">Description</label>
    <textarea name="description" rows="2" maxlength="500"
              placeholder="When should supervisors pick this route?"
              class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('description') border-red-400 @enderror">{{ old('description', $routeTemplate?->description) }}</textarea>
    @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
    <div class="flex items-center justify-between">
        <label class="block text-sm font-semibold text-gray-700">Endorsement Steps <span class="text-red-500">*</span></label>
        <p class="text-xs text-gray-500">Same step number + opposite conditions = a branch (e.g. SVP vs Bidding).</p>
    </div>
    @error('steps')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

    <div class="mt-2 space-y-2">
        <template x-for="(step, index) in steps" :key="index">
            <div class="flex flex-wrap items-start gap-2 rounded-xl border border-gray-200 bg-gray-50/60 p-3">
                <div class="w-16">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Step</label>
                    <input type="number" min="1" max="50" required x-model="step.step_order" :name="`steps[${index}][step_order]`"
                           class="mt-0.5 w-full rounded-lg border border-gray-200 bg-white px-2 py-2 text-center text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                </div>
                <div class="min-w-[180px] flex-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Department</label>
                    <select required x-model="step.department_id" :name="`steps[${index}][department_id]`"
                            class="mt-0.5 w-full rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                        <option value="">Select office…</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[160px] flex-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Action</label>
                    <input type="text" required maxlength="100" x-model="step.action" :name="`steps[${index}][action]`"
                           placeholder="e.g. Approve request"
                           class="mt-0.5 w-full rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                </div>
                <div class="min-w-[200px] flex-1">
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-400">Condition</label>
                    <select x-model="step.condition" :name="`steps[${index}][condition]`"
                            class="mt-0.5 w-full rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                        <option value="">Always</option>
                        @foreach(RouteTemplateStep::CONDITIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" @click="remove(index)" x-show="steps.length > 1"
                        class="mt-5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-500 transition hover:bg-red-100"
                        aria-label="Remove step">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <button type="button" @click="add()"
            class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
        + Add Step
    </button>
</div>
