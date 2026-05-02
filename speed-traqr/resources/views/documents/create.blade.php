<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">New Submission</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl">
        <div class="grid grid-cols-1 gap-8 rounded-3xl border border-gray-200/90 bg-white p-6 shadow-md lg:grid-cols-2">
            <div class="rounded-2xl bg-gray-100/80 p-4">
                <input type="file" id="attachmentInput" name="attachment" form="submissionForm" accept="image/*" class="sr-only">

                <div
                    id="dropZone"
                    role="button"
                    tabindex="0"
                    aria-label="Choose file or drop image here"
                    class="flex h-full min-h-[430px] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 bg-gray-200/60 text-center transition hover:border-emerald-400 hover:bg-emerald-50/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                >
                    <div id="dropZonePlaceholder" class="px-4">
                        <svg class="mx-auto mb-3 h-12 w-12 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 14v4a2 2 0 002 2h12a2 2 0 002-2v-4"/>
                        </svg>
                        <p class="text-lg font-semibold text-gray-600 sm:text-xl">Drop your file here</p>
                        <p class="mt-2 text-sm text-gray-500">or <span class="font-semibold text-emerald-800 underline">click to browse</span> — images only</p>
                    </div>
                    <div id="dropZonePreview" class="hidden max-h-[380px] w-full max-w-md flex-col items-center gap-3 p-4">
                        <img id="previewImg" src="" alt="Selected file preview" class="max-h-64 w-auto max-w-full rounded-lg object-contain shadow-md ring-1 ring-gray-200">
                        <p id="previewName" class="truncate text-sm font-medium text-gray-800"></p>
                        <button type="button" id="clearFileBtn" class="text-sm font-semibold text-red-600 underline hover:text-red-800">Remove file</button>
                    </div>
                </div>
            </div>

            <div>
                <form id="submissionForm" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">File Name</label>
                        <input name="remarks" value="{{ old('remarks') }}" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="File Name">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Description</label>
                        <textarea name="description" rows="3" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="Description">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Expire At</label>
                        <div class="relative">
                            <input type="date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 pr-10 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Category</label>
                        <select name="document_type" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" required>
                            <option value="">Select Category</option>
                            @foreach($categoryOptions as $category)
                                <option value="{{ $category }}" @selected(old('document_type') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Citizen Name</label>
                        <input name="citizen_name" value="{{ old('citizen_name') }}" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-600">Citizen Contact</label>
                        <input name="citizen_contact" value="{{ old('citizen_contact') }}" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-gray-300 px-6 py-2.5 font-semibold text-gray-700 transition hover:bg-gray-400">Cancel</a>
                        <button type="submit" class="rounded-xl bg-emerald-800 px-6 py-2.5 font-semibold text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('attachmentInput');
            const zone = document.getElementById('dropZone');
            const placeholder = document.getElementById('dropZonePlaceholder');
            const previewWrap = document.getElementById('dropZonePreview');
            const previewImg = document.getElementById('previewImg');
            const previewName = document.getElementById('previewName');
            const clearBtn = document.getElementById('clearFileBtn');

            if (!input || !zone) return;

            function showPreview(file) {
                if (!file || !file.type.startsWith('image/')) return;
                const url = URL.createObjectURL(file);
                if (previewImg.dataset.objectUrl) URL.revokeObjectURL(previewImg.dataset.objectUrl);
                previewImg.dataset.objectUrl = url;
                previewImg.src = url;
                previewName.textContent = file.name;
                placeholder.classList.add('hidden');
                previewWrap.classList.remove('hidden');
                previewWrap.classList.add('flex');
            }

            function clearFile() {
                input.value = '';
                if (previewImg.dataset.objectUrl) {
                    URL.revokeObjectURL(previewImg.dataset.objectUrl);
                    delete previewImg.dataset.objectUrl;
                }
                previewImg.removeAttribute('src');
                previewWrap.classList.add('hidden');
                previewWrap.classList.remove('flex');
                placeholder.classList.remove('hidden');
            }

            zone.addEventListener('click', function (e) {
                if (e.target.closest('#clearFileBtn')) return;
                input.click();
            });

            zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    clearFile();
                });
            }

            input.addEventListener('change', function () {
                const file = this.files && this.files[0];
                if (file) showPreview(file);
                else clearFile();
            });

            ['dragenter', 'dragover'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    zone.classList.add('border-emerald-500', 'bg-emerald-100/50');
                });
            });

            zone.addEventListener('dragleave', function (e) {
                e.preventDefault();
                zone.classList.remove('border-emerald-500', 'bg-emerald-100/50');
            });

            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('border-emerald-500', 'bg-emerald-100/50');
                const file = e.dataTransfer.files && e.dataTransfer.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    alert('Please drop an image file (PNG, JPG, etc.).');
                    return;
                }
                try {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                } catch (err) {
                    return;
                }
                showPreview(file);
            });
        })();
    </script>
</x-app-layout>
