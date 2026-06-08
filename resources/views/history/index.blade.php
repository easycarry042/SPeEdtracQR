<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">History</h1>
                @php $dept = auth()->user()?->department; @endphp
                @if($dept && !auth()->user()->can('manage system'))
                    <p class="mt-1 text-sm text-emerald-700">Documents for <span class="font-semibold">{{ $dept->name }}</span></p>
                @endif
            </div>
            <a href="{{ route('history.export', request()->query()) }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                Export CSV
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <form method="GET" class="grid grid-cols-1 gap-3 rounded-2xl border border-gray-200/90 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-1">
                <input name="search" value="{{ request('search') }}" placeholder="Search" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>
            <select name="document_type" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <option value="">All category</option>
                @foreach($documentTypes as $type)
                    <option value="{{ $type }}" @selected(request('document_type')===$type)>{{ $type }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <option value="">All status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            <button type="submit" class="rounded-xl bg-emerald-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-900">Apply</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50">
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
</x-app-layout>
