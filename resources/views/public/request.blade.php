<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit a Request — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    <header class="border-b border-emerald-200/60 bg-[#f1f2f1]/90">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-9 w-9 rounded-xl">
                <span class="text-lg font-extrabold tracking-tight text-emerald-950">SPeED <span class="text-emerald-700">TraQR</span></span>
            </a>
            <a href="{{ route('track.index') }}" class="text-sm font-semibold text-emerald-800 hover:underline">Track a request</a>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-6 py-10">
        <a href="{{ url('/') }}"
           onclick="if (document.referrer && history.length > 1) { event.preventDefault(); history.back(); }"
           class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-800 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18"/></svg>
            Back
        </a>
        <h1 class="text-3xl font-extrabold tracking-tight text-emerald-950">Submit a request online</h1>
        <p class="mt-2 text-sm text-gray-600">Fill in the form below instead of going to the municipality. You'll get a tracking number to follow your request.</p>

        @if($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">Please fix the highlighted {{ $errors->count() === 1 ? 'field' : 'fields' }} below:</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @php
            $field = 'w-full rounded-xl border bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30';
            $ok = 'border-gray-200';
            $bad = 'border-red-400 bg-red-50/40';
        @endphp

        <form method="POST" action="{{ route('public.request.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" novalidate>
            @csrf

            {{-- Honeypot: must stay empty. Hidden from real users. --}}
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label for="document_type" class="mb-1 block text-sm font-semibold text-gray-700">Request type <span class="text-red-500">*</span></label>
                <select id="document_type" name="document_type" required aria-invalid="@error('document_type')true @else false @enderror" @error('document_type') aria-describedby="document_type-err" @enderror class="{{ $field }} @error('document_type') {{ $bad }} @else {{ $ok }} @enderror">
                    <option value="">Select type…</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(old('document_type') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                @error('document_type')<p id="document_type-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Requirements for the chosen request type — populated by JS from the
                 catalog. Citizens bring the originals; uploading a copy is optional. --}}
            <div id="requirementsSection" class="hidden rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                <p class="text-sm font-semibold text-emerald-900">Requirements for this request</p>
                <p class="mt-0.5 text-xs text-gray-600">Please bring the <strong>original</strong> of each to the counter — staff will verify them. You may optionally upload a photo or scan now to speed things up.</p>
                <ul id="requirementsList" class="mt-3 space-y-3"></ul>
            </div>
            <script>
                window.__requirements = @json($requestTypes->mapWithKeys(fn ($t) => [$t->name => $t->requirements->map(fn ($r) => ['id' => $r->id, 'label' => $r->label, 'mandatory' => (bool) $r->is_mandatory])->values()]));
            </script>

            <div>
                <label for="purpose" class="mb-1 block text-sm font-semibold text-gray-700">Purpose</label>
                <input id="purpose" name="purpose" value="{{ old('purpose') }}" maxlength="255" aria-invalid="@error('purpose')true @else false @enderror" @error('purpose') aria-describedby="purpose-err" @enderror class="{{ $field }} @error('purpose') {{ $bad }} @else {{ $ok }} @enderror">
                <p class="mt-1 text-xs text-gray-500">What the document is for — e.g. “business permit renewal.”</p>
                @error('purpose')<p id="purpose-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="5000" aria-invalid="@error('description')true @else false @enderror" @error('description') aria-describedby="description-err" @enderror class="{{ $field }} @error('description') {{ $bad }} @else {{ $ok }} @enderror">{{ old('description') }}</textarea>
                @error('description')<p id="description-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="citizen_name" class="mb-1 block text-sm font-semibold text-gray-700">Your name <span class="text-red-500">*</span></label>
                    <input id="citizen_name" name="citizen_name" value="{{ old('citizen_name') }}" required autocomplete="name" maxlength="255" aria-invalid="@error('citizen_name')true @else false @enderror" @error('citizen_name') aria-describedby="citizen_name-err" @enderror class="{{ $field }} @error('citizen_name') {{ $bad }} @else {{ $ok }} @enderror">
                    @error('citizen_name')<p id="citizen_name-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="citizen_email" class="mb-1 block text-sm font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input id="citizen_email" type="email" name="citizen_email" value="{{ old('citizen_email') }}" required autocomplete="email" inputmode="email" maxlength="255" aria-invalid="@error('citizen_email')true @else false @enderror" aria-describedby="citizen_email-hint @error('citizen_email') citizen_email-err @enderror" class="{{ $field }} @error('citizen_email') {{ $bad }} @else {{ $ok }} @enderror">
                    <p id="citizen_email-hint" class="mt-1 text-xs text-gray-500">We'll send your tracking link here.</p>
                    @error('citizen_email')<p id="citizen_email-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="citizen_contact" class="mb-1 block text-sm font-semibold text-gray-700">Contact number</label>
                <input id="citizen_contact" type="tel" name="citizen_contact" value="{{ old('citizen_contact') }}" autocomplete="tel" inputmode="tel" maxlength="255" aria-invalid="@error('citizen_contact')true @else false @enderror" @error('citizen_contact') aria-describedby="citizen_contact-err" @enderror class="{{ $field }} @error('citizen_contact') {{ $bad }} @else {{ $ok }} @enderror">
                @error('citizen_contact')<p id="citizen_contact-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between gap-3">
                    <label for="attachments" class="block text-sm font-semibold text-gray-700">Supporting files <span class="font-normal text-gray-500">(optional)</span></label>
                    <span id="fileCount" class="text-xs font-semibold text-emerald-800" aria-live="polite">0 of 5 files</span>
                </div>

                {{-- Clickable drop area; the native input is visually hidden but still submits. --}}
                <label for="attachments" id="fileDrop" class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-emerald-300 bg-emerald-50/40 px-4 py-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                    <span class="text-sm font-semibold text-emerald-800">Tap to add files</span>
                    <span class="text-xs text-gray-500">JPG, PNG, WEBP, PDF, or DOC/DOCX — max 10 MB each, up to 5 files</span>
                </label>
                <input id="attachments" type="file" name="attachments[]" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" multiple class="sr-only" aria-describedby="attachments-err fileError">

                {{-- Selected-file list (thumbnails + name + size + remove); populated by JS. --}}
                <ul id="fileList" class="mt-3 space-y-2"></ul>
                <p id="fileError" class="mt-2 hidden text-xs font-medium text-red-600" role="alert"></p>
                @error('attachments.*')<p id="attachments-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4 text-sm text-emerald-900">
                <input type="checkbox" name="consent" value="1" @checked(old('consent')) class="mt-0.5">
                <span>I agree that the information I provide will be collected and processed by the municipality solely to handle this request, in accordance with the Data Privacy Act of 2012 (RA 10173). Only the details needed to process and contact me about this request are collected.</span>
            </label>

            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-emerald-800 px-6 py-2.5 font-semibold text-white transition hover:bg-emerald-900">Submit request</button>
            </div>
        </form>
    </main>

    {{-- Client-side error PREVENTION: catch the required fields before the
         server round-trip and point the citizen straight at the problem.
         Progressive enhancement only — the form still posts and is fully
         re-validated server-side if JavaScript is unavailable. --}}
    <script>
        (function () {
            const form = document.querySelector('form[action="{{ route('public.request.store') }}"]');
            if (!form) { return; }

            form.addEventListener('submit', function (e) {
                form.querySelectorAll('[data-client-err]').forEach(n => n.remove());
                const problems = [];
                const check = (el, ok, msg) => { if (!ok) { problems.push({ el, msg }); } };

                check(form.document_type, !!form.document_type.value, 'Please choose a request type.');
                check(form.citizen_name, form.citizen_name.value.trim().length > 0, 'Please enter your name.');
                const email = form.citizen_email.value.trim();
                check(form.citizen_email, email.length > 0 && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email), email.length ? 'Please enter a valid email address.' : 'Please enter your email.');
                check(form.consent, form.consent.checked, 'Please agree to the data privacy notice to submit.');

                if (!problems.length) { return; }
                e.preventDefault();

                problems.forEach(({ el, msg }, i) => {
                    if (el.type !== 'checkbox') { el.classList.add('border-red-400', 'bg-red-50/40'); }
                    const anchor = el.type === 'checkbox' ? el.closest('label') : el;
                    const p = document.createElement('p');
                    p.dataset.clientErr = '1';
                    p.className = 'mt-1 text-xs font-medium text-red-600';
                    p.textContent = msg;
                    anchor.insertAdjacentElement('afterend', p);
                    if (i === 0) { el.focus(); anchor.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
                });
            });
        })();

        // Attachment picker: visible file list, count, per-file removal, image
        // thumbnails. Mirrors the server rules (≤5 files, ≤10 MB, jpg/png/webp/
        // pdf/doc/docx). Degrades to a read-only list where DataTransfer is
        // unavailable (older devices) — the form still submits natively.
        (function () {
            const input = document.getElementById('attachments');
            const list = document.getElementById('fileList');
            const countEl = document.getElementById('fileCount');
            const errEl = document.getElementById('fileError');
            const drop = document.getElementById('fileDrop');
            if (!input) { return; }

            const MAX = 5;
            const MAX_BYTES = 10 * 1024 * 1024;
            const ALLOWED = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];
            const canEdit = typeof DataTransfer !== 'undefined';
            let files = [];

            const ext = (n) => (n.split('.').pop() || '').toLowerCase();
            const isImg = (n) => ['jpg', 'jpeg', 'png', 'webp'].includes(ext(n));
            const human = (b) => b < 1024 ? b + ' B' : (b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB');
            const showErr = (m) => { errEl.textContent = m; errEl.classList.remove('hidden'); };
            const clearErr = () => { errEl.textContent = ''; errEl.classList.add('hidden'); };
            const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

            function syncInput() {
                if (!canEdit) { return; }
                const dt = new DataTransfer();
                files.forEach(f => dt.items.add(f));
                input.files = dt.files;
            }

            const fileSvg = '<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM14 3v4h4"/></svg></span>';

            function render() {
                list.innerHTML = '';
                files.forEach((f, idx) => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2';
                    let thumb;
                    if (isImg(f.name)) {
                        const url = URL.createObjectURL(f);
                        thumb = `<img src="${url}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover" onload="URL.revokeObjectURL(this.src)">`;
                    } else {
                        thumb = fileSvg;
                    }
                    li.innerHTML = thumb +
                        `<div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-gray-800">${esc(f.name)}</p><p class="text-xs text-gray-500">${human(f.size)}</p></div>`;
                    if (canEdit) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400';
                        btn.setAttribute('aria-label', 'Remove ' + f.name);
                        btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>';
                        btn.addEventListener('click', () => { files.splice(idx, 1); syncInput(); render(); });
                        li.appendChild(btn);
                    }
                    list.appendChild(li);
                });
                countEl.textContent = `${files.length} of ${MAX} files`;
            }

            function addFiles(incoming) {
                clearErr();
                let firstErr = '';
                for (const f of incoming) {
                    if (files.length >= MAX) { firstErr = firstErr || `You can attach up to ${MAX} files.`; break; }
                    if (!ALLOWED.includes(ext(f.name))) { firstErr = firstErr || `“${f.name}” isn't an accepted file type.`; continue; }
                    if (f.size > MAX_BYTES) { firstErr = firstErr || `“${f.name}” is larger than 10 MB.`; continue; }
                    if (files.some(x => x.name === f.name && x.size === f.size)) { continue; }
                    files.push(f);
                }
                if (firstErr) { showErr(firstErr); }
                syncInput();
                render();
            }

            input.addEventListener('change', () => {
                if (canEdit) {
                    addFiles(Array.from(input.files));
                } else {
                    // No DataTransfer: reflect the native selection read-only.
                    files = Array.from(input.files);
                    render();
                }
            });

            if (drop && canEdit) {
                ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('border-emerald-500', 'bg-emerald-50'); }));
                ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('border-emerald-500', 'bg-emerald-50')));
                drop.addEventListener('drop', (e) => { e.preventDefault(); if (e.dataTransfer?.files?.length) { addFiles(Array.from(e.dataTransfer.files)); } });
            }
        })();

        // Show the selected request type's requirement checklist, each with an
        // optional file input (name="requirements[<id>]").
        (function () {
            const sel = document.getElementById('document_type');
            const section = document.getElementById('requirementsSection');
            const list = document.getElementById('requirementsList');
            const data = window.__requirements || {};
            if (!sel || !section || !list) { return; }

            const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

            function render() {
                const reqs = data[sel.value] || [];
                list.innerHTML = '';
                if (!reqs.length) { section.classList.add('hidden'); return; }
                section.classList.remove('hidden');
                reqs.forEach((r) => {
                    const li = document.createElement('li');
                    li.className = 'rounded-lg border border-gray-200 bg-white p-3';
                    li.innerHTML =
                        `<div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-800">${esc(r.label)}</span>
                            <span class="shrink-0 text-[11px] font-semibold ${r.mandatory ? 'text-red-600' : 'text-gray-400'}">${r.mandatory ? 'Required' : 'Optional'}</span>
                        </div>
                        <input type="file" name="requirements[${r.id}]" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
                               class="mt-2 w-full text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800">`;
                    list.appendChild(li);
                });
            }

            sel.addEventListener('change', render);
            render(); // handle old() repopulation after a validation error
        })();
    </script>
</body>
</html>
