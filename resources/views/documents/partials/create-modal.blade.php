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
                    <input type="file" id="attachmentInput" name="attachments[]" form="submissionForm" accept="image/*" multiple class="sr-only">

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
                            <p class="mt-2 text-sm text-gray-500">or <span class="font-semibold text-emerald-800 underline">click to browse</span> — images only (multiple allowed)</p>
                        </div>
                        <div id="dropZonePreview" class="hidden w-full flex-col gap-3 p-4">
                            <p id="previewCount" class="text-sm font-semibold text-gray-800"></p>
                            <div id="previewGrid" class="grid max-h-[320px] w-full grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3"></div>
                            <button type="button" id="clearFileBtn" class="text-sm font-semibold text-red-600 underline hover:text-red-800">Remove all photos</button>
                        </div>
                    </div>
                </div>

                <div id="routeBuilder"
                     class="min-h-0 lg:overflow-y-auto lg:pr-2"
                     data-departments='@json($createModalDepartments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values())'
                     data-default-routes='@json($createModalDefaultRoutes)'
                     data-old-route='@json(collect(old('route_departments', []))->map(fn ($id) => (int) $id)->values())'
                     data-old-type='@json(old('document_type', ''))'>
                    <form id="submissionForm" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <input type="hidden" name="from_modal" value="1">
                        <div id="routeHiddenInputs"></div>
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

                        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div>
                                    <label class="block text-sm font-semibold text-emerald-950">Routing Path</label>
                                    <p class="text-xs text-emerald-800/80">Set the departments this document will visit, in order.</p>
                                </div>
                                <span id="routeStepCount" class="shrink-0 rounded-full bg-white px-2.5 py-0.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-200">0 steps</span>
                            </div>

                            @error('route_departments')
                                <p id="routeClientError" class="mb-2 text-sm font-medium text-red-600">{{ $message }}</p>
                            @else
                                <p id="routeClientError" class="mb-2 hidden text-sm font-medium text-red-600"></p>
                            @enderror

                            <p class="mb-2 text-xs text-emerald-900/70">
                                1. Choose a department &nbsp;→&nbsp; 2. Click <strong>Add to path</strong>
                            </p>
                            <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                <select id="routeDeptPicker"
                                        class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                    <option value="">Select department…</option>
                                    @foreach($createModalDepartments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="routeAddBtn"
                                        class="inline-flex w-full min-h-[42px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm ring-2 ring-emerald-600/30 transition hover:bg-emerald-800 active:scale-[0.98] sm:w-auto sm:min-w-[9.5rem]">
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                                    </svg>
                                    Add to path
                                </button>
                            </div>

                            <p id="routeEmptyHint" class="rounded-lg border border-dashed border-emerald-300/80 bg-white/60 px-3 py-4 text-center text-sm text-gray-500">
                                No steps yet. Pick a category to load a suggested path, or use the dropdown and <strong>Add to path</strong> button above.
                            </p>

                            <ul id="routeStepsList" class="hidden space-y-2"></ul>

                            <p id="routeSuggestedHint" class="mt-2 hidden text-xs text-gray-500">
                                Suggested path loaded for this category — you can reorder or change it.
                            </p>
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

        const departments = JSON.parse(root.dataset.departments || '[]');
        const defaultRoutes = JSON.parse(root.dataset.defaultRoutes || '{}');
        const oldRoute = JSON.parse(root.dataset.oldRoute || '[]');
        const oldType = root.dataset.oldType || '';

        const form = document.getElementById('submissionForm');
        const typeSelect = document.getElementById('documentTypeSelect');
        const picker = document.getElementById('routeDeptPicker');
        const addBtn = document.getElementById('routeAddBtn');
        const listEl = document.getElementById('routeStepsList');
        const emptyHint = document.getElementById('routeEmptyHint');
        const suggestedHint = document.getElementById('routeSuggestedHint');
        const stepCount = document.getElementById('routeStepCount');
        const hiddenContainer = document.getElementById('routeHiddenInputs');
        const clientError = document.getElementById('routeClientError');

        let steps = [];

        function findDept(id) {
            return departments.find(d => d.id === id);
        }

        function setStepsFromIds(ids) {
            steps = ids.map(id => findDept(id)).filter(Boolean);
            render();
        }

        function syncHiddenInputs() {
            if (!hiddenContainer) return;
            hiddenContainer.innerHTML = '';
            steps.forEach(step => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'route_departments[]';
                input.value = String(step.id);
                hiddenContainer.appendChild(input);
            });
        }

        function updateCount() {
            const n = steps.length;
            stepCount.textContent = n + ' step' + (n === 1 ? '' : 's');
            emptyHint.classList.toggle('hidden', n > 0);
            listEl.classList.toggle('hidden', n === 0);
            const type = typeSelect?.value || '';
            const hasDefault = type && Array.isArray(defaultRoutes[type]) && defaultRoutes[type].length > 0;
            suggestedHint.classList.toggle('hidden', !hasDefault || n === 0);
        }

        function render() {
            listEl.innerHTML = '';
            steps.forEach((step, index) => {
                const li = document.createElement('li');
                li.className = 'flex items-center gap-2 rounded-xl border border-white bg-white px-3 py-2 shadow-sm';
                li.innerHTML = `
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">${index + 1}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800"></span>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" data-action="up" data-index="${index}" class="route-move rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40" ${index === 0 ? 'disabled' : ''}>↑</button>
                        <button type="button" data-action="down" data-index="${index}" class="route-move rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40" ${index === steps.length - 1 ? 'disabled' : ''}>↓</button>
                        <button type="button" data-action="remove" data-index="${index}" class="rounded-lg border border-red-200 px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Remove</button>
                    </div>
                `;
                li.querySelector('span:nth-child(2)').textContent = step.name;
                listEl.appendChild(li);
            });
            syncHiddenInputs();
            updateCount();
        }

        function addStep() {
            const id = parseInt(picker.value, 10);
            if (!id) return;
            if (steps.some(s => s.id === id)) {
                alert('That department is already in the route.');
                return;
            }
            const dept = findDept(id);
            if (dept) {
                steps.push(dept);
                render();
            }
            picker.value = '';
        }

        function applyDefaultRoute() {
            const type = typeSelect?.value || '';
            const ids = defaultRoutes[type] || [];
            if (ids.length) {
                setStepsFromIds(ids);
            }
        }

        addBtn?.addEventListener('click', addStep);

        picker?.addEventListener('change', function () {
            addBtn?.classList.toggle('ring-amber-400', !!picker.value);
            addBtn?.classList.toggle('bg-emerald-800', !!picker.value);
        });

        picker?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addStep();
            }
        });

        listEl?.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const index = parseInt(btn.dataset.index, 10);
            const action = btn.dataset.action;
            if (action === 'remove') {
                steps.splice(index, 1);
                render();
            } else if (action === 'up' && index > 0) {
                const item = steps.splice(index, 1)[0];
                steps.splice(index - 1, 0, item);
                render();
            } else if (action === 'down' && index < steps.length - 1) {
                const item = steps.splice(index, 1)[0];
                steps.splice(index + 1, 0, item);
                render();
            }
        });

        typeSelect?.addEventListener('change', function () {
            if (steps.length === 0) {
                applyDefaultRoute();
            } else if (confirm('Replace the current routing path with the suggested path for this category?')) {
                applyDefaultRoute();
            }
        });

        form?.addEventListener('submit', function (e) {
            syncHiddenInputs();
            if (steps.length < 1) {
                e.preventDefault();
                if (clientError) {
                    clientError.textContent = 'Add at least one department to the routing path.';
                    clientError.classList.remove('hidden');
                }
                document.getElementById('routeBuilder')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
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

        if (oldRoute.length) {
            setStepsFromIds(oldRoute);
        } else if (oldType) {
            applyDefaultRoute();
        } else {
            render();
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

        function syncInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
        }

        function addFiles(fileList) {
            const incoming = Array.from(fileList || []).filter(isImage);
            if (!incoming.length) {
                alert('Please choose image files only (PNG, JPG, etc.).');
                return;
            }
            incoming.forEach(file => {
                if (selectedFiles.length >= MAX_FILES) return;
                if (selectedFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) return;
                selectedFiles.push(file);
            });
            if (selectedFiles.length >= MAX_FILES) {
                alert('You can attach up to ' + MAX_FILES + ' images per submission.');
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
            previewCount.textContent = selectedFiles.length + ' photo' + (selectedFiles.length === 1 ? '' : 's') + ' selected';

            selectedFiles.forEach((file, index) => {
                const url = URL.createObjectURL(file);
                objectUrls.set(index, url);
                const wrap = document.createElement('div');
                wrap.className = 'relative overflow-hidden rounded-lg ring-1 ring-gray-200';
                wrap.innerHTML = `
                    <img src="${url}" alt="" class="h-24 w-full object-cover bg-gray-100">
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
