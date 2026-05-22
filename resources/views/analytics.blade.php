<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Analytics</h1>
                @if(!$isOrgWide && $dept)
                    <p class="mt-1 text-sm text-emerald-700">
                        Showing data for <span class="font-semibold">{{ $dept->name }}</span> only
                    </p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Organization-wide document and scan metrics</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
        {{-- Summary cards --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $isOrgWide ? 'In transit' : 'At your department' }}</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $summary['at_department'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Completed</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $summary['completed'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submitted this month</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $summary['submitted_month'] }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Overdue now</p>
                <p class="mt-2 text-3xl font-bold text-amber-700">{{ $summary['overdue'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="rounded-xl border border-[#e0e0e0] bg-white p-6 lg:col-span-2">
                <h2 class="text-xl font-bold text-[#1a5c1a]">Document activity over time</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $isOrgWide ? 'All offices — new submissions vs completions per day.' : 'Documents linked to your department.' }}
                    Chart loads automatically for the last 30 days.
                </p>

                <div class="mb-6 mt-5 grid grid-cols-1 gap-4 md:grid-cols-5 md:items-end">
                    <div>
                        <label for="docType" class="block text-sm font-medium text-gray-700">Category</label>
                        <select id="docType" class="mt-1 block h-10 w-full rounded-lg border border-gray-300 text-sm shadow-sm">
                            <option value="">All</option>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" class="mt-1 block h-10 w-full rounded-lg border border-gray-300 text-sm shadow-sm">
                            <option value="">All</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}">{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="fromDate" class="block text-sm font-medium text-gray-700">From</label>
                        <input type="date" id="fromDate" class="mt-1 block h-10 w-full rounded-lg border border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label for="toDate" class="block text-sm font-medium text-gray-700">To</label>
                        <input type="date" id="toDate" class="mt-1 block h-10 w-full rounded-lg border border-gray-300 text-sm shadow-sm" />
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-transparent select-none" aria-hidden="true">Actions</span>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <button id="applyBtn" type="button" class="inline-flex h-10 items-center justify-center rounded-lg bg-gray-800 px-4 text-sm font-semibold text-white hover:bg-gray-900">Apply</button>
                            <button id="downloadBtn" type="button" class="inline-flex h-10 items-center justify-center rounded-lg bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700">Download CSV</button>
                        </div>
                    </div>
                </div>

                <div id="chartError" class="hidden mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
                <div id="chartEmpty" class="hidden mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                    No data for this date range. Try widening the dates or clearing filters.
                </div>

                <div class="relative h-72 w-full">
                    <canvas id="submissionChart"></canvas>
                </div>

                <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#4caf50]"></span> New submissions</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#1a5c1a]"></span> Completed</span>
                </div>
            </div>

            <div class="space-y-6">
            <div class="rounded-xl border border-[#e0e0e0] bg-white p-6">
                <h3 class="text-lg font-bold text-[#1a5c1a]">By document type</h3>
                <table class="mt-4 min-w-full">
                    <tbody>
                        @forelse($byType as $row)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="py-2 text-sm text-gray-800">{{ $row->document_type }}</td>
                                <td class="py-2 text-right text-sm font-bold text-emerald-800">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-4 text-center text-sm text-gray-500">No documents yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border border-[#e0e0e0] bg-white p-6">
                @if($isOrgWide)
                    <div class="flex items-start justify-between">
                        <h3 class="text-2xl font-extrabold leading-tight text-[#1a5c1a]">Top Submitting Departments</h3>
                        <svg class="h-10 w-10 shrink-0 text-[#1a5c1a]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <rect x="3" y="10" width="4" height="11" rx="1"></rect>
                            <rect x="10" y="6" width="4" height="15" rx="1"></rect>
                            <rect x="17" y="3" width="4" height="18" rx="1"></rect>
                        </svg>
                    </div>
                    <table class="mt-5 min-w-full">
                        <thead class="bg-[#fafafa]">
                            <tr>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#666666]">Department</th>
                                <th class="px-4 py-3 text-right text-[13px] font-semibold text-[#666666]">Scans</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topDepartments as $deptRow)
                                <tr class="border-b border-[#e0e0e0] last:border-b-0">
                                    <td class="px-4 py-3 text-[14px] text-[#1a1a1a]">{{ $deptRow->name }}</td>
                                    <td class="px-4 py-3 text-right text-[14px] font-bold text-[#1a5c1a]">{{ $deptRow->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No scan data yet</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <h3 class="text-2xl font-extrabold leading-tight text-[#1a5c1a]">Status Breakdown</h3>
                    <p class="mt-1 text-sm text-gray-500">Documents linked to {{ $dept?->name ?? 'your department' }}</p>
                    <table class="mt-5 min-w-full">
                        <thead class="bg-[#fafafa]">
                            <tr>
                                <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#666666]">Status</th>
                                <th class="px-4 py-3 text-right text-[13px] font-semibold text-[#666666]">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($statusBreakdown as $row)
                                <tr class="border-b border-[#e0e0e0] last:border-b-0">
                                    <td class="px-4 py-3 text-[14px] capitalize text-[#1a1a1a]">{{ str_replace('_', ' ', $row->status) }}</td>
                                    <td class="px-4 py-3 text-right text-[14px] font-bold text-[#1a5c1a]">{{ $row->total }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No documents yet for your department</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let chart;

        function defaultDates() {
            const to = new Date();
            const from = new Date();
            from.setDate(from.getDate() - 30);
            document.getElementById('toDate').value = to.toISOString().slice(0, 10);
            document.getElementById('fromDate').value = from.toISOString().slice(0, 10);
        }

        function chartMax(values) {
            const peak = Math.max(...values, 0);
            return peak <= 5 ? 5 : Math.ceil(peak * 1.25);
        }

        async function loadChart() {
            const errorEl = document.getElementById('chartError');
            const emptyEl = document.getElementById('chartEmpty');
            errorEl.classList.add('hidden');
            emptyEl.classList.add('hidden');

            const params = new URLSearchParams({
                document_type: document.getElementById('docType').value,
                status: document.getElementById('status').value,
                from: document.getElementById('fromDate').value,
                to: document.getElementById('toDate').value,
            });

            try {
                const response = await fetch(`{{ route('analytics.data') }}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Could not load chart data.');
                }

                const data = await response.json();
                window.chartData = data;

                const hasPoints = data.submitted.some(v => v > 0) || data.completed.some(v => v > 0);
                emptyEl.classList.toggle('hidden', hasPoints);

                if (chart) chart.destroy();
                const ctx = document.getElementById('submissionChart').getContext('2d');
                const yMax = chartMax([...data.submitted, ...data.completed]);

                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels.map(d => {
                            const dt = new Date(d + 'T12:00:00');
                            return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'New submissions',
                                data: data.submitted,
                                borderColor: '#4caf50',
                                backgroundColor: 'rgba(76, 175, 80, 0.15)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                            },
                            {
                                label: 'Completed',
                                data: data.completed,
                                borderColor: '#1a5c1a',
                                backgroundColor: 'transparent',
                                tension: 0.35,
                                pointRadius: 3,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom' },
                        },
                        scales: {
                            y: {
                                min: 0,
                                max: yMax,
                                ticks: { stepSize: Math.max(1, Math.ceil(yMax / 5)) },
                                grid: { color: '#eeeeee' },
                            },
                            x: { grid: { display: false } },
                        },
                    },
                });
            } catch (err) {
                errorEl.textContent = err.message || 'Failed to load analytics chart.';
                errorEl.classList.remove('hidden');
            }
        }

        document.getElementById('applyBtn').addEventListener('click', loadChart);
        document.getElementById('downloadBtn').addEventListener('click', () => {
            if (!window.chartData) return;
            let csv = 'Date,Submitted,Completed\n';
            for (let i = 0; i < window.chartData.labels.length; i++) {
                csv += `${window.chartData.labels[i]},${window.chartData.submitted[i]},${window.chartData.completed[i]}\n`;
            }
            const blob = new Blob([csv], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'analytics.csv';
            link.click();
        });

        defaultDates();
        loadChart();
    </script>
</x-app-layout>
