@if(request()->routeIs('admin.users.index'))
<a href="{{ route('admin.users.create') }}"
   onclick="if (window.openAddUserModal) { event.preventDefault(); openAddUserModal(); }"
   class="inline-flex h-10 items-center gap-2 rounded-full bg-green-deep px-4 text-sm font-semibold text-on-green ring-1 ring-green-deep transition hover:bg-green active:scale-95">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
    Add User
</a>
@endif

@if($showCreateDocumentModal ?? false)
<button type="button" onclick="openCreateDocumentModal()" class="flex h-11 w-11 items-center justify-center rounded-full bg-green-wash text-green-deep ring-1 ring-hairline-strong transition hover:scale-105 hover:bg-emerald-300/90 hover:shadow-md active:scale-95" title="New document">
    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6M9 14h6"/>
    </svg>
</button>
@endif

{{-- No global search here: searching is offered inside the tab that needs it
     (the Requests table below the supervisor dashboard, the staff directory, the
     assignments desk, History), which keeps the header to identity + alerts. --}}

@php
    // Real unread feed for the bell (database notifications, see DocumentEvent).
    $bellUnread = auth()->check() ? auth()->user()->unreadNotifications()->latest()->take(10)->get() : collect();
    $bellCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp
<div class="relative" id="notifDropdown">
    <button type="button"
            id="notifBtn"
            onclick="toggleHeaderDropdown('notifPanel', 'profilePanel')"
            class="relative flex h-11 w-11 items-center justify-center rounded-full bg-green-wash text-green-deep ring-1 ring-hairline-strong transition hover:scale-105 hover:bg-emerald-300/90 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2"
            title="Notifications"
            aria-haspopup="true">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2z"/></svg>
        @if($bellCount > 0)
            <span class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>
        @endif
    </button>
    <div id="notifPanel"
         class="dropdown-panel hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-gray-200 bg-gray-100 py-2 shadow-xl shadow-gray-900/10"
         style="z-index:9999;">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 pb-2">
            <p class="text-xs font-semibold tracking-wide text-gray-500">Notifications</p>
            @if($bellCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-[11px] font-semibold text-green hover:underline">Mark all as read</button>
                </form>
            @endif
        </div>
        @forelse($bellUnread as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}"
               class="block border-b border-gray-200/70 px-4 py-2.5 transition hover:bg-gray-200/80">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-[13px] font-bold text-ink">{{ data_get($notification->data, 'title', 'Update') }}</p>
                    <span class="shrink-0 text-[11px] text-ink-soft">{{ $notification->created_at->diffForHumans(short: true) }}</span>
                </div>
                <p class="mt-0.5 line-clamp-2 text-xs text-ink-soft">{{ data_get($notification->data, 'body') }}</p>
                <p class="mt-0.5 font-mono text-[10.5px] text-green-deep">{{ data_get($notification->data, 'tracking') }}</p>
            </a>
        @empty
            <p class="px-4 py-6 text-center text-sm text-gray-500">You&apos;re all caught up — no new notifications.</p>
        @endforelse
        @if($bellCount > 10)
            <p class="px-4 pt-2 text-center text-[11px] text-ink-soft">+ {{ $bellCount - 10 }} more unread</p>
        @endif
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
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('kbd-help'))"
                class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">
            Keyboard shortcuts <kbd class="rounded border border-hairline-strong bg-paper px-1.5 py-0.5 font-mono text-[11px] text-ink-soft">?</kbd>
        </button>
        {{-- Staff directory has its own super_admin sidebar entry, so it only
             shows here for topnav roles. Settings lives in this menu for
             everyone — it is not in the sidebar. --}}
        @unless(auth()->user()?->can('manage system'))
            <a href="{{ route('staff.index') }}" class="block px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-200/80">Staff directory</a>
        @endunless
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
