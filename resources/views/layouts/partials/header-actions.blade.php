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

{{-- The frame puts three controls on this side of the bar, each on its own white
     disc: scan, notifications, then the identity chip (Figma: STAFF LOOK UP,
     "Group 84"). The scan disc is how staff get to the QR hub when they have the
     paper in hand — the desk below is for when they do not. --}}
{{-- Opens the camera in a modal over whatever page staff are on. The href is
     the no-JavaScript fallback: the Look Up hub, with image upload. --}}
{{-- File Request: opens the filing form (layouts/app) over any page. The
     href is the no-JavaScript route to the same form on its own page. --}}
@if($fileRequestForm ?? null)
    <a href="{{ route('requests.create') }}" @click.prevent="$dispatch('open-file-request')"
       class="header-action" aria-haspopup="dialog">
        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
        <span>File Request</span>
    </a>
@endif

<div x-data="headerQrScanner" class="contents">
    <a href="{{ route('track.index', ['scan' => 1]) }}" @click.prevent="open()"
       class="header-action" aria-haspopup="dialog">
        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/>
            <path stroke-linecap="round" d="M7 12h10"/>
        </svg>
        <span>Scan QR Code</span>
    </a>

    {{-- Teleported to <body> so the header's own stacking and backdrop
         filter cannot clip a fixed overlay. --}}
    <template x-teleport="body">
        <div x-show="isOpen" x-cloak
             x-transition.opacity.duration.150ms
             @keydown.escape.window="isOpen && close()"
             @click.self="close()"
             class="fixed inset-0 z-[70] flex items-center justify-center bg-black/55 p-4"
             role="dialog" aria-modal="true" aria-labelledby="headerScanTitle">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-paper shadow-2xl">
                <div class="flex items-center justify-between border-b border-hairline px-5 py-3.5">
                    <h2 id="headerScanTitle" class="text-lg font-bold text-green-deep">Scan a QR code</h2>
                    <button type="button" @click="close()" aria-label="Close scanner"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-ink-soft transition hover:bg-green-wash hover:text-green-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <div class="p-5">
                    <div class="qr-viewport relative overflow-hidden rounded-xl bg-[#1e1e1e]">
                        {{-- .qr-surface: see .qr-viewport in app.css — a square box must not
                             distort the picture the decoder reads. --}}
                        <div id="headerScanReader" class="qr-surface"></div>
                        <p x-show="starting && !error" class="absolute inset-0 flex items-center justify-center text-sm font-medium text-white/80">
                            Starting camera…
                        </p>
                    </div>

                    <p x-show="!error" class="mt-3 text-center text-sm text-ink-soft">
                        Point the camera at the QR on the claim slip or folder.
                    </p>
                    <p x-show="error" x-text="error" class="mt-3 rounded-lg bg-status-red-wash px-3 py-2.5 text-sm font-semibold text-status-red" role="alert"></p>

                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <button type="button" x-show="error" @click="startCamera()"
                                class="rounded-lg bg-green px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-green/40">
                            Try again
                        </button>
                        <button type="button" @click="$refs.headerScanFile.click()"
                                class="rounded-lg border border-hairline-strong px-4 py-2 text-sm font-semibold text-green-deep transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green/40">
                            Upload QR image instead
                        </button>
                        <input x-ref="headerScanFile" type="file" accept="image/*" class="hidden" @change="readFile($event)">
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>

@include('partials.qr-scan-helpers')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('headerQrScanner', () => ({
            isOpen: false,
            starting: false,
            error: '',

            open() {
                this.isOpen = true;
                this.startCamera();
            },

            close() {
                this.isOpen = false;
                this.starting = false;
                window.SpeedQr.stop();
            },

            startCamera() {
                this.error = '';
                this.starting = true;
                window.SpeedQr.start('headerScanReader', (decodedText) => {
                    this.handle(decodedText, 'scan');
                }, (cameraError) => {
                    this.starting = false;
                    this.error = window.SpeedQr.describe(cameraError) + ' You can upload a QR image instead.';
                }).then(() => { this.starting = false; });
            },

            /**
             * Only codes this system issued may open a record. An internal
             * request waiting for this person to receive it is received by the
             * same scan (SpeedQr.openScanned).
             */
            handle(decodedText, method) {
                window.SpeedQr.stop();
                window.SpeedQr.openScanned(decodedText, method).catch((e) => {
                    this.error = e.message;
                });
            },

            async readFile(event) {
                const file = event.target.files && event.target.files[0];
                event.target.value = '';
                if (! file) {
                    return;
                }
                this.error = '';
                window.SpeedQr.stop();
                let decodedText;
                try {
                    decodedText = await window.SpeedQr.decodeImage(file);
                } catch (e) {
                    this.error = 'Could not read a QR code from that image. Use a clearer, well-lit photo.';

                    return;
                }
                this.handle(decodedText, 'image');
            },
        }));
    });
</script>

@php
    // Real unread feed for the bell (database notifications, see DocumentEvent).
    $bellUnread = auth()->check() ? auth()->user()->unreadNotifications()->latest()->take(10)->get() : collect();
    $bellCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp
<div class="relative" id="notifDropdown">
    <button type="button"
            id="notifBtn"
            onclick="toggleHeaderDropdown('notifPanel', 'profilePanel')"
            class="header-disc"
            title="Notifications"
            aria-label="Notifications{{ $bellCount > 0 ? ', '.$bellCount.' unread' : '' }}"
            aria-haspopup="true" aria-expanded="false">
        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Z"/>
            <path stroke-linecap="round" d="M10 20.5a2.2 2.2 0 0 0 4 0"/>
        </svg>
        @if($bellCount > 0)
            <span class="header-badge" aria-hidden="true">{{ $bellCount > 99 ? '99+' : $bellCount }}</span>
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

@php
    // The desk the signed-in user acts for. Department scoping decides what
    // they can see, so the chip names it rather than leaving it implied.
    $departmentName = auth()->user()?->department?->name;
@endphp
<div class="relative" id="profileDropdown">
    <button type="button"
            id="profileBtn"
            onclick="toggleHeaderDropdown('profilePanel', 'notifPanel')"
            class="header-chip"
            aria-label="Account menu for {{ $name }}"
            aria-haspopup="true" aria-expanded="false">
        <x-user-avatar :user="auth()->user()" :name="$name" class="header-avatar" />

        {{-- Name, then role and office. Drops below sm: the avatar and caret
             still open the menu. --}}
        <span class="header-who hidden sm:block">
            <span class="header-who-name">{{ $name }}</span>
            <span class="header-who-meta">{{ collect([$roleLabel, $departmentName])->filter()->implode(' · ') }}</span>
        </span>

        <svg class="header-caret" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
        </svg>
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
    function syncHeaderExpanded() {
        [['notifBtn', 'notifPanel'], ['profileBtn', 'profilePanel']].forEach(function (pair) {
            const button = document.getElementById(pair[0]);
            const panel = document.getElementById(pair[1]);
            if (button && panel) button.setAttribute('aria-expanded', panel.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    function toggleHeaderDropdown(showId, hideId) {
        const show = document.getElementById(showId);
        const hide = document.getElementById(hideId);
        if (hide) hide.classList.add('hidden');
        if (show) show.classList.toggle('hidden');
        syncHeaderExpanded();
    }

    document.addEventListener('click', function (e) {
        ['notifDropdown', 'profileDropdown'].forEach(function (wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            if (wrapper && !wrapper.contains(e.target)) {
                const panel = wrapper.querySelector('.dropdown-panel');
                if (panel) panel.classList.add('hidden');
            }
        });
        syncHeaderExpanded();
    });
</script>
