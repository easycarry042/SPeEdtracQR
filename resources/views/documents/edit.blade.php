<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950">Edit Document</h1>
            <p class="mt-1 text-sm text-emerald-700">
                <span class="font-mono font-semibold">{{ $document->tracking_number }}</span>
                — update citizen and document details. Routing and status are changed through scanning, not here.
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <form method="POST" action="{{ route('documents.update', $document) }}"
              class="space-y-5 rounded-2xl border border-gray-200/90 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="document_type" :value="__('Document Type')" />
                <input list="documentTypeOptions" id="document_type" name="document_type" required
                       value="{{ old('document_type', $document->document_type) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                <datalist id="documentTypeOptions">
                    @foreach($categoryOptions as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>
                <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="citizen_name" :value="__('Citizen Name')" />
                    <x-text-input id="citizen_name" name="citizen_name" type="text" class="mt-1 block w-full"
                                  :value="old('citizen_name', $document->citizen_name)" />
                    <x-input-error :messages="$errors->get('citizen_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="citizen_contact" :value="__('Citizen Contact')" />
                    <x-text-input id="citizen_contact" name="citizen_contact" type="text" class="mt-1 block w-full"
                                  :value="old('citizen_contact', $document->citizen_contact)" />
                    <x-input-error :messages="$errors->get('citizen_contact')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="purpose" :value="__('Purpose')" />
                <x-text-input id="purpose" name="purpose" type="text" class="mt-1 block w-full"
                              :value="old('purpose', $document->purpose)" />
                <x-input-error :messages="$errors->get('purpose')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" :value="__('Description')" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $document->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="remarks" :value="__('Remarks')" />
                <textarea id="remarks" name="remarks" rows="2"
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('remarks', $document->remarks) }}</textarea>
                <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('track.show', $document->tracking_number) }}"
                   class="rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100">Cancel</a>
                <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
