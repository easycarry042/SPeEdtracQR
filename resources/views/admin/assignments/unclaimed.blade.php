<x-app-layout>
    <div class="page-shell">
        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="GET" class="flex flex-col gap-2 sm:flex-row">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search tracking, citizen, type"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Search</button>
        </form>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Tracking</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Citizen</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($documents as $document)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono">{{ $document->tracking_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $document->document_type }}</td>
                            <td class="px-4 py-3 text-sm">{{ $document->citizen_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @can('accept documents')
                                    <form method="POST" action="{{ route('documents.accept', $document) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white">Accept</button>
                                    </form>
                                @else
                                    <span class="text-gray-500">View only</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No unclaimed documents.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $documents->links() }}
    </div>
</x-app-layout>
