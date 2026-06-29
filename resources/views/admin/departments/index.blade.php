<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6">

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="tiles">
            <div class="tile">
                <div class="k">Departments</div>
                <div class="v mono">{{ $departmentsTotal }}</div>
                <div class="bar"></div>
            </div>
            <div class="tile">
                <div class="k">Tightest SLA</div>
                <div class="v mono">{{ $tightestSlaDept?->sla_hours ?? '—' }}h</div>
                <div class="sub">{{ $tightestSlaDept?->name ?? '—' }}</div>
                <div class="bar"></div>
            </div>
            <div class="tile">
                <div class="k">In queue now</div>
                <div class="v mono">{{ $queueTotal }}</div>
                <div class="sub">{{ $queueOverdueTotal }} overdue</div>
                <div class="bar {{ $queueOverdueTotal > 0 ? 'red' : '' }}"></div>
            </div>
            <button type="button" onclick="if (window.openAddDepartmentModal) openAddDepartmentModal();"
                    class="tile add flex items-center justify-center gap-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                <span style="font-weight:600;font-size:13px;">Add department</span>
            </button>
        </div>

        <div class="deptcards">
            @forelse($departments as $dept)
                @php
                    $stats = $departmentStats[$dept->id] ?? ['in_queue' => 0, 'overdue' => 0, 'avg_pct' => 0, 'health' => 'healthy'];
                    $badge = match ($stats['health']) {
                        'overdue' => ['p-red', 'Overdue'],
                        'watch' => ['p-amber', 'Watch'],
                        default => ['p-green', 'Healthy'],
                    };
                    $fill = match ($stats['health']) {
                        'overdue' => 'red',
                        'watch' => 'amber',
                        default => '',
                    };
                @endphp
                <div class="deptcard">
                    <div class="dc-head">
                        <div>
                            <div class="dc-name">{{ $dept->name }}</div>
                            @if($dept->email)
                                <div class="dc-email">{{ $dept->email }}</div>
                            @endif
                        </div>
                        <span class="pill {{ $badge[0] }}">{{ $badge[1] }}</span>
                    </div>
                    <div class="dc-row"><span>SLA target</span><span class="mono">{{ $dept->sla_hours }}h</span></div>
                    <div class="dc-bar"><div class="dc-fill {{ $fill }}" style="width:{{ $stats['avg_pct'] }}%"></div></div>
                    <div class="dc-row"><span>In queue</span><span class="mono">{{ $stats['in_queue'] }}</span></div>
                    <div class="dc-foot">
                        <a href="{{ route('admin.departments.edit', $dept) }}" class="cr-btn cr-btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}"
                              onsubmit="return confirm('Delete {{ addslashes($dept->name) }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="panel" style="grid-column:1/-1;padding:30px;text-align:center;color:var(--ink-soft);">
                    No departments yet. <a href="{{ route('admin.departments.create') }}" onclick="if (window.openAddDepartmentModal) { event.preventDefault(); openAddDepartmentModal(); }" class="cr-link">Add one.</a>
                </div>
            @endforelse

            <button type="button" onclick="if (window.openAddDepartmentModal) openAddDepartmentModal();"
                    class="deptcard add flex flex-col items-center justify-center gap-2" style="min-height:140px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                <span style="font-size:12.5px;text-align:center;">Add a new department<br>and set its SLA</span>
            </button>
        </div>

        {{ $departments->links() }}
    </div>

    {{-- Add Department modal — opened from the navbar button; auto-reopens after a failed validation round-trip --}}
    <div id="addDepartmentModal"
         class="{{ old('from_department_modal') && $errors->any() ? '' : 'hidden' }} fixed inset-0 z-[100] overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="addDepartmentModalTitle">
        <div class="fixed inset-0 bg-emerald-950/40 backdrop-blur-sm" data-close-add-department></div>

        <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                    <h2 id="addDepartmentModalTitle" class="text-xl font-bold tracking-tight text-emerald-950">Add Department</h2>
                    <button type="button" data-close-add-department
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                            aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-5 p-6">
                    @csrf
                    <input type="hidden" name="from_department_modal" value="1">

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Department Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('name') border-red-400 @enderror"
                               placeholder="e.g. Records Office">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Short Code</label>
                        <input type="text" name="code" value="{{ old('code') }}" maxlength="20"
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-mono shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('code') border-red-400 @enderror"
                               placeholder="e.g. REC">
                        <p class="mt-1 text-xs text-gray-400">Optional. Used as a short identifier in reports.</p>
                        @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Alert Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('email') border-red-400 @enderror"
                               placeholder="department@office.gov">
                        <p class="mt-1 text-xs text-gray-400">Receives SLA warning and breach notifications.</p>
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">SLA Hours <span class="text-red-500">*</span></label>
                        <input type="number" name="sla_hours" value="{{ old('sla_hours', 48) }}" required min="1" max="8760"
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('sla_hours') border-red-400 @enderror">
                        <p class="mt-1 text-xs text-gray-400">Maximum hours a document may stay in this department before an alert is sent.</p>
                        @error('sla_hours')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <button type="button" data-close-add-department
                                class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                            Create Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('addDepartmentModal');
            if (!modal) return;

            window.openAddDepartmentModal = function () {
                modal.classList.remove('hidden');
                modal.querySelector('input[name="name"]')?.focus();
            };

            function closeModal() {
                modal.classList.add('hidden');
            }

            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-close-add-department]')) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });
        })();
    </script>
</x-app-layout>
