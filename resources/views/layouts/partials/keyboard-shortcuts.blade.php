{{-- Global keyboard accelerators for staff/admin (Flexibility & Efficiency).
     Gmail-style "g then key" navigation, "/" or ⌘/Ctrl-K to search, "?" for the
     cheat-sheet. Permission-gated so only reachable destinations are bound. The
     cheat-sheet doubles as contextual Help and is also openable from the account
     menu. Motion respects the global prefers-reduced-motion reset. --}}
@php
    $shortcuts = collect([
        ['key' => 'd', 'label' => 'Dashboard',        'url' => $isSupervisor ? route('dashboard') : ($isSystemAdmin ? route('admin.dashboard') : route('staff.dashboard')), 'show' => true],
        ['key' => 'a', 'label' => 'Assignments',       'url' => \Illuminate\Support\Facades\Route::has('admin.assignments.index') ? route('admin.assignments.index') : null, 'show' => auth()->user()?->can('assign documents')],
        ['key' => 'u', 'label' => 'Users',             'url' => \Illuminate\Support\Facades\Route::has('admin.users.index') ? route('admin.users.index') : null, 'show' => auth()->user()?->can('manage users')],
        ['key' => 'r', 'label' => 'Internal requests', 'url' => \Illuminate\Support\Facades\Route::has('requests.index') ? route('requests.index') : null, 'show' => auth()->user()?->can('manage system')],
        ['key' => 'p', 'label' => 'Departments',       'url' => \Illuminate\Support\Facades\Route::has('admin.departments.index') ? route('admin.departments.index') : null, 'show' => auth()->user()?->can('manage system')],
        ['key' => 't', 'label' => 'Route templates',   'url' => \Illuminate\Support\Facades\Route::has('admin.route-templates.index') ? route('admin.route-templates.index') : null, 'show' => auth()->user()?->can('manage system')],
        ['key' => 'l', 'label' => 'Audit log',         'url' => \Illuminate\Support\Facades\Route::has('admin.audit-log.index') ? route('admin.audit-log.index') : null, 'show' => auth()->user()?->can('manage system')],
        ['key' => 'h', 'label' => 'History',           'url' => \Illuminate\Support\Facades\Route::has('history') ? route('history') : null, 'show' => true],
    ])->filter(fn ($s) => $s['show'] && $s['url'])->values();
@endphp

<div id="kbdHelp" class="kbd-help hidden" role="dialog" aria-modal="true" aria-labelledby="kbdHelpTitle">
    <div class="kbd-help-backdrop" data-kbd-close></div>
    <div class="kbd-help-card" role="document">
        <div class="kbd-help-head">
            <h2 id="kbdHelpTitle">Keyboard shortcuts</h2>
            <button type="button" data-kbd-close aria-label="Close shortcuts"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-ink-soft transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="kbd-help-body">
            <section>
                <h3>Go to</h3>
                <ul>
                    @foreach($shortcuts as $s)
                        <li><span class="kbd-combo"><kbd>g</kbd> <kbd>{{ $s['key'] }}</kbd></span><span>{{ $s['label'] }}</span></li>
                    @endforeach
                </ul>
            </section>
            <section>
                <h3>Actions</h3>
                <ul>
                    <li><span class="kbd-combo"><kbd>/</kbd></span><span>Search this page</span></li>
                    <li><span class="kbd-combo"><kbd>?</kbd></span><span>Show this help</span></li>
                    <li><span class="kbd-combo"><kbd>Esc</kbd></span><span>Close menus &amp; dialogs</span></li>
                </ul>
            </section>
        </div>
        <p class="kbd-help-foot">Press <kbd>g</kbd> then a letter to jump. Shortcuts are ignored while typing in a field.</p>
    </div>
</div>

<script>
(function () {
    const NAV = @json($shortcuts->pluck('url', 'key'));
    const overlay = document.getElementById('kbdHelp');
    let gPending = false, gTimer = null;

    function typingContext(el) {
        if (!el) { return false; }
        const tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }
    function openHelp() {
        overlay.classList.remove('hidden');
        overlay.querySelector('[data-kbd-close]')?.focus();
    }
    function closeHelp() { overlay.classList.add('hidden'); }
    function focusSearch() {
        const s = document.querySelector('input[type="search"], input[name="search"], #userSearch, [data-kbd-search]');
        if (s) { s.focus(); s.select?.(); return true; }
        return false;
    }

    overlay.addEventListener('click', (e) => { if (e.target.closest('[data-kbd-close]')) { closeHelp(); } });
    window.addEventListener('kbd-help', openHelp);

    document.addEventListener('keydown', function (e) {
        // ⌘/Ctrl-K → search, allowed even from a field.
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            if (focusSearch()) { e.preventDefault(); }
            return;
        }
        if (e.metaKey || e.ctrlKey || e.altKey) { return; }

        if (e.key === 'Escape') { if (!overlay.classList.contains('hidden')) { closeHelp(); } gPending = false; return; }
        if (typingContext(e.target)) { return; }

        if (e.key === '?') { e.preventDefault(); overlay.classList.contains('hidden') ? openHelp() : closeHelp(); return; }
        if (e.key === '/') { if (focusSearch()) { e.preventDefault(); } return; }

        if (gPending) {
            gPending = false;
            clearTimeout(gTimer);
            const dest = NAV[e.key.toLowerCase()];
            if (dest) { e.preventDefault(); window.location.href = dest; }
            return;
        }
        if (e.key.toLowerCase() === 'g') {
            gPending = true;
            gTimer = setTimeout(() => { gPending = false; }, 1200);
        }
    });
})();
</script>
