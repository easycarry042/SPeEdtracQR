<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.departments.index') }}" class="text-sm font-medium text-emerald-600 hover:underline">Departments</a>
            <span class="text-gray-400">/</span>
            <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Add Department</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-xl">
        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">Department Details</h2>
            </div>

            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-5 p-6">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-700">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus
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
