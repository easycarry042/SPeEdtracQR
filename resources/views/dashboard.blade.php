<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Dashboard</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <x-stat-card label="Total Request" :value="$totalRequests" icon="list" />
            <x-stat-card label="Pending Request" :value="$pendingRequest" icon="hourglass" />
            <x-stat-card label="Completed" :value="$completed" icon="check" />
        </div>

        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Recent Activity</h2>
                    <a href="{{ route('history') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-800 transition hover:text-emerald-950 hover:underline">
                        Show all
                        <span aria-hidden="true">›</span>
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <label class="relative flex-1 sm:flex-initial">
                        <span class="sr-only">Search activity</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="search" id="activitySearch" placeholder="Search" class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm shadow-sm transition placeholder:text-gray-400 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 sm:w-64">
                    </label>
                    <button type="button" class="rounded-xl border border-gray-200 bg-white p-2 text-gray-500 shadow-sm transition hover:bg-gray-50 hover:text-emerald-900" aria-label="Filter">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="activityTable">
                        <thead>
                            <tr class="bg-gray-100/90">
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">#</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">File Name</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tracking ID</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Date</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Category</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($recentActivity as $activity)
                                <x-document-row
                                    class="activity-row even:bg-gray-50/50"
                                    :index="$loop->iteration"
                                    :date="$activity->created_at->format('M j, Y')"
                                    :tracking="$activity->tracking_number"
                                    :fileName="$activity->citizen_name ?: 'File '.substr($activity->tracking_number, -5)"
                                    :category="$activity->document_type"
                                    :status="$activity->status"
                                />
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No recent activity.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
    <script>
        const searchInput = document.getElementById('activitySearch');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.activity-row').forEach(row => {
                    row.classList.toggle('hidden', !row.innerText.toLowerCase().includes(q));
                });
            });
        }
    </script>
</x-app-layout>
