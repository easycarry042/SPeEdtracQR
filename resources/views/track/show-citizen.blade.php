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

        {{-- ── Routing Progress ─────────────────────────────────────────────── --}}
        @if($routingSteps->isNotEmpty())
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-5 shadow-sm">
            <h2 class="mb-4 text-base font-bold text-gray-800">Department Progress</h2>
            <div class="flex flex-wrap items-center gap-2">
                @foreach($routingSteps as $idx => $step)
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col items-center gap-1">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full
                                {{ $idx === count($routingSteps) - 1 && $document->status !== 'completed'
                                    ? 'bg-amber-400 ring-2 ring-amber-300 ring-offset-1'
                                    : 'bg-emerald-500 text-white' }} text-xs font-bold">
                                @if($idx === count($routingSteps) - 1 && $document->status !== 'completed')
                                    ●
                                @else
                                    ✓
                                @endif
                            </span>
                            <span class="max-w-[80px] text-center text-[10px] text-gray-500 leading-tight">{{ $step }}</span>
                        </div>
                        @if(!$loop->last)
                            <span class="mb-4 h-0.5 w-8 bg-emerald-400"></span>
                        @endif
                    </div>
                @endforeach
                @if($document->status === 'completed')
                    <div class="flex items-center gap-2">
                        <span class="mb-4 h-0.5 w-8 bg-emerald-400"></span>
                        <div class="flex flex-col items-center gap-1">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-bold">✓</span>
                            <span class="text-[10px] text-gray-500">Done</span>
                        </div>
                    </div>
                @endif
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

    {{-- ── Live status polling ──────────────────────────────────────────────── --}}
    <script>
        const trackingNumber = @json($document->tracking_number);
        const statusEndpoint = '/track/' + encodeURIComponent(trackingNumber) + '/status';
        let currentStatus    = @json($document->status);

        const statusDotClasses = {
            completed: { bg: 'bg-green-100',  text: 'text-green-800',  dot: 'bg-green-500',  label: 'Completed'  },
            pending:   { bg: 'bg-blue-100',   text: 'text-blue-800',   dot: 'bg-blue-500',   label: 'Pending'    },
            returned:  { bg: 'bg-rose-100',   text: 'text-rose-800',   dot: 'bg-rose-500',   label: 'Returned'   },
            in_transit:{ bg: 'bg-amber-100',  text: 'text-amber-800',  dot: 'bg-amber-500',  label: 'In Transit' },
        };

        function getStatusClasses(status) {
            return statusDotClasses[status] ?? statusDotClasses['in_transit'];
        }

        async function pollStatus() {
            const spinner = document.getElementById('pollSpinner');
            const checkedText = document.getElementById('lastCheckedText');
            spinner.classList.remove('hidden');

            try {
                const res = await fetch(statusEndpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                const badge = document.getElementById('statusBadge');
                const label = document.getElementById('statusLabel');
                const dept  = document.getElementById('currentDept');
                const classes = getStatusClasses(data.status);

                // Detect change
                if (data.status !== currentStatus) {
                    document.getElementById('updateBanner').classList.remove('hidden');
                    setTimeout(() => location.reload(), 1800);
                    return;
                }

                // Update badge colours
                badge.className = `inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold ${classes.bg} ${classes.text}`;
                badge.querySelector('span:first-child').className = `h-2 w-2 rounded-full ${classes.dot}`;
                label.textContent = classes.label;
                dept.textContent  = data.current_department ?? 'Not yet assigned';
                currentStatus = data.status;

                const now = new Date();
                checkedText.textContent = 'Last checked ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (_) {
                // silent fail — network may be temporarily down
            } finally {
                spinner.classList.add('hidden');
            }
        }

        // Poll every 30 seconds
        setInterval(pollStatus, 30000);
    </script>
</x-citizen-layout>
