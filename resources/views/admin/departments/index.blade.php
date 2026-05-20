<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Departments</h1>
            <a href="{{ route('admin.departments.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Department
            </a>
        </div>
    </x-slot>

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

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Code</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Alert Email</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">SLA (hrs)</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($departments as $dept)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-800">{{ $dept->name }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ $dept->code ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $dept->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700 font-semibold">{{ $dept->sla_hours }}h</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.departments.edit', $dept) }}"
                                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.departments.destroy', $dept) }}"
                                              onsubmit="return confirm('Delete {{ addslashes($dept->name) }}? This cannot be undone.')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No departments yet. <a href="{{ route('admin.departments.create') }}" class="text-emerald-600 hover:underline">Add one.</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $departments->links() }}
    </div>
</x-app-layout>
