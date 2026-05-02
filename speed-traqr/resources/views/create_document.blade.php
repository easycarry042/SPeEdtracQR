<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New Submission') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="p-6">
                    <form method="POST" action="{{ route('documents.store') }}" class="space-y-5">
                        @csrf

                        @if ($errors->any())
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div>
                            <label for="documentTypeSelect" class="mb-1 block text-sm font-medium text-gray-700">Document Type</label>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <select id="documentTypeSelect" class="rounded-lg border-gray-300 text-sm shadow-sm" onchange="syncDocumentTypeInput(this.value)">
                                    <option value="">Custom type...</option>
                                    @foreach($documentTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                                <input id="documentTypeInput" name="document_type" value="{{ old('document_type') }}" required placeholder="Enter document type" class="rounded-lg border-gray-300 text-sm shadow-sm" />
                            </div>
                        </div>

                        <div>
                            <label for="citizen_name" class="mb-1 block text-sm font-medium text-gray-700">Citizen Name (optional)</label>
                            <input id="citizen_name" name="citizen_name" value="{{ old('citizen_name') }}" class="w-full rounded-lg border-gray-300 text-sm shadow-sm" />
                        </div>

                        <div>
                            <label for="remarks" class="mb-1 block text-sm font-medium text-gray-700">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="4" class="w-full rounded-lg border-gray-300 text-sm shadow-sm">{{ old('remarks') }}</textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('dashboard') }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
                            <button type="submit" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                                Generate QR and Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function syncDocumentTypeInput(value) {
            if (!value) return;
            document.getElementById('documentTypeInput').value = value;
        }
    </script>
</x-app-layout>