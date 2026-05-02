<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Document Created') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Document Successfully Registered</h3>
                    <p class="mt-2 text-sm text-gray-700"><strong>Tracking Number:</strong> {{ $document->tracking_number }}</p>
                    <p class="text-sm text-gray-700"><strong>Document Type:</strong> {{ $document->document_type }}</p>
                    <p class="text-sm text-gray-700"><strong>Citizen:</strong> {{ $document->citizen_name ?? 'N/A' }}</p>

                    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-center">
                        <img src="{{ Storage::url($qrPath) }}" alt="QR Code" class="mx-auto w-48">
                        <p class="mt-2 text-sm text-gray-500">Scan this QR to update routing and tracking.</p>
                        <a href="{{ Storage::url($qrPath) }}" target="_blank" class="mt-3 inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100" onclick="window.print(); return false;">
                            Print QR Sticker
                        </a>
                    </div>

                    <div class="mt-8 flex justify-between">
                        <a href="{{ route('documents.create') }}" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Create Another</a>
                        <a href="{{ route('dashboard') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>