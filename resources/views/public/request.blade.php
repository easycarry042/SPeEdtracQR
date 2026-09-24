<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit a Request — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
{{-- The design's two "welcome light" glows over the arrow doodle. They're
     radial gradients rather than the 1156px SVGs from the file: at that size
     the SVGs are pure decoration, and gradients scale to any viewport for
     free. Fixed, so the glows stay put as the long form scrolls. --}}
<body class="relative min-h-screen antialiased text-gray-900"
      style="background-color: var(--page-wash-base);
             background-image:
                 radial-gradient(62% 55% at 10% -6%, rgba(141, 255, 60, .26), rgba(141, 255, 60, 0) 68%),
                 radial-gradient(58% 52% at 82% -10%, rgba(1, 114, 26, .20), rgba(1, 114, 26, 0) 68%),
                 var(--page-wash-veil),
                 url('{{ asset('images/doodle-bg.png') }}');
             background-size: cover, cover, cover, cover;
             background-position: center, center, center, center;
             background-attachment: fixed, fixed, fixed, fixed;
             background-repeat: no-repeat, no-repeat, no-repeat, no-repeat;">

    {{-- Same public portal header as /citizen and /track. The wash is pale at
         the top here, so the brand keeps its green mark. Not sticky: the bar is
         transparent, and this form is long enough that fields would scroll
         through the wordmark. --}}
    @include('layouts.partials.public-header', ['sticky' => false])

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">

        {{-- Title block: icon, heading, one line of orientation. --}}
        <div class="flex items-start gap-4 sm:gap-6">
            <img src="{{ asset('images/icon-submit-request.svg') }}" alt=""
                 class="h-14 w-14 shrink-0 sm:h-20 sm:w-20" aria-hidden="true">
            <div class="min-w-0">
                <h1 class="text-2xl font-black tracking-tight sm:text-3xl" style="color: #004004;">Submit a request</h1>
                <p class="mt-1 max-w-3xl text-sm font-medium text-gray-800 sm:mt-2 sm:text-lg">
                    Fill in the form below instead of going to the municipality. You'll get a tracking number to follow your request.
                </p>
            </div>
        </div>

        @if($errors->any())
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">Please fix the highlighted {{ $errors->count() === 1 ? 'field' : 'fields' }} below:</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.request.store') }}" enctype="multipart/form-data"
              class="req-shell mt-6 space-y-6 p-5 sm:mt-8 sm:space-y-7 sm:p-10" novalidate>
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
                <label for="request_category" class="req-label">Request category <span class="req-star">*</span></label>
                <select id="request_category" class="req-field req-select mt-2">
                    <option value="">Select a category…</option>
                    @foreach($groups as $kind => $groupLabel)
                        @if($groupedTypes->has($kind))
                            <option value="{{ $kind }}">{{ $groupLabel }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label for="document_type" class="req-label">Request Type <span class="req-star">*</span></label>
                <select id="document_type" name="document_type" required aria-invalid="@error('document_type')true @else false @enderror" @error('document_type') aria-describedby="document_type-err" @enderror class="req-field req-select mt-2">
                    <option value="">Select a request type…</option>
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
                @error('document_type')<p id="document_type-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Requirements for the chosen request type — populated by JS from the
                 catalog. Citizens bring the originals to the counter. --}}
            <div id="requirementsSection" class="req-note hidden p-4 sm:p-5">
                <p class="req-note-title text-sm sm:text-base">Requirements for this request</p>
                <p class="req-note-body mt-1 text-xs sm:text-sm">Please bring the <strong>original</strong> of each to the counter — staff will verify them. Attaching a copy below is optional.</p>
                <ul id="requirementsList" class="mt-3 space-y-3"></ul>
            </div>

            {{-- Facility reservations: reserve a place for a specific time window
                 on one day (e.g. covered court, 4:00 PM – 7:00 PM). --}}
            <div id="bookingSection" class="req-note hidden p-4 sm:p-5">
                <p class="req-note-title text-sm sm:text-base">Reservation details</p>
                <p class="req-note-body mt-1 text-xs sm:text-sm">You're reserving <strong id="bookingResource"></strong>. Pick the date and the time you need it — staff confirm availability, and clashing times are refused.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="booking_date" class="req-label text-sm">Date</label>
                        <input id="booking_date" type="date" name="booking_date" value="{{ old('booking_date') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('booking_date') is-invalid @enderror">
                        @error('booking_date')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="start_time" label="Start time" :value="old('start_time', '')" default="09:00" />
                        @error('start_time')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="end_time" label="End time" :value="old('end_time', '')" default="10:00" />
                        @error('end_time')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Equipment borrowing: how many units, and the borrow-to-return dates. --}}
            <div id="equipmentSection" class="req-note hidden p-4 sm:p-5">
                <p class="req-note-title text-sm sm:text-base">Borrowing details</p>
                <p class="req-note-body mt-1 text-xs sm:text-sm">You're borrowing <strong id="equipmentResource"></strong>. Tell us how many and when — staff confirm availability.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="quantity" class="req-label text-sm">How many</label>
                        <input id="quantity" type="number" name="quantity" min="1" step="1" inputmode="numeric" value="{{ old('quantity') }}" placeholder="e.g. 50" class="req-field mt-2 @error('quantity') is-invalid @enderror">
                        @error('quantity')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_date" class="req-label text-sm">Date needed</label>
                        <input id="needed_date" type="date" name="needed_date" value="{{ old('needed_date') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('needed_date') is-invalid @enderror">
                        @error('needed_date')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="return_date" class="req-label text-sm">Return by</label>
                        <input id="return_date" type="date" name="return_date" value="{{ old('return_date') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('return_date') is-invalid @enderror">
                        @error('return_date')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Service / production requests (e.g. lei making): how many to make,
                 and the date they're needed. No resource is reserved. --}}
            <div id="serviceSection" class="req-note hidden p-4 sm:p-5">
                <p class="req-note-title text-sm sm:text-base">Service details</p>
                <p class="req-note-body mt-1 text-xs sm:text-sm">Tell us how many you need and by when.</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="service_quantity" class="req-label text-sm">How many</label>
                        <input id="service_quantity" type="number" name="quantity" min="1" step="1" inputmode="numeric" value="{{ old('quantity') }}" placeholder="e.g. 10" class="req-field mt-2 @error('quantity') is-invalid @enderror">
                        @error('quantity')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_by" class="req-label text-sm">Date needed</label>
                        <input id="needed_by" type="date" name="needed_by" value="{{ old('needed_by') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('needed_by') is-invalid @enderror">
                        @error('needed_by')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
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
                <label for="purpose" class="req-label">Purpose</label>
                <div class="relative mt-2">
                    <input id="purpose" name="purpose" value="{{ old('purpose') }}" maxlength="255" placeholder="What is this request for?" data-counter aria-invalid="@error('purpose')true @else false @enderror" @error('purpose') aria-describedby="purpose-err" @enderror class="req-field req-counted @error('purpose') is-invalid @enderror">
                    <span class="req-count" data-count-for="purpose" aria-hidden="true"></span>
                </div>
                <p class="mt-1.5 text-xs text-gray-600">What the document is for — e.g. “business permit renewal.”</p>
                @error('purpose')<p id="purpose-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="req-label">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="5000" placeholder="Anything else the office should know…" aria-invalid="@error('description')true @else false @enderror" @error('description') aria-describedby="description-err" @enderror class="req-field mt-2 @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')<p id="description-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="citizen_name" class="req-label">Name <span class="req-star">*</span></label>
                <div class="relative mt-2">
                    <input id="citizen_name" name="citizen_name" value="{{ old('citizen_name') }}" required autocomplete="name" maxlength="255" placeholder="Your name..." data-counter aria-invalid="@error('citizen_name')true @else false @enderror" @error('citizen_name') aria-describedby="citizen_name-err" @enderror class="req-field req-counted @error('citizen_name') is-invalid @enderror">
                    <span class="req-count" data-count-for="citizen_name" aria-hidden="true"></span>
                </div>
                @error('citizen_name')<p id="citizen_name-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 sm:gap-5">
                <div>
                    <label for="citizen_email" class="req-label">Email <span class="req-star">*</span></label>
                    <div class="relative mt-2">
                        <input id="citizen_email" type="email" name="citizen_email" value="{{ old('citizen_email') }}" required autocomplete="email" inputmode="email" maxlength="255" placeholder="Your email..." data-counter aria-invalid="@error('citizen_email')true @else false @enderror" aria-describedby="citizen_email-hint @error('citizen_email') citizen_email-err @enderror" class="req-field req-counted @error('citizen_email') is-invalid @enderror">
                        <span class="req-count" data-count-for="citizen_email" aria-hidden="true"></span>
                    </div>
                    <p id="citizen_email-hint" class="mt-1.5 text-xs text-gray-600">We'll send your tracking link here.</p>
                    @error('citizen_email')<p id="citizen_email-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="citizen_contact" class="req-label">Contact No.</label>
                    {{-- +63 is a static affix, not part of the value: the field has
                         always stored whatever the citizen typed, and changing that
                         would rewrite how existing numbers read. --}}
                    <div class="req-field req-affix req-counted relative mt-2 @error('citizen_contact') is-invalid @enderror">
                        <span class="req-affix-label">+63</span>
                        <span class="req-affix-rule" aria-hidden="true"></span>
                        <input id="citizen_contact" type="tel" name="citizen_contact" value="{{ old('citizen_contact') }}" autocomplete="tel" inputmode="tel" maxlength="255" placeholder="9123456789" data-counter aria-invalid="@error('citizen_contact')true @else false @enderror" @error('citizen_contact') aria-describedby="citizen_contact-err" @enderror>
                        <span class="req-count" data-count-for="citizen_contact" aria-hidden="true"></span>
                    </div>
                    @error('citizen_contact')<p id="citizen_contact-err" class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Privacy notice — RA 10173 consent, which the server requires. --}}
            <div class="req-note p-5 text-center sm:p-8">
                <img src="{{ asset('images/icon-privacy-shield.svg') }}" alt=""
                     class="mx-auto h-12 w-12 sm:h-16 sm:w-16" aria-hidden="true">
                <p class="req-note-title mt-3 text-xl sm:text-3xl">Privacy Notice</p>
                <label class="mt-4 flex items-start gap-4 text-left sm:mt-6">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) class="req-check mt-0.5">
                    <span class="req-note-body text-sm sm:text-lg">I agree that the information I provide will be collected and processed by the municipality solely to handle this request, in accordance with the Data Privacy Act of 2012 (RA 10173). Only the details needed to process and contact me about this request are collected.</span>
                </label>
            </div>

            <div class="flex justify-center pt-1">
                <button type="submit" class="req-submit w-full sm:w-auto sm:min-w-[230px]">Submit Request</button>
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
                    // The contact row puts the surface on a wrapper, so the tint
                    // has to land there rather than on the bare input.
                    const surface = el.closest('.req-affix') || el;
                    if (el.type !== 'checkbox') { surface.classList.add('is-invalid'); }
                    const anchor = el.type === 'checkbox' ? el.closest('label') : (surface.closest('.relative') || surface);
                    const p = document.createElement('p');
                    p.dataset.clientErr = '1';
                    p.className = 'mt-1.5 text-xs font-medium text-red-600';
                    p.textContent = msg;
                    anchor.insertAdjacentElement('afterend', p);
                    if (i === 0) { el.focus(); anchor.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
                });
            });
        })();

        // Live character counters, as in the design. Each reads its own
        // maxlength, so the cap shown is always the cap enforced.
        (function () {
            document.querySelectorAll('[data-counter]').forEach((el) => {
                const out = document.querySelector('[data-count-for="' + el.id + '"]');
                const max = el.getAttribute('maxlength');
                if (!out || !max) { return; }
                const sync = () => { out.textContent = el.value.length + '/' + max; };
                el.addEventListener('input', sync);
                sync();
            });
        })();

        // Accepted upload formats come from App\Support\UploadRules, so the file
        // picker always matches what the server will accept.
        const ACCEPTED_UPLOADS = @json(\App\Support\UploadRules::accept());

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
                    li.className = 'rounded-2xl border border-white/60 bg-white/70 p-3';
                    li.innerHTML =
                        `<div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-gray-800">${esc(r.label)}</span>
                            <span class="shrink-0 text-[11px] font-semibold ${r.mandatory ? 'text-red-600' : 'text-gray-500'}">${r.mandatory ? 'Required' : 'Optional'}</span>
                        </div>
                        <input type="file" name="requirements[${r.id}]" accept="${ACCEPTED_UPLOADS}"
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
