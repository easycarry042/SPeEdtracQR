<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Document - SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <div class="mx-auto min-h-screen max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Track Document</h1>
            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="text-sm font-semibold text-green-700 hover:text-green-800">
                {{ auth()->check() ? 'Back to dashboard' : 'Back to home' }}
            </a>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="p-6">
                <form action="{{ route('track.search') }}" method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="md:col-span-3">
                        <label for="tracking_number" class="mb-1 block text-sm font-medium text-gray-700">Tracking Number</label>
                        <input
                            id="tracking_number"
                            type="text"
                            name="tracking_number"
                            value="{{ $trackingNumber ?? request('tracking_number') }}"
                            placeholder="e.g. SPD-20260427-XXXXXX"
                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm"
                        />
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">
                            Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if (!empty($notFound))
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                No document found for tracking number <strong>{{ $trackingNumber }}</strong>.
            </div>
        @endif

        @if (!empty($document))
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 class="text-lg font-semibold text-gray-900">Document Details</h2>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p class="text-sm text-gray-700"><span class="font-semibold text-gray-900">Tracking Number:</span> {{ $document->tracking_number }}</p>
                        <p class="text-sm text-gray-700"><span class="font-semibold text-gray-900">Type:</span> {{ $document->document_type }}</p>
                        <p class="text-sm text-gray-700"><span class="font-semibold text-gray-900">Citizen Name:</span> {{ $document->citizen_name ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold text-gray-900">Status:</span>
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                {{ $document->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $document->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}
                                {{ in_array($document->status, ['pending','in_transit']) ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                {{ str_replace('_', ' ', ucfirst($document->status)) }}
                            </span>
                        </p>
                        <p class="text-sm text-gray-700 sm:col-span-2"><span class="font-semibold text-gray-900">Current Department:</span> {{ $document->currentDepartment->name ?? 'Not assigned yet' }}</p>
                    </div>

                    <h3 class="mt-8 text-base font-semibold text-gray-900">Scan History Timeline</h3>
                    <div class="mt-4 border-l-2 border-gray-200 pl-4">
                        @forelse($document->scans as $scan)
                            <div class="relative mb-5">
                                <span class="absolute -left-[1.35rem] mt-1 h-3 w-3 rounded-full {{ $scan->action === 'in' ? 'bg-green-500' : 'bg-blue-500' }}"></span>
                                <p class="text-sm font-semibold text-gray-900">
                                    {{ strtoupper($scan->action) }} - {{ $scan->department->name ?? 'Unknown Department' }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ optional($scan->scanned_at)->format('M d, Y h:i A') ?? 'N/A' }}
                                    by {{ $scan->user->name ?? 'System' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No scan activity yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">File Metadata</h2>
                    <div class="mt-4 space-y-3 text-sm text-gray-700">
                        <p><span class="font-semibold text-gray-900">Path:</span> {{ $fileMetadata->path ?? 'N/A' }}</p>
                        <p><span class="font-semibold text-gray-900">Type:</span> {{ $fileMetadata->type ?? 'N/A' }}</p>
                        <p><span class="font-semibold text-gray-900">Size:</span> {{ $fileMetadata->size ?? 'N/A' }}</p>
                        <p><span class="font-semibold text-gray-900">Uploaded:</span> {{ $fileMetadata->uploaded ?? 'N/A' }}</p>
                        <p><span class="font-semibold text-gray-900">Submitted:</span> {{ $fileMetadata->submitted ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
