<x-app-layout>
    <div class="page-shell page-shell-loose">

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
        <form method="GET" id="userFilterForm" class="flex flex-wrap gap-3">
            <input type="text" name="search" id="userSearch" value="{{ request('search') }}" autocomplete="off"
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

        <div id="usersBar" class="cr-topbar mt-3 hidden" aria-hidden="true"></div>
        <div id="usersLive" class="sr-only" role="status" aria-live="polite"></div>

        {{-- Bulk action bar — appears when one or more accounts are selected --}}
        <div id="bulkBar" class="hidden items-center justify-between gap-3 rounded-xl border border-green bg-green-wash px-4 py-2.5">
            <p class="text-sm font-semibold text-green-deep"><span id="bulkCount">0</span> selected</p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="usersBulk('activate')" class="rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">Activate</button>
                <button type="button" onclick="usersBulk('deactivate')" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">Deactivate</button>
                <button type="button" onclick="usersBulk('archive')" class="rounded-lg border border-amber-200 bg-white px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-50">Archive</button>
                <button type="button" onclick="usersBulkClear()" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-ink-soft transition hover:text-ink">Clear</button>
            </div>
        </div>

        {{-- Table --}}
        <div id="usersResults" class="space-y-6 transition-opacity duration-150" aria-busy="false">
        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-4 py-3.5 text-left">
                                <input type="checkbox" id="selectAllUsers" aria-label="Select all accounts on this page" class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Email</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Role</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50/60 transition {{ $user->trashed() || ! $user->is_active ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3">
                                    @if(! $user->trashed() && $user->id !== auth()->id())
                                        <input type="checkbox" class="userRowCheck h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" data-id="{{ $user->id }}" aria-label="Select {{ $user->name }}">
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">
                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </span>
                                        <a href="{{ route('staff.profile', ['user' => $user->id]) }}" class="text-sm font-semibold text-gray-800 hover:text-emerald-700 hover:underline">{{ $user->name }}</a>
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
                                <td class="px-4 py-3">
                                    @if($user->trashed())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Archived
                                        </span>
                                    @elseif($user->is_active)
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
                                        @if($user->trashed())
                                            <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                    Restore
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                                  onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone.');">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                    Delete
                                                </button>
                                            </form>
                                        @else
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
                                                                    ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                                                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.users.archive', $user) }}"
                                                      onsubmit="return confirm('Archive {{ $user->name }}? They will no longer be able to log in until restored.');">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100">
                                                        Archive
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                                      onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone.');">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    @if(request()->hasAny(['search','role']))
                                        <x-empty-state icon="search" title="No users match your filters">
                                            Try a different name, email, or role — or clear the filters to see everyone.
                                            <x-slot:action>
                                                <a href="{{ route('admin.users.index') }}" class="cr-btn cr-btn-sm">Clear filters</a>
                                            </x-slot:action>
                                        </x-empty-state>
                                    @else
                                        <x-empty-state icon="users" title="No staff accounts yet">
                                            Add your office's staff so they can be assigned documents and tracked.
                                            <x-slot:action>
                                                <a href="{{ route('admin.users.create') }}" onclick="if (window.openAddUserModal) { event.preventDefault(); openAddUserModal(); }" class="cr-btn cr-btn-sm cr-btn-primary">Add a user</a>
                                            </x-slot:action>
                                        </x-empty-state>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $users->links() }}
        </div>{{-- /usersResults --}}
    </div>

    {{-- Add User modal — opened from the navbar button; auto-reopens after a failed validation round-trip --}}
    <div id="addUserModal"
         class="{{ old('from_user_modal') && $errors->any() ? '' : 'hidden' }} fixed inset-0 z-[100] overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="addUserModalTitle">
        <div class="fixed inset-0 bg-emerald-950/40 backdrop-blur-sm" data-close-add-user></div>

        <div class="relative flex min-h-full items-center justify-center p-4 sm:p-6">
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                    <h2 id="addUserModalTitle" class="text-xl font-bold tracking-tight text-emerald-950">Add User</h2>
                    <button type="button" data-close-add-user
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                            aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5 p-6">
                    @csrf
                    <input type="hidden" name="from_user_modal" value="1">

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('name') border-red-400 @enderror">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('email') border-red-400 @enderror">
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required autocomplete="new-password"
                                   class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('password') border-red-400 @enderror">
                            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" required autocomplete="new-password"
                                   class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Role <span class="text-red-500">*</span></label>
                            <select name="role" required
                                    class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:outline-none @error('role') border-red-400 @enderror">
                                <option value="">Select a role…</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" @selected(old('role') === $role->name)>
                                        {{ ucwords(str_replace('_', ' ', $role->name)) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Staff create &amp; scan documents · Receiving staff intake only · Department admin manages their office · Super admin has full access.</p>
                            @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div></div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                        <button type="button" data-close-add-user
                                class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('addUserModal');
            if (!modal) return;

            window.openAddUserModal = function () {
                modal.classList.remove('hidden');
                modal.querySelector('input[name="name"]')?.focus();
            };

            function closeModal() {
                modal.classList.add('hidden');
            }

            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-close-add-user]')) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });
        })();

        // Live search: debounce keystrokes, fetch the same page with the current
        // form values, and swap in the fresh table + pagination.
        (function () {
            const form = document.getElementById('userFilterForm');
            const input = document.getElementById('userSearch');
            const results = document.getElementById('usersResults');
            if (!form || !input || !results) return;

            let timer = null;
            let controller = null;

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(refreshResults, 300);
            });

            form.querySelectorAll('select').forEach(function (el) {
                el.addEventListener('change', refreshResults);
            });

            function refreshResults() {
                const params = new URLSearchParams(new FormData(form));
                const url = '{{ route('admin.users.index') }}' + (params.toString() ? '?' + params.toString() : '');

                controller?.abort();
                controller = new AbortController();

                const bar = document.getElementById('usersBar');
                const live = document.getElementById('usersLive');
                results.classList.add('opacity-50');
                results.setAttribute('aria-busy', 'true');
                bar?.classList.remove('hidden');
                if (live) { live.textContent = 'Loading users…'; }

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                    .then(response => response.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const fresh = doc.getElementById('usersResults');
                        if (fresh) { results.innerHTML = fresh.innerHTML; }
                        document.dispatchEvent(new CustomEvent('users:refreshed'));
                        window.history.replaceState({}, '', url);
                        results.classList.remove('opacity-50');
                        results.setAttribute('aria-busy', 'false');
                        bar?.classList.add('hidden');
                        if (live) {
                            const rows = results.querySelectorAll('tbody tr').length;
                            const hasEmpty = results.querySelector('.cr-empty');
                            live.textContent = hasEmpty ? 'No users match your filters.' : `${rows} ${rows === 1 ? 'user' : 'users'} found.`;
                        }
                    })
                    .catch(error => {
                        if (error.name !== 'AbortError') {
                            results.classList.remove('opacity-50');
                            results.setAttribute('aria-busy', 'false');
                            bar?.classList.add('hidden');
                            if (live) { live.textContent = 'Could not load users. Please try again.'; }
                            console.error(error);
                        }
                    });
            }
        })();
    </script>

    {{-- Bulk actions: select-all + per-row checkboxes → one action on many accounts.
         Uses event delegation so it survives the live-search innerHTML swaps, and
         builds a CSRF-signed form on submit (the row action cells already contain
         their own <form>s, so the table itself can't be wrapped in one). --}}
    <script>
        (function () {
            const bar   = document.getElementById('bulkBar');
            const count = document.getElementById('bulkCount');
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const bulkUrl = '{{ route('admin.users.bulk') }}';

            function checked() { return Array.from(document.querySelectorAll('.userRowCheck:checked')); }

            function updateBar() {
                const n = checked().length;
                count.textContent = n;
                bar.classList.toggle('hidden', n === 0);
                bar.classList.toggle('flex', n > 0);

                const all = Array.from(document.querySelectorAll('.userRowCheck'));
                const master = document.getElementById('selectAllUsers');
                if (master) {
                    master.checked = all.length > 0 && n === all.length;
                    master.indeterminate = n > 0 && n < all.length;
                }
            }

            document.addEventListener('change', function (e) {
                if (e.target.id === 'selectAllUsers') {
                    document.querySelectorAll('.userRowCheck').forEach(c => { c.checked = e.target.checked; });
                    updateBar();
                } else if (e.target.classList.contains('userRowCheck')) {
                    updateBar();
                }
            });

            // Recompute after a live-search swap replaces the rows.
            document.addEventListener('users:refreshed', updateBar);

            window.usersBulkClear = function () {
                document.querySelectorAll('.userRowCheck').forEach(c => { c.checked = false; });
                updateBar();
            };

            window.usersBulk = function (action) {
                const ids = checked().map(c => c.dataset.id);
                if (!ids.length) { return; }

                const nouns = ids.length === 1 ? 'account' : 'accounts';
                const prompts = {
                    activate: null,
                    deactivate: `Deactivate ${ids.length} ${nouns}? They won't be able to log in until reactivated.`,
                    archive: `Archive ${ids.length} ${nouns}? They'll be hidden and unable to log in until restored.`,
                };
                if (prompts[action] && !confirm(prompts[action])) { return; }

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = bulkUrl;
                form.innerHTML =
                    `<input type="hidden" name="_token" value="${token}">` +
                    `<input type="hidden" name="action" value="${action}">` +
                    ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
                document.body.appendChild(form);
                form.submit();
            };
        })();
    </script>
</x-app-layout>
