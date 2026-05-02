<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Analytics</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="rounded-xl border border-[#e0e0e0] bg-white p-6 lg:col-span-2">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-5 md:items-end">
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
                        {{-- Placeholder label so the button row lines up with selects/date inputs --}}
                        <span class="block text-sm font-medium text-transparent select-none" aria-hidden="true">Actions</span>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <button id="applyBtn" type="button" class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-gray-800 px-4 text-sm font-semibold text-white hover:bg-gray-900">Apply</button>
                            <button id="downloadBtn" type="button" class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-green-600 px-4 text-sm font-semibold text-white hover:bg-green-700">Download CSV</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <canvas id="submissionChart" height="120"></canvas>
                </div>
                <p class="mt-6 text-center text-3xl font-extrabold text-[#1a5c1a]">Document Submission Over Time</p>
            </div>

            <div class="rounded-xl border border-[#e0e0e0] bg-white p-6">
                <div class="flex items-start justify-between">
                    <h3 class="text-4xl font-extrabold leading-tight text-[#1a5c1a]">Top Submitting Departments</h3>
                    <svg class="h-10 w-10 text-[#1a5c1a]" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="10" width="4" height="11" rx="1"></rect>
                        <rect x="10" y="6" width="4" height="15" rx="1"></rect>
                        <rect x="17" y="3" width="4" height="18" rx="1"></rect>
                    </svg>
                </div>
                <table class="mt-5 min-w-full">
                    <thead class="bg-[#fafafa]">
                        <tr>
                            <th class="px-4 py-3 text-left text-[13px] font-semibold text-[#666666]">Department</th>
                            <th class="px-4 py-3 text-right text-[13px] font-semibold text-[#666666]">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topDepartments as $dept)
                            <tr class="border-b border-[#e0e0e0] last:border-b-0">
                                <td class="px-4 py-3 text-[14px] text-[#1a1a1a]">{{ $dept->name }}</td>
                                <td class="px-4 py-3 text-right text-[14px] font-bold text-[#1a5c1a]">{{ $dept->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No data yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let chart;
        async function loadChart() {
            const params = new URLSearchParams({
                document_type: document.getElementById('docType').value,
                status: document.getElementById('status').value,
                from: document.getElementById('fromDate').value,
                to: document.getElementById('toDate').value
            });
            const response = await fetch(`/analytics/data?${params}`);
            const data = await response.json();

            if (chart) chart.destroy();
            const ctx = document.getElementById('submissionChart').getContext('2d');
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        { label: 'Total of submission', data: data.total, borderColor: '#4caf50', backgroundColor: '#4caf50', pointRadius: 5, pointHoverRadius: 6, tension: 0.35, fill: false }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, labels: { boxWidth: 10, color: '#1a1a1a' } }
                    },
                    scales: {
                        y: { min: 0, max: 100, ticks: { stepSize: 25 }, grid: { color: '#eeeeee' } },
                        x: { grid: { display: false } }
                    },
                }
            });

            // Store data for CSV download
            window.chartData = data;
        }

        document.getElementById('applyBtn').addEventListener('click', loadChart);

        document.getElementById('downloadBtn').addEventListener('click', () => {
            if (!window.chartData) return;
            let csv = "Date,Accepted,Pending/Rejected,Total\n";
            for (let i = 0; i < window.chartData.labels.length; i++) {
                csv += `${window.chartData.labels[i]},${window.chartData.accepted[i]},${window.chartData.pending_rejected[i]},${window.chartData.total[i]}\n`;
            }
            const blob = new Blob([csv], { type: 'text/csv' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'submissions.csv';
            link.click();
        });

        loadChart();
    </script>
</x-app-layout>