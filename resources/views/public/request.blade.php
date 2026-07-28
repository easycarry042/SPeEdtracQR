<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit a Request — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-100 antialiased text-gray-900">

    {{-- Same public portal header as /citizen and /track for a unified look. --}}
    @include('layouts.partials.public-header')

    <main class="mx-auto max-w-3xl px-6 py-10">
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

            @php
                $groupedTypes = $requestTypes->groupBy('kind');
                $groups = [
                    \App\Models\RequestType::KIND_DOCUMENT => 'Documents & Permits',
                    \App\Models\RequestType::KIND_BOOKING => 'Facility reservations',
                    \App\Models\RequestType::KIND_EQUIPMENT => 'Equipment borrowing',
                    \App\Models\RequestType::KIND_SERVICE => 'Services',
                ];
                // kind => [type name, …], in display order — powers the JS cascade.
                $typesByCategory = collect($groups)->keys()
                    ->filter(fn ($kind) => $groupedTypes->has($kind))
                    ->mapWithKeys(fn ($kind) => [$kind => $groupedTypes[$kind]->pluck('name')->values()])
                    ->all();
            @endphp

            {{-- Step 1 (JS only): pick a category to narrow the type list below.
                 Hidden without JS — the full grouped type select still works. --}}
            <div id="categoryWrap" class="hidden">
                <label for="request_category" class="mb-1 block text-sm font-semibold text-gray-700">Request category <span class="text-red-500">*</span></label>
                <select id="request_category" class="{{ $field }} {{ $ok }}">
                    <option value="">Select a category…</option>
                    @foreach($groups as $kind => $groupLabel)
                        @if($groupedTypes->has($kind))
                            <option value="{{ $kind }}">{{ $groupLabel }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label for="document_type" class="mb-1 block text-sm font-semibold text-gray-700">Request type <span class="text-red-500">*</span></label>
                <select id="document_type" name="document_type" required aria-invalid="@error('document_type')true @else false @enderror" @error('document_type') aria-describedby="document_type-err" @enderror class="{{ $field }} @error('document_type') {{ $bad }} @else {{ $ok }} @enderror">
                    <option value="">Select type…</option>
                    @foreach($groups as $kind => $groupLabel)
                        @if($groupedTypes->has($kind))
                            <optgroup label="{{ $groupLabel }}">
                                @foreach($groupedTypes[$kind] as $rt)
                                    <option value="{{ $rt->name }}" @selected(old('document_type') === $rt->name)>{{ $rt->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
                @error('document_type')<p id="document_type-err" class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Requirements for the chosen request type — populated by JS from the
                 catalog. Citizens bring the originals to the counter. --}}
            <div id="requirementsSection" class="hidden rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                <p class="text-sm font-semibold text-emerald-900">Requirements for this request</p>
                <p class="mt-0.5 text-xs text-gray-600">Please bring the <strong>original</strong> of each to the counter — staff will verify them. Attaching a copy below is optional.</p>
                <ul id="requirementsList" class="mt-3 space-y-3"></ul>
            </div>

            {{-- Facility reservations: reserve a place for a specific time window
                 on one day (e.g. covered court, 4:00 PM – 7:00 PM). --}}
            <div id="bookingSection" class="hidden rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                <p class="text-sm font-semibold text-emerald-900">Reservation details</p>
                <p class="mt-0.5 text-xs text-gray-600">You're reserving <strong id="bookingResource"></strong>. Pick the date and the time you need it — staff confirm availability, and clashing times are refused.</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label for="booking_date" class="mb-1 block text-xs font-semibold text-gray-700">Date</label>
                        <input id="booking_date" type="date" name="booking_date" value="{{ old('booking_date') }}" min="{{ now()->toDateString() }}" class="{{ $field }} @error('booking_date') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('booking_date')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="start_time" label="Start time" :value="old('start_time', '')" default="09:00" />
                        @error('start_time')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="end_time" label="End time" :value="old('end_time', '')" default="10:00" />
                        @error('end_time')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Equipment borrowing: how many units, and the borrow-to-return dates. --}}
            <div id="equipmentSection" class="hidden rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                <p class="text-sm font-semibold text-emerald-900">Borrowing details</p>
                <p class="mt-0.5 text-xs text-gray-600">You're borrowing <strong id="equipmentResource"></strong>. Tell us how many and when — staff confirm availability.</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label for="quantity" class="mb-1 block text-xs font-semibold text-gray-700">How many</label>
                        <input id="quantity" type="number" name="quantity" min="1" step="1" inputmode="numeric" value="{{ old('quantity') }}" placeholder="e.g. 50" class="{{ $field }} @error('quantity') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('quantity')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_date" class="mb-1 block text-xs font-semibold text-gray-700">Date needed</label>
                        <input id="needed_date" type="date" name="needed_date" value="{{ old('needed_date') }}" min="{{ now()->toDateString() }}" class="{{ $field }} @error('needed_date') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('needed_date')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="return_date" class="mb-1 block text-xs font-semibold text-gray-700">Return by</label>
                        <input id="return_date" type="date" name="return_date" value="{{ old('return_date') }}" min="{{ now()->toDateString() }}" class="{{ $field }} @error('return_date') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('return_date')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Service / production requests (e.g. lei making): how many to make,
                 and the date they're needed. No resource is reserved. --}}
            <div id="serviceSection" class="hidden rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                <p class="text-sm font-semibold text-emerald-900">Service details</p>
                <p class="mt-0.5 text-xs text-gray-600">Tell us how many you need and by when.</p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="service_quantity" class="mb-1 block text-xs font-semibold text-gray-700">How many</label>
                        <input id="service_quantity" type="number" name="quantity" min="1" step="1" inputmode="numeric" value="{{ old('quantity') }}" placeholder="e.g. 10" class="{{ $field }} @error('quantity') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('quantity')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_by" class="mb-1 block text-xs font-semibold text-gray-700">Date needed</label>
                        <input id="needed_by" type="date" name="needed_by" value="{{ old('needed_by') }}" min="{{ now()->toDateString() }}" class="{{ $field }} @error('needed_by') {{ $bad }} @else {{ $ok }} @enderror">
                        @error('needed_by')<p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            @php
                $typeMap = $requestTypes->mapWithKeys(fn ($t) => [$t->name => [
                    'kind' => $t->kind,
                    'resource' => $t->resource?->name,
                    'requirements' => $t->requirements->map(fn ($r) => ['id' => $r->id, 'label' => $r->label, 'mandatory' => (bool) $r->is_mandatory])->values(),
                ]]);
            @endphp
            <script>
                window.__types = @json($typeMap);
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

        // Branch the form on the selected type's kind: document types show a
        // requirement checklist (optional uploads); booking types show a
        // resource + date/time reservation block.
        (function () {
            const sel = document.getElementById('document_type');
            const reqSection = document.getElementById('requirementsSection');
            const reqList = document.getElementById('requirementsList');
            const bookingSection = document.getElementById('bookingSection');
            const bookingResource = document.getElementById('bookingResource');
            const equipmentSection = document.getElementById('equipmentSection');
            const equipmentResource = document.getElementById('equipmentResource');
            const serviceSection = document.getElementById('serviceSection');
            const types = window.__types || {};
            if (!sel || !reqSection || !reqList) { return; }

            const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

            // Only one scheduling panel is used at a time, and several share field
            // names (e.g. "quantity"). Disable inputs in the hidden panels so the
            // browser never submits stale values from another kind.
            const scheduleSections = [bookingSection, equipmentSection, serviceSection].filter(Boolean);
            const setSection = (section, active) => {
                section.classList.toggle('hidden', !active);
                section.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !active; });
            };

            function render() {
                const t = types[sel.value] || null;
                reqSection.classList.add('hidden');
                reqList.innerHTML = '';
                scheduleSections.forEach((s) => setSection(s, false));
                if (!t) { return; }

                // Facility / equipment / service types reveal their scheduling
                // panel. The requirements checklist below is shown for EVERY kind,
                // so we no longer return early here.
                if (t.kind === 'booking' && bookingSection) {
                    setSection(bookingSection, true);
                    if (bookingResource) { bookingResource.textContent = t.resource || 'this resource'; }
                } else if (t.kind === 'equipment' && equipmentSection) {
                    setSection(equipmentSection, true);
                    if (equipmentResource) { equipmentResource.textContent = t.resource || 'this item'; }
                } else if (t.kind === 'service' && serviceSection) {
                    setSection(serviceSection, true);
                }

                const reqs = t.requirements || [];
                if (!reqs.length) { return; }
                reqSection.classList.remove('hidden');
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
                    reqList.appendChild(li);
                });
            }

            sel.addEventListener('change', render);

            // Progressive enhancement: turn the single grouped select into a
            // two-step cascade — pick a category, then only that category's types
            // appear. Without JS the full grouped select above is used as-is.
            const cat = document.getElementById('request_category');
            const catWrap = document.getElementById('categoryWrap');
            const typesByCategory = @json($typesByCategory);

            if (cat && catWrap) {
                catWrap.classList.remove('hidden');

                const fillTypes = (kind, selected) => {
                    sel.innerHTML = '';
                    sel.add(new Option('Select a type…', ''));
                    (typesByCategory[kind] || []).forEach((name) => {
                        const opt = new Option(name, name);
                        if (name === selected) { opt.selected = true; }
                        sel.add(opt);
                    });
                };

                cat.addEventListener('change', () => { fillTypes(cat.value, ''); render(); });

                // Re-hydrate both steps after a validation error, else start clean.
                const oldType = @json(old('document_type'));
                const oldKind = oldType
                    ? Object.keys(typesByCategory).find((k) => typesByCategory[k].includes(oldType))
                    : null;

                if (oldKind) {
                    cat.value = oldKind;
                    fillTypes(oldKind, oldType);
                } else {
                    sel.innerHTML = '';
                    sel.add(new Option('Select a category first…', ''));
                }
            }

            render(); // handle old() repopulation after a validation error
        })();
    </script>
</body>
</html>
