<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Document Movements</h1>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                {{ $documents->total() }} in transit
            </span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3">
            <select name="department" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department') == $dept->id)>
                        {{ $dept->name }}
                    </option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm cursor-pointer">
                <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="font-medium text-gray-700">Overdue only</span>
            </label>
            <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">
                Filter
            </button>
            @if(request()->hasAny(['department', 'overdue']))
                <a href="{{ route('movements.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>

        @if($documents->isEmpty())
            <div class="rounded-2xl border border-gray-200/90 bg-white px-6 py-16 text-center shadow-sm">
                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M4.5 19.5l15-15M19.5 4.5l-15 15"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">No documents currently in transit.</p>
            </div>
        @else
            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Tracking #</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Citizen</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Current Department</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Time at Dept.</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">SLA</th>
                                <th class="px-4 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($documents as $document)
                                @php
                                    $lastInScan = $document->scans
                                        ->where('department_id', $document->current_department_id)
                                        ->first();
                                    $isOverdue  = $document->isOverdue();
                                @endphp
                                <tr class="transition hover:bg-gray-50/60">
                                    <td class="px-4 py-3.5">
                                        <span class="font-mono text-sm font-semibold text-emerald-700">
                                            {{ $document->tracking_number }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-sm text-gray-700">
                                        {{ $document->document_type }}
                                    </td>
                                    <td class="px-4 py-3.5 text-sm text-gray-700">
                                        {{ $document->citizen_name }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                            {{ $document->currentDepartment->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-sm text-gray-600">
                                        {{ $lastInScan ? $lastInScan->scanned_at->diffForHumans(['parts' => 2, 'short' => true]) : '—' }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($isOverdue)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                                Overdue
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                On Time
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <a href="{{ route('track.show', $document->tracking_number) }}"
                                           class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($documents->hasPages())
                    <div class="border-t border-gray-100 px-4 py-3">
                        {{ $documents->links() }}
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-app-layout>
