<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.departments.index') }}" class="text-sm font-medium text-emerald-600 hover:underline">Departments</a>
            <span class="text-gray-400">/</span>
            <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Add Department</h1>
        </div>
    </x-slot>

    <div class="page-shell page-shell-narrow">
        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">New Department</h2>
            </div>

            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-5 p-6">
                @csrf
                @include('admin.departments.partials.form-fields', ['department' => null])

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                    <a href="{{ route('admin.departments.index') }}"
                       class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                        Create Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
