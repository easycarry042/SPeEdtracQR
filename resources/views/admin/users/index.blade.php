<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Users</h1>
            <a href="{{ route('admin.users.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add User
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">

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

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search name or email…"
                   class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 min-w-[200px]">
            <select name="role" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" @selected(request('role') === $role->name)>
                        {{ ucfirst($role->name) }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">
                Filter
            </button>
            @if(request()->hasAny(['search','role']))
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>

        {{-- Table --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Department</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50/60 transition {{ ! $user->is_active ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </span>
                                        <span class="text-sm font-semibold text-gray-800">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                            {{ ucfirst($role->name) }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $user->department->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($user->is_active)
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
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                            Edit
                                        </a>
                                        @if($user->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition
                                                            {{ $user->is_active
                                                                ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100'
                                                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
