<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-emerald-600 hover:underline">Users</a>
            <span class="text-gray-400">/</span>
            <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Edit {{ $user->name }}</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-5">

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">Account Details</h2>
            </div>

            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5 p-6">
                @csrf @method('PUT')

                <div>
                    <label class="block text-sm font-semibold text-gray-700">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('name') border-red-400 @enderror">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('email') border-red-400 @enderror">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">New Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('password') border-red-400 @enderror">
                        @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Confirm New Password</label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Role <span class="text-red-500">*</span></label>
                        <select name="role" required
                                class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:outline-none @error('role') border-red-400 @enderror">
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" @selected(old('role', $user->roles->first()?->name) === $role->name)>
                                    {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Department</label>
                        @if($deptLocked)
                            <input type="text" value="{{ $departments->first()?->name ?? '—' }}" disabled
                                   class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-100 px-4 py-2.5 text-sm text-gray-500 shadow-sm cursor-not-allowed">
                            <input type="hidden" name="department_id" value="{{ $departments->first()?->id }}">
                        @else
                            <select name="department_id"
                                    class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:outline-none">
                                <option value="">None</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected(old('department_id', $user->department_id) == $dept->id)>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 pt-4">
                    @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}" class="m-0">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="rounded-xl border px-4 py-2.5 text-sm font-semibold transition
                                        {{ $user->is_active
                                            ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                {{ $user->is_active ? 'Deactivate Account' : 'Activate Account' }}
                            </button>
                        </form>
                    @else
                        <span></span>
                    @endif

                    <div class="flex items-center gap-3">
                        <a href="{{ route('admin.users.index') }}"
                           class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>