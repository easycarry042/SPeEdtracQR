<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Administration</p>
                <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Admin Dashboard</h1>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Admin
            </span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8">

        {{-- ── Stat Cards ─────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Total Documents --}}
            <div class="group relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Documents</p>
                        <p class="mt-2 text-4xl font-bold text-emerald-900">{{ $totalDocuments }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm0 2v14h9V8h-3V5H7zm2 6h6v2H9v-2zm0 4h6v2H9v-2z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-emerald-100">
                    <div class="h-1 rounded-full bg-emerald-500" style="width: 100%"></div>
                </div>
            </div>

            {{-- Pending Documents --}}
            <div class="group relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pending / In Transit</p>
                        <p class="mt-2 text-4xl font-bold text-amber-600">{{ $pendingDocuments }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 14H11V11h2v5zm0-7H11V7h2v2z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-amber-100">
                    @php $pct = $totalDocuments > 0 ? round(($pendingDocuments / $totalDocuments) * 100) : 0; @endphp
                    <div class="h-1 rounded-full bg-amber-400" style="width: {{ $pct }}%"></div>
                </div>
            </div>

            {{-- Total Staff --}}
            <div class="group relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Staff</p>
                        <p class="mt-2 text-4xl font-bold text-emerald-900">{{ $totalStaff }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-sky-100">
                    <div class="h-1 rounded-full bg-sky-400" style="width: 100%"></div>
                </div>
            </div>

            {{-- Total Departments --}}
            <div class="group relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Departments</p>
                        <p class="mt-2 text-4xl font-bold text-emerald-900">{{ $totalDepartments }}</p>
                    </div>
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 3L2 9v2h20V9L12 3zM4 13v5h3v-5H4zm5 0v5h3v-5H9zm5 0v5h3v-5h-3zm5 0v5h-2v-5h2zm-15 7h16v2H4v-2z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-4 h-1 w-full rounded-full bg-purple-100">
                    <div class="h-1 rounded-full bg-purple-400" style="width: 100%"></div>
                </div>
            </div>

        </div>

        @include('partials.predictive-insights')

        {{-- ── Recent Scan Activity ────────────────────────────────────────────── --}}
        @if($recentScans->isNotEmpty())
        <section class="space-y-4">
            <h2 class="text-xl font-bold text-emerald-950">Recent Scans</h2>
            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Document</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Scanned By</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($recentScans as $scan)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 text-sm font-mono font-medium text-emerald-800">
                                    {{ $scan->document?->tracking_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $scan->department?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                        {{ $scan->action === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700' }}">
                                        {{ strtoupper($scan->action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $scan->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $scan->scanned_at?->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif

        {{-- ── Recent Documents ────────────────────────────────────────────────── --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-emerald-950">Recent Documents</h2>
                <a href="{{ route('history') }}" class="text-sm font-semibold text-emerald-700 hover:underline">View all →</a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="adminActivityTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Tracking ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Citizen</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($recentActivity as $i => $doc)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-3 text-sm font-mono font-semibold text-emerald-800">
                                    {{ $doc->tracking_number }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $doc->citizen_name ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->document_type }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $doc->currentDepartment?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :status="$doc->status" />
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    {{ $doc->created_at->format('M j, Y') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No documents yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
</x-app-layout>
