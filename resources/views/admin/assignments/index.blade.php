<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-emerald-950">Document Assignments</h1>
            <p class="mt-1 text-sm text-gray-500">Assign the staff member responsible for advancing each document through its status stages.</p>
        </div>

        {{-- All / Unclaimed tabs --}}
        <div class="flex gap-1 rounded-xl bg-gray-100 p-1 w-fit">
            <a href="{{ route('admin.assignments.index') }}"
               class="rounded-lg px-4 py-1.5 text-sm font-semibold {{ $filter !== 'unclaimed' ? 'bg-white text-emerald-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">All</a>
            <a href="{{ route('admin.assignments.index', ['filter' => 'unclaimed']) }}"
               class="rounded-lg px-4 py-1.5 text-sm font-semibold {{ $filter === 'unclaimed' ? 'bg-white text-emerald-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">Unclaimed</a>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3">
            @if($filter === 'unclaimed')
                <input type="hidden" name="filter" value="unclaimed">
            @endif
            <input type="text" name="q" value="{{ request('q') }}" autocomplete="off"
                   placeholder="Search tracking #, citizen, or type…"
                   class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 min-w-[220px]">
            <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <option value="">All statuses</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">Filter</button>
            @if(request()->hasAny(['q', 'status']))
                <a href="{{ route('admin.assignments.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
            @endif
        </form>

        {{-- Table --}}
        <div class="panel">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Tracking #</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Type</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Citizen</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500 min-w-[280px]">Assigned to</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($documents as $document)
                            <tr class="even:bg-gray-50/50">
                                <td class="px-4 py-3 text-sm font-mono font-semibold text-emerald-800">
                                    <a href="{{ route('track.show', $document->tracking_number) }}" class="hover:underline">{{ $document->tracking_number }}</a>
                                    <x-document-origin :source="$document->source" :by="$document->creator?->name" :at="$document->created_at" class="origin-row" />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $document->document_type }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $document->citizen_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm"><x-status-badge :status="$document->status" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('admin.assignments.assign', $document) }}" class="flex flex-1 items-center gap-2">
                                            @csrf @method('PATCH')
                                            <select name="assigned_to"
                                                    class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                                <option value="">— Unassigned —</option>
                                                @foreach($staff as $member)
                                                    <option value="{{ $member->id }}" @selected((int) $document->assigned_to === (int) $member->id)>{{ $member->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Save</button>
                                        </form>
                                        @if($document->assigned_to === null)
                                            @can('accept documents')
                                                <form method="POST" action="{{ route('documents.accept', $document) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="rounded-lg border border-emerald-700 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">Accept</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state icon="document" title="No documents to assign">
                                        When documents come in, they'll appear here waiting to be assigned to a staff member. Nothing is currently in the queue.
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $documents->links() }}
    </div>
</x-app-layout>
