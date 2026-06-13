<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <form method="GET" id="historyFilterForm"
              x-data="{ filtersOpen: {{ (request()->filled('document_type') || request()->filled('status') || request()->filled('from') || request()->filled('to')) ? 'true' : 'false' }} }"
              class="space-y-3">
            {{-- Search row: input + filter toggle + export, all aligned --}}
            <div class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" id="historySearch" value="{{ request('search') }}" autocomplete="off"
                       placeholder="Search tracking #, citizen, or type…"
                       class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 min-w-[200px]">
                <button type="button" @click="filtersOpen = !filtersOpen"
                        :class="filtersOpen ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-gray-200 bg-white text-gray-600'"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:bg-gray-50"
                        :aria-expanded="filtersOpen ? 'true' : 'false'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filters
                </button>
                <a href="{{ route('history.export', request()->query()) }}" id="historyExportLink"
                   class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Export CSV
                </a>
            </div>

            {{-- Toggleable filter panel --}}
            <div x-show="filtersOpen" x-cloak class="grid grid-cols-1 gap-3 rounded-2xl border border-gray-200/90 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <select name="document_type" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                    <option value="">All Categories</option>
                    @foreach($documentTypes as $type)
                        <option value="{{ $type }}" @selected(request('document_type')===$type)>{{ $type }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ request('from') }}" title="From date"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <input type="date" name="to" value="{{ request('to') }}" title="To date"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">Apply</button>
                    <a href="{{ route('history') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
                </div>
            </div>
        </form>

        <div id="historyResults" class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50 transition-opacity duration-150">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-100/90">
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">#</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">File Name</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tracking ID</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Date</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Category</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Sticker</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($documents as $doc)
                            @php
                                $index = ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration;
                            @endphp
                            <x-document-row
                                class="even:bg-gray-50/50"
                                :index="$index"
                                :date="$doc->created_at->format('M j, Y')"
                                :tracking="$doc->tracking_number"
                                :fileName="$doc->citizen_name ?: 'File '.substr($doc->tracking_number, -5)"
                                :category="$doc->document_type"
                                :status="$doc->status === 'completed' ? 'completed' : $doc->status"
                                :href="route('track.show', $doc->tracking_number)"
                                :sticker-href="route('documents.sticker', $doc)"
                            />
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3">{{ $documents->links() }}</div>
        </div>
    </div>

    <script>
        // Live search: debounce keystrokes, fetch the same page with the current
        // form values, and swap in the fresh table + pagination.
        (function () {
            const form = document.getElementById('historyFilterForm');
            const input = document.getElementById('historySearch');
            const results = document.getElementById('historyResults');
            if (!form || !input || !results) return;

            let timer = null;
            let controller = null;

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(refreshResults, 300);
            });

            // Dropdowns and dates refresh immediately on change
            form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                el.addEventListener('change', refreshResults);
            });

            function refreshResults() {
                const params = new URLSearchParams(new FormData(form));
                const url = '{{ route('history') }}' + (params.toString() ? '?' + params.toString() : '');

                controller?.abort();
                controller = new AbortController();
                results.classList.add('opacity-50');

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                    .then(response => response.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const fresh = doc.getElementById('historyResults');
                        if (fresh) results.innerHTML = fresh.innerHTML;
                        window.history.replaceState({}, '', url);
                        const exportLink = document.getElementById('historyExportLink');
                        if (exportLink) {
                            exportLink.href = '{{ route('history.export') }}' + (params.toString() ? '?' + params.toString() : '');
                        }
                        results.classList.remove('opacity-50');
                    })
                    .catch(error => {
                        if (error.name !== 'AbortError') {
                            results.classList.remove('opacity-50');
                            console.error(error);
                        }
                    });
            }
        })();
    </script>
</x-app-layout>
