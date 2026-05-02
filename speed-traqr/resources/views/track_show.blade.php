<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Track Document: ' . $document->tracking_number) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- File metadata (placeholders) -->
                    <div class="grid grid-cols-2 gap-4 mb-8 p-4 bg-gray-50 rounded">
                        <div><strong>File Path:</strong> {{ $fileMetadata->path }}</div>
                        <div><strong>File Type:</strong> {{ $fileMetadata->type }}</div>
                        <div><strong>File Size:</strong> {{ $fileMetadata->size }}</div>
                        <div><strong>Uploaded:</strong> {{ $fileMetadata->uploaded }}</div>
                        <div><strong>Submitted:</strong> {{ $fileMetadata->submitted }}</div>
                    </div>

                    <!-- Document details -->
                    <h3 class="text-lg font-semibold">Document Information</h3>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div><strong>Tracking Number:</strong> {{ $document->tracking_number }}</div>
                        <div><strong>Type:</strong> {{ $document->document_type }}</div>
                        <div><strong>Citizen:</strong> {{ $document->citizen_name ?? 'N/A' }}</div>
                        <div><strong>Status:</strong> 
                            <span class="px-2 py-1 rounded text-xs {{ $document->status == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ ucfirst($document->status) }}
                            </span>
                        </div>
                        <div><strong>Current Department:</strong> {{ $document->currentDepartment->name ?? 'Not yet assigned' }}</div>
                    </div>

                    <!-- Routing History -->
                    <h3 class="text-lg font-semibold mb-3">Routing History</h3>
                    <div class="relative border-l-4 border-blue-500 ml-4 pl-6 space-y-6">
                        @foreach($document->scans as $scan)
                            <div class="relative">
                                <div class="absolute -left-10 mt-1.5 w-4 h-4 rounded-full {{ $scan->action == 'in' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium">{{ $scan->action == 'in' ? 'Entered' : 'Exited' }} <strong>{{ $scan->department->name }}</strong></p>
                                        <p class="text-sm text-gray-500">Scanned by: {{ $scan->user->name ?? 'System' }}</p>
                                    </div>
                                    <p class="text-sm text-gray-500">{{ $scan->scanned_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 text-center">
                        <a href="{{ route('track.search') }}" class="text-blue-600 hover:underline">Track another document</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>