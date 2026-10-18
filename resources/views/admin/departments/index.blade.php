<x-app-layout title="Departments">
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('success') }}</div></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">Every office: who supervises it, which citizen services it handles, and how long it has to act on an internal request.</p>
            <a href="{{ route('admin.departments.create') }}" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Department
            </a>
        </div>

        <div class="panel">
            <div class="table-wrap">
                <table class="reg">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Code</th>
                            <th>Supervisor</th>
                            <th>Members</th>
                            <th>Citizen services</th>
                            <th>Turnaround</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $department)
                            <tr>
                                <td class="nm text-ink">{{ $department->name }}</td>
                                <td><span class="id-chip">{{ $department->code }}</span></td>
                                <td>
                                    @forelse($supervisors->get($department->id, collect()) as $supervisor)
                                        <span class="block text-[13px] text-ink">{{ $supervisor->name }}</span>
                                    @empty
                                        {{-- Requests routed here notify no one but the super admins. --}}
                                        <span class="pill p-amber">No supervisor</span>
                                    @endforelse
                                </td>
                                <td class="muted">{{ $department->users_count }}</td>
                                <td class="muted">{{ $department->services_count ?: 'None (internal only)' }}</td>
                                <td class="muted">{{ \App\Support\Duration::hours($department->internalStepHours()) }}</td>
                                <td>
                                    <span class="pill {{ $department->is_active ? 'p-green' : 'p-muted' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.departments.edit', $department) }}" class="cr-btn cr-btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.departments.toggle-active', $department) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="cr-btn cr-btn-sm">{{ $department->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-[13px] text-ink-soft">
                                    No departments yet. Seed the defaults with <code class="code">php artisan db:seed --class=DepartmentSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
