<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Document History') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="p-6">
                    <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Document Type</label>
                            <select name="document_type" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type }}" {{ request('document_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                                <option value="">All</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">From</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">To</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="inline-flex rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">Filter</button>
                            <a href="{{ route('history') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        </div>
                    </form>

                    <div class="mb-4 mt-4 flex justify-end">
                        <a href="{{ route('history.export', request()->query()) }}" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Export CSV</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Tracking #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Citizen</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Current Dept</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Created At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($documents as $doc)
                                    <tr>
                                        <td class="px-6 py-4 text-sm">{{ $doc->tracking_number }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $doc->document_type }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $doc->citizen_name ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                                {{ $doc->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                                {{ $doc->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}
                                                {{ in_array($doc->status, ['pending','in_transit']) ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                                {{ str_replace('_', ' ', ucfirst($doc->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm">{{ $doc->currentDepartment->name ?? 'None' }}</td>
                                        <td class="px-6 py-4 text-sm">{{ $doc->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="{{ route('track.show', $doc->tracking_number) }}" class="text-blue-600 hover:underline">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-4">No documents found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $documents->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>