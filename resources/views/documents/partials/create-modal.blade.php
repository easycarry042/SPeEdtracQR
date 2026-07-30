{{-- "New Submission" modal — included by layouts/app.blade.php when the user can create documents.
     Open with window.openCreateDocumentModal(); auto-opens after a failed validation round-trip
     (old('from_modal')) or when redirected from the old /documents/create URL (session flash). --}}
@php
    $createModalAutoOpen = (bool) (session('openCreateModal') || old('from_modal'));
@endphp

<div id="createDocumentModal"
     class="{{ $createModalAutoOpen ? '' : 'hidden' }} fixed inset-0 z-[100]"
     role="dialog" aria-modal="true" aria-labelledby="createDocumentModalTitle">
    <div class="fixed inset-0 bg-emerald-950/40 backdrop-blur-sm" data-close-create-modal></div>

    <div class="relative flex h-full items-center justify-center p-4 sm:p-6">
        <div class="relative flex h-full max-h-[52rem] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-gray-200/90 bg-white shadow-2xl">
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <h2 id="createDocumentModalTitle" class="text-2xl font-bold tracking-tight text-emerald-950">New Submission</h2>
                <button type="button" data-close-create-modal
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                        aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- On lg+ the panel height is fixed: the drop zone fills the left column and
                 only the form column scrolls. On smaller screens the whole body scrolls. --}}
            <div id="createModalBody" class="grid min-h-0 flex-1 grid-cols-1 gap-8 overflow-y-auto p-6 lg:grid-cols-2 lg:overflow-hidden">
                <div class="min-h-0 rounded-2xl bg-gray-100/80 p-4">
                    <input type="file" id="attachmentInput" name="attachments[]" form="submissionForm" accept="{{ \App\Support\UploadRules::accept() }}" multiple class="sr-only">

                    <div
                        id="dropZone"
                        role="button"
                        tabindex="0"
                        aria-label="Choose file or drop image here"
                        class="flex h-full min-h-[320px] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 bg-gray-200/60 text-center transition hover:border-emerald-400 hover:bg-emerald-50/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                    >
                        <div id="dropZonePlaceholder" class="px-4">
                            <svg class="mx-auto mb-3 h-12 w-12 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 14v4a2 2 0 002 2h12a2 2 0 002-2v-4"/>
                            </svg>
                            <p class="text-lg font-semibold text-gray-600 sm:text-xl">Drop your file here</p>
                            <p class="mt-2 text-sm text-gray-500">or <span class="font-semibold text-emerald-800 underline">click to browse</span> — images, PDF or Word (multiple allowed)</p>
                        </div>
                        <div id="dropZonePreview" class="hidden w-full flex-col gap-3 p-4">
                            <p id="previewCount" class="text-sm font-semibold text-gray-800"></p>
                            <div id="previewGrid" class="grid max-h-[320px] w-full grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3"></div>
                            <button type="button" id="clearFileBtn" class="text-sm font-semibold text-red-600 underline hover:text-red-800">Remove all files</button>
                        </div>
                    </div>
                </div>

                <div id="routeBuilder" class="min-h-0 lg:overflow-y-auto lg:pr-2">
                    <form id="submissionForm" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="from_modal" value="1">
                        <div id="createModalErrors" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>
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
                            <select name="document_type" id="documentTypeSelect"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" required>
                                <option value="">Select Category</option>
                                @foreach($createModalCategories as $category)
                                    <option value="{{ $category }}" @selected(old('document_type') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 px-4 py-3">
                            <p class="text-xs text-emerald-900/80">
                                After submission, an admin assigns the staff member responsible for advancing this document through its status stages.
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-600">Citizen Name</label>
                            <input name="citizen_name" value="{{ old('citizen_name') }}" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-600">Citizen Email</label>
                            <input name="citizen_email" type="email" value="{{ old('citizen_email') }}" placeholder="name@gmail.com" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-600">Citizen Contact</label>
                            <input name="citizen_contact" value="{{ old('citizen_contact') }}" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" data-close-create-modal class="rounded-xl bg-gray-300 px-6 py-2.5 font-semibold text-gray-700 transition hover:bg-gray-400">Cancel</button>
                            <button type="submit" id="createSubmitBtn" class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-6 py-2.5 font-semibold text-white transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                                <svg id="createSubmitSpinner" class="hidden h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span id="createSubmitLabel">Submit</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Filled with the created-card extracted from the documents.created response after an AJAX submit --}}
            <div id="createModalSuccess" class="hidden min-h-0 flex-1 overflow-y-auto p-6"></div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('createDocumentModal');
        if (!modal) return;

        function openModal() {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            modal.querySelector('select, input:not([type=hidden]), textarea')?.focus();
        }

        function closeModal() {
            // After a successful submission the page data (tables, counters) is stale — reload instead of just hiding.
            if (modal.dataset.created === '1') {
                window.location.reload();
                return;
            }
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        window.openCreateDocumentModal = openModal;

        modal.addEventListener('click', function (e) {
            if (e.target.closest('[data-close-create-modal]')) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        if (!modal.classList.contains('hidden')) {
            document.body.classList.add('overflow-hidden');
        }
    })();

    (function () {
        const root = document.getElementById('routeBuilder');
        if (!root) return;

        const form = document.getElementById('submissionForm');

        form?.addEventListener('submit', function (e) {
            e.preventDefault();
            submitViaFetch();
        });

        function setSubmitting(submitting) {
            const btn = document.getElementById('createSubmitBtn');
            const spinner = document.getElementById('createSubmitSpinner');
            const label = document.getElementById('createSubmitLabel');
            if (btn) btn.disabled = submitting;
            spinner?.classList.toggle('hidden', !submitting);
            if (label) label.textContent = submitting ? 'Submitting…' : 'Submit';
        }

        function showSubmitErrors(messages) {
            const box = document.getElementById('createModalErrors');
            if (!box) return;
            box.innerHTML = '';
            messages.forEach(message => {
                const p = document.createElement('p');
                p.textContent = message;
                box.appendChild(p);
            });
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function showCreatedCard(card) {
            const body = document.getElementById('createModalBody');
            const success = document.getElementById('createModalSuccess');
            const title = document.getElementById('createDocumentModalTitle');
            const modal = document.getElementById('createDocumentModal');
            if (!body || !success) return;
            body.classList.add('hidden');
            success.innerHTML = '';
            success.appendChild(card);
            success.classList.remove('hidden');
            if (title) title.textContent = 'Document Created';
            if (modal) modal.dataset.created = '1';
        }

        function submitViaFetch() {
            document.getElementById('createModalErrors')?.classList.add('hidden');
            setSubmitting(true);

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(async response => {
                    const contentType = response.headers.get('content-type') || '';
                    if (response.ok && contentType.includes('text/html')) {
                        // The store redirect was followed to the documents.created page — lift its card into the modal.
                        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const card = doc.getElementById('documentCreatedCard');
                        if (card) {
                            showCreatedCard(card);
                            return;
                        }
                        window.location.href = response.url;
                        return;
                    }
                    if (response.status === 422 && contentType.includes('json')) {
                        const data = await response.json();
                        showSubmitErrors(Object.values(data.errors || {}).flat());
                        return;
                    }
                    throw new Error('Unexpected response: ' + response.status);
                })
                .catch(() => showSubmitErrors(['Something went wrong while submitting. Please check your connection and try again.']))
                .finally(() => setSubmitting(false));
        }
    })();

    (function () {
        const input = document.getElementById('attachmentInput');
        const zone = document.getElementById('dropZone');
        const placeholder = document.getElementById('dropZonePlaceholder');
        const previewWrap = document.getElementById('dropZonePreview');
        const previewGrid = document.getElementById('previewGrid');
        const previewCount = document.getElementById('previewCount');
        const clearBtn = document.getElementById('clearFileBtn');
        const MAX_FILES = 10;

        if (!input || !zone) return;

        let selectedFiles = [];
        const objectUrls = new Map();

        function isImage(file) {
            return file && file.type.startsWith('image/');
        }

        const ALLOWED_EXT = ['pdf', 'doc', 'docx'];
        function isAllowed(file) {
            if (!file) return false;
            if (isImage(file)) return true;
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            return ALLOWED_EXT.includes(ext);
        }

        function syncInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
        }

        function addFiles(fileList) {
            const incoming = Array.from(fileList || []).filter(isAllowed);
            if (!incoming.length) {
                alert('Please choose images, PDF or Word files only.');
                return;
            }
            incoming.forEach(file => {
                if (selectedFiles.length >= MAX_FILES) return;
                if (selectedFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) return;
                selectedFiles.push(file);
            });
            if (selectedFiles.length >= MAX_FILES) {
                alert('You can attach up to ' + MAX_FILES + ' files per submission.');
            }
            syncInputFiles();
            renderPreview();
        }

        function renderPreview() {
            previewGrid.innerHTML = '';
            objectUrls.forEach(url => URL.revokeObjectURL(url));
            objectUrls.clear();

            if (!selectedFiles.length) {
                previewWrap.classList.add('hidden');
                previewWrap.classList.remove('flex');
                placeholder.classList.remove('hidden');
                return;
            }

            placeholder.classList.add('hidden');
            previewWrap.classList.remove('hidden');
            previewWrap.classList.add('flex');
            previewCount.textContent = selectedFiles.length + ' file' + (selectedFiles.length === 1 ? '' : 's') + ' selected';

            selectedFiles.forEach((file, index) => {
                const wrap = document.createElement('div');
                wrap.className = 'relative overflow-hidden rounded-lg ring-1 ring-gray-200';
                let media;
                if (isImage(file)) {
                    const url = URL.createObjectURL(file);
                    objectUrls.set(index, url);
                    media = `<img src="${url}" alt="" class="h-24 w-full object-cover bg-gray-100">`;
                } else {
                    const ext = (file.name.split('.').pop() || 'file').toUpperCase();
                    media = `<div class="flex h-24 w-full flex-col items-center justify-center gap-1 bg-gray-100 text-gray-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v4h4"/></svg>
                        <span class="text-[10px] font-bold">${ext}</span>
                    </div>`;
                }
                wrap.innerHTML = `
                    ${media}
                    <button type="button" data-index="${index}" class="remove-preview absolute right-1 top-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-bold text-white hover:bg-black/80">×</button>
                    <p class="truncate px-1 py-0.5 text-[10px] text-gray-600">${file.name}</p>
                `;
                previewGrid.appendChild(wrap);
            });
        }

        function clearFiles() {
            selectedFiles = [];
            syncInputFiles();
            renderPreview();
        }

        previewGrid?.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-preview');
            if (!btn) return;
            e.stopPropagation();
            selectedFiles.splice(parseInt(btn.dataset.index, 10), 1);
            syncInputFiles();
            renderPreview();
        });

        zone.addEventListener('click', function (e) {
            if (e.target.closest('#clearFileBtn') || e.target.closest('.remove-preview')) return;
            input.click();
        });

        zone.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        clearBtn?.addEventListener('click', function (e) {
            e.stopPropagation();
            clearFiles();
        });

        input.addEventListener('change', function () {
            if (this.files && this.files.length) addFiles(this.files);
            else clearFiles();
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
            addFiles(e.dataTransfer.files);
        });
    })();
</script>
