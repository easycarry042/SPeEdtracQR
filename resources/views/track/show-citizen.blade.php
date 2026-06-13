@php
    $statusColor = match($document->status) {
        'completed'  => ['bg' => 'bg-green-100',  'text' => 'text-green-800',  'dot' => 'bg-green-500',  'label' => 'Completed'],
        'pending'    => ['bg' => 'bg-blue-100',   'text' => 'text-blue-800',   'dot' => 'bg-blue-500',   'label' => 'Pending'],
        'returned'   => ['bg' => 'bg-rose-100',   'text' => 'text-rose-800',   'dot' => 'bg-rose-500',   'label' => 'Returned'],
        default      => ['bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'dot' => 'bg-amber-500',  'label' => 'In Transit'],
    };
@endphp

<x-citizen-layout>
    <x-slot name="title">Tracking {{ $document->tracking_number }}</x-slot>

    {{-- Back link --}}
    <div class="mb-6">
        <a href="{{ route('citizen.track') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Track Another Document
        </a>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">

        {{-- ── Document Header Card ─────────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-emerald-600 px-6 py-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-200">Tracking Number</p>
                <p class="mt-0.5 font-mono text-2xl font-extrabold text-white">{{ $document->tracking_number }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Document Type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->document_type }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Citizen</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->citizen_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Submitted</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->created_at->format('M d, Y') }}</p>
                </div>
            </div>

            {{-- Internal document images are intentionally NOT shown on the public
                 tracking page; they are only visible to authorized staff. --}}
        </div>

        {{-- ── Live Status Card ─────────────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" id="statusCard">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Current Status</h2>
                <div class="flex items-center gap-2 text-xs text-gray-400" id="lastChecked">
                    <svg class="h-3.5 w-3.5 animate-spin text-emerald-500 hidden" id="pollSpinner" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="lastCheckedText">Auto-updates every 30 s</span>
                </div>
            </div>

            <div class="p-6">
                {{-- Status badge --}}
                <div class="flex flex-wrap items-center gap-4">
                    <span id="statusBadge"
                          class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold {{ $statusColor['bg'] }} {{ $statusColor['text'] }}">
                        <span class="h-2 w-2 rounded-full {{ $statusColor['dot'] }}"></span>
                        <span id="statusLabel">{{ $statusColor['label'] }}</span>
                    </span>

                    <div>
                        <p class="text-xs text-gray-400">Current Location</p>
                        <p class="text-sm font-semibold text-gray-800" id="currentDept">
                            {{ $document->currentDepartment->name ?? 'Not yet assigned' }}
                        </p>
                    </div>
                </div>

                {{-- Update banner (hidden until status changes) --}}
                <div id="updateBanner"
                     class="mt-4 hidden rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    Status updated! Refreshing…
                </div>
            </div>
        </div>

        {{-- ── Predicted completion (self-hosted analytics) ─────────────────── --}}
        <x-eta-estimate :prediction="$prediction ?? null" :document="$document" />

        {{-- ── Self-hosted AI assistant (Pillar 3) ──────────────────────────── --}}
        <x-doc-assistant :document="$document" />

        @if($routingChain->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-5 shadow-sm">
            <h2 class="mb-4 text-base font-bold text-gray-800">Department Progress</h2>
            <x-routing-stepper :document="$document" :chain="$routingChain" />
        </div>
        @endif

        {{-- ── Citizen upload (notifies current department only) ──────────────── --}}
        @if($document->status !== 'completed')
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Upload supporting documents</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Files are sent only to
                    <span class="font-semibold text-emerald-800">{{ $document->currentDepartment->name ?? ($routingChain->first()?->name ?? 'the office handling your ticket') }}</span>.
                </p>
            </div>
            <div class="px-6 py-5">
                @if(session('upload_success'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ session('upload_success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ $errors->first() }}
                    </div>
                @endif
                <form method="POST" action="{{ route('track.citizen-upload', $document->tracking_number) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label for="citizen_attachments" class="block text-sm font-medium text-gray-700">Photos (up to 5)</label>
                        <input type="file" id="citizen_attachments" name="attachments[]" accept="image/*" multiple required
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700" />
                    </div>
                    <div>
                        <label for="citizen_note" class="block text-sm font-medium text-gray-700">Short note (optional)</label>
                        <textarea id="citizen_note" name="note" rows="2" maxlength="1000" placeholder="e.g. Missing ID copy attached"
                                  class="mt-1 block w-full rounded-lg border border-gray-300 text-sm shadow-sm">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 sm:w-auto">
                        Send to department
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- ── Scan Timeline ─────────────────────────────────────────────────── --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Activity Log</h2>
            </div>
            <div class="divide-y divide-gray-50 px-6" id="timeline">
                @forelse($timeline as $log)
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full
                                {{ $log['action'] === 'in' ? 'bg-emerald-500' : 'bg-gray-400' }}">
                            </span>
                            <span class="text-sm text-gray-700">{{ $log['event'] }}</span>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-gray-400">{{ $log['timestamp'] }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-400">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>

        {{-- ── Scan Another QR ──────────────────────────────────────────────── --}}
        <div class="text-center">
            <a href="{{ route('citizen.track') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-5 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 0 1 2-2h2m10 0h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                    <rect x="9" y="9" width="6" height="6" rx="1"/>
                </svg>
                Scan or Search Another Document
            </a>
        </div>
    </div>

    {{-- ── Live status: real-time via Laravel Echo / Reverb, 30 s poll fallback ─ --}}
    <script>
        const trackingNumber = @json($document->tracking_number);
        const statusEndpoint = '/track/' + encodeURIComponent(trackingNumber) + '/status';
        let currentStatus    = @json($document->status);
        let liveConnected    = false;

        const statusDotClasses = {
            completed: { bg: 'bg-green-100',  text: 'text-green-800',  dot: 'bg-green-500',  label: 'Completed'  },
            pending:   { bg: 'bg-blue-100',   text: 'text-blue-800',   dot: 'bg-blue-500',   label: 'Pending'    },
            returned:  { bg: 'bg-rose-100',   text: 'text-rose-800',   dot: 'bg-rose-500',   label: 'Returned'   },
            in_transit:{ bg: 'bg-amber-100',  text: 'text-amber-800',  dot: 'bg-amber-500',  label: 'In Transit' },
        };

        function getStatusClasses(status) {
            return statusDotClasses[status] ?? statusDotClasses['in_transit'];
        }

        // Update the status badge + current location in place (no reload).
        function applyStatus(status, department) {
            const badge = document.getElementById('statusBadge');
            const label = document.getElementById('statusLabel');
            const dept  = document.getElementById('currentDept');
            const classes = getStatusClasses(status);

            badge.className = `inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold ${classes.bg} ${classes.text}`;
            badge.querySelector('span:first-child').className = `h-2 w-2 rounded-full ${classes.dot}`;
            label.textContent = classes.label;

            if (department) {
                dept.textContent = department;
            } else if (department === null) {
                dept.textContent = 'Not yet assigned';
            }
            currentStatus = status;
        }

        // Prepend a new activity row to the timeline (matching the Blade markup).
        function prependTimelineEntry(entry) {
            const timeline = document.getElementById('timeline');
            if (!timeline) return;

            // Drop the "No activity recorded yet." placeholder if present.
            const placeholder = timeline.querySelector('p');
            if (placeholder) placeholder.remove();

            const row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-4 py-3 transition-colors';
            const dotColor = entry.action === 'in' ? 'bg-emerald-500' : 'bg-gray-400';
            row.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${dotColor}"></span>
                    <span class="eventText text-sm text-gray-700"></span>
                </div>
                <span class="tsText shrink-0 text-xs font-semibold text-gray-400"></span>`;
            // Use textContent to avoid injecting unescaped names into the DOM.
            row.querySelector('.eventText').textContent = entry.event ?? '';
            row.querySelector('.tsText').textContent = entry.timestamp ?? '';
            timeline.prepend(row);

            // Brief highlight so the citizen notices the new entry.
            row.classList.add('bg-emerald-50');
            setTimeout(() => row.classList.remove('bg-emerald-50'), 2000);
        }

        function flashBanner(message) {
            const banner = document.getElementById('updateBanner');
            if (!banner) return;
            banner.textContent = message;
            banner.classList.remove('hidden');
            clearTimeout(window.__bannerTimer);
            window.__bannerTimer = setTimeout(() => banner.classList.add('hidden'), 5000);
        }

        function setLiveIndicator() {
            const checkedText = document.getElementById('lastCheckedText');
            if (checkedText) {
                checkedText.innerHTML =
                    '<span class="inline-flex items-center gap-1.5">' +
                    '<span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>Live</span>';
            }
        }

        // ── Real-time updates via Laravel Echo / Reverb ────────────────────────
        if (window.Echo) {
            try {
                window.Echo.channel('documents.' + trackingNumber)
                    .listen('.moved', (e) => {
                        applyStatus(e.status, e.current_department);
                        prependTimelineEntry(e);
                        flashBanner('Status updated!');
                    });

                const pusher = window.Echo.connector?.pusher;
                if (pusher) {
                    pusher.connection.bind('connected', () => { liveConnected = true; setLiveIndicator(); });
                    pusher.connection.bind('unavailable', () => { liveConnected = false; });
                    pusher.connection.bind('disconnected', () => { liveConnected = false; });
                } else {
                    liveConnected = true;
                    setLiveIndicator();
                }
            } catch (_) {
                liveConnected = false; // Echo unavailable — poll fallback stays active.
            }
        }

        // ── Fallback: poll every 30 s only while the live socket is not connected ─
        async function pollStatus() {
            if (liveConnected) return;

            const spinner = document.getElementById('pollSpinner');
            const checkedText = document.getElementById('lastCheckedText');
            spinner.classList.remove('hidden');

            try {
                const res = await fetch(statusEndpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                // Status changed but we have no live timeline payload — reload to refresh it.
                if (data.status !== currentStatus) {
                    flashBanner('Status updated! Refreshing…');
                    setTimeout(() => location.reload(), 1800);
                    return;
                }

                applyStatus(data.status, data.current_department);

                const now = new Date();
                checkedText.textContent = 'Last checked ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (_) {
                // silent fail — network may be temporarily down
            } finally {
                spinner.classList.add('hidden');
            }
        }

        setInterval(pollStatus, 30000);
    </script>
</x-citizen-layout>
