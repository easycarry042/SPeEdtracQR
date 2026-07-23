<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Offices available for internal request routing.</p>
            <a href="{{ route('admin.departments.create') }}"
               class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                + Add Department
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Department</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Code</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Members</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($departments as $department)
                            <tr class="hover:bg-gray-50/60 transition {{ $department->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $department->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 font-mono text-xs font-semibold text-gray-600">{{ $department->code }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $department->users_count }}</td>
                                <td class="px-4 py-3">
                                    @if($department->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.departments.edit', $department) }}"
                                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.departments.toggle-active', $department) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition
                                                        {{ $department->is_active
                                                            ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100'
                                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                {{ $department->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No departments yet. Seed the defaults with <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">php artisan db:seed --class=DepartmentSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
