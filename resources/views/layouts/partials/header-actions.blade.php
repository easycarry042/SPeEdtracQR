@if(request()->routeIs('admin.users.index'))
<a href="{{ route('admin.users.create') }}"
   onclick="if (window.openAddUserModal) { event.preventDefault(); openAddUserModal(); }"
   class="inline-flex h-10 items-center gap-2 rounded-full bg-green-deep px-4 text-sm font-semibold text-on-green ring-1 ring-green-deep transition hover:bg-green active:scale-95">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
    Add User
</a>
@endif

@if($showCreateDocumentModal ?? false)
<button type="button" onclick="openCreateDocumentModal()" class="flex h-10 w-10 items-center justify-center rounded-full bg-green-wash text-green-deep ring-1 ring-hairline-strong transition hover:scale-105 hover:bg-emerald-300/90 hover:shadow-md active:scale-95" title="New document">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6M9 14h6"/>
    </svg>
</button>
@endif

<div class="relative"
     x-data="{ open: false, q: '', results: [],
        run() {
            if (! this.q.trim()) { this.results = []; return; }
            fetch('{{ route('staff.search') }}?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => { this.results = d; }).catch(() => { this.results = []; });
        } }">
    <button type="button"
            @click="open = ! open; if (open) $nextTick(() => $refs.pinput.focus())"
            class="flex h-10 w-10 items-center justify-center rounded-full bg-green-wash text-green-deep ring-1 ring-hairline-strong transition hover:scale-105 hover:bg-emerald-300/90 active:scale-95"
            title="Find a person" aria-haspopup="true">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" class="people-panel">
        <input type="text" x-ref="pinput" x-model="q" @input.debounce.300ms="run()" placeholder="Find a staff member…">
        <div class="mt-1">
            <template x-for="p in results" :key="p.url">
                <a :href="p.url" class="people-result"><span x-text="p.name"></span><span class="r" x-text="p.role"></span></a>
            </template>
            <div x-show="q.length && ! results.length" class="people-empty">No matches.</div>
        </div>
    </div>
</div>

<div class="relative" id="notifDropdown">
    <button type="button"
            id="notifBtn"
            onclick="toggleHeaderDropdown('notifPanel', 'profilePanel')"
            class="relative flex h-10 w-10 items-center justify-center rounded-full bg-green-wash text-green-deep ring-1 ring-hairline-strong transition hover:scale-105 hover:bg-emerald-300/90 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
            title="Notifications"
            aria-haspopup="true">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2z"/></svg>
        @if(($headerNotifications ?? collect())->isNotEmpty())
            <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $headerNotifications->count() }}</span>
        @endif
    </button>
    <div id="notifPanel"
         class="dropdown-panel hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-white py-2 shadow-xl shadow-gray-900/15"
         style="z-index:9999;">
        <p class="border-b border-gray-100 px-4 pb-2 text-xs font-semibold tracking-wide text-gray-500">Notifications</p>
        {{-- Department notifications were retired with the department model; an
             assignment-based notifications feed is a parked follow-up. --}}
        <p class="px-4 py-6 text-center text-sm text-gray-500">You&apos;re all caught up — no new notifications.</p>
    </div>
</div>

<div class="relative" id="profileDropdown">
    <button type="button"
            id="profileBtn"
            onclick="toggleHeaderDropdown('profilePanel', 'notifPanel')"
            class="inline-flex items-center gap-2 rounded-full bg-green-wash py-1.5 pl-1.5 pr-3 text-green-deep ring-1 ring-hairline-strong transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green focus-visible:ring-offset-2"
            aria-haspopup="true">
        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">{{ $initials }}</span>
        <svg class="h-4 w-4 text-emerald-900/70" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </button>
    <div id="profilePanel"
         class="dropdown-panel hidden absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-gray-100 py-1 shadow-xl shadow-gray-900/10"
         style="z-index:9999;">
        <div class="border-b border-gray-200 px-4 py-3">
            <p class="truncate text-sm font-semibold text-gray-900">{{ $name }}</p>
            @if($roleLabel)
                <p class="text-xs text-gray-500">{{ $roleLabel }}</p>
            @endif
        </div>
        <a href="{{ route('staff.profile', ['user' => auth()->id()]) }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">My Profile</a>
        <a href="{{ route('history') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">History</a>
        <a href="{{ route('staff.index') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">Staff directory</a>
        <div class="my-1 border-t border-gray-200"></div>
        <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">Settings</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50">Logout</button>
        </form>
    </div>
</div>

<script>
    function toggleHeaderDropdown(showId, hideId) {
        const show = document.getElementById(showId);
        const hide = document.getElementById(hideId);
        if (hide) hide.classList.add('hidden');
        if (show) show.classList.toggle('hidden');
    }

    document.addEventListener('click', function (e) {
        ['notifDropdown', 'profileDropdown'].forEach(function (wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            if (wrapper && !wrapper.contains(e.target)) {
                const panel = wrapper.querySelector('.dropdown-panel');
                if (panel) panel.classList.add('hidden');
            }
        });
    });
</script>
