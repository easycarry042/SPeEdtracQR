<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit a Request — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
{{-- White paper under the arrow doodle at 15%, the same backdrop the rest of
     the citizen pages use. Fixed, so the pattern holds still while this long
     form scrolls. --}}
<body class="relative min-h-screen bg-white antialiased text-ink">
    <div class="pointer-events-none fixed inset-0 -z-10 bg-cover bg-center opacity-15"
         style="background-image: url('{{ asset('images/landing/doodle-pattern.jpg') }}');"></div>

    {{-- Same white portal bar as /citizen and /track. Not sticky: this form is
         long enough that fields would scroll through the wordmark. --}}
    @include('layouts.partials.portal-header', ['sticky' => false])

    <main class="mx-auto w-full max-w-[1364px] px-4 pb-16 pt-[70px] sm:px-6">

        {{-- Title block: icon, heading, one line of orientation. --}}
        <div class="flex items-start gap-4 sm:gap-[35px]">
            <img src="{{ asset('images/icon-submit-request.svg') }}" alt=""
                 class="h-14 w-14 shrink-0 sm:h-20 sm:w-20" aria-hidden="true">
            <div class="min-w-0">
                <h1 class="font-display text-[26px] font-black tracking-tight text-[#07491b] sm:text-[30px]">Submit a request</h1>
                <p class="mt-1 max-w-[1100px] text-[17px] font-medium text-black sm:mt-2 sm:text-[22px]">
                    Fill in the form below instead of going to the municipality. You'll get a tracking number to follow your request.
                </p>
            </div>
        </div>

        {{-- Without JavaScript this static box is the whole report. With it, the
             script below lifts these messages into the pop-up alert and hides
             the box, so the problem arrives in front of the citizen instead of
             waiting quietly somewhere on a long form. --}}
        @if($errors->any())
            <div id="serverErrorSummary" class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <p class="font-semibold">Please fix the highlighted {{ $errors->count() === 1 ? 'field' : 'fields' }}:</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Two columns, as drawn: what you are asking for on the left, who is
             asking on the right. They stack on narrow screens. --}}
        <form method="POST" action="{{ route('public.request.store') }}" enctype="multipart/form-data"
              class="req-shell mt-[30px] p-6 sm:p-[50px]" novalidate>
            @csrf

            {{-- Honeypot: must stay empty. Hidden from real users, and kept
                 OUTSIDE the columns below — as the first child of a space-y
                 stack it took the first slot and pushed "Request category" a
                 gap lower than "Name" beside it, so the two columns started at
                 different heights. --}}
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid grid-cols-1 gap-x-[74px] gap-y-[30px] lg:grid-cols-2">
            <div class="space-y-[30px]">

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
                @error('document_type')<p id="document_type-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Shortcuts to the handful of types citizens file most, so the
                 common errands never need the dropdown. Each one selects the
                 type above (and fires its change handler) rather than
                 submitting, so the requirements/scheduling panels still appear. --}}
            @if($popularTypes->isNotEmpty())
                <div>
                    <p class="req-label">Mostly Requested</p>
                    <div class="mt-3 flex flex-wrap gap-[18px]">
                        @foreach($popularTypes as $popular)
                            <button type="button" class="req-chip" data-pick-type="{{ $popular->name }}">
                                <svg class="h-[22px] w-[22px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="#01721a" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/>
                                </svg>
                                {{ $popular->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

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
                        @error('booking_date')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="start_time" label="Start time" :value="old('start_time', '')" default="09:00" />
                        @error('start_time')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <x-time-clock name="end_time" label="End time" :value="old('end_time', '')" default="10:00" />
                        @error('end_time')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
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
                        @error('quantity')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_date" class="req-label text-sm">Date needed</label>
                        <input id="needed_date" type="date" name="needed_date" value="{{ old('needed_date') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('needed_date') is-invalid @enderror">
                        @error('needed_date')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="return_date" class="req-label text-sm">Return by</label>
                        <input id="return_date" type="date" name="return_date" value="{{ old('return_date') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('return_date') is-invalid @enderror">
                        @error('return_date')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
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
                        @error('quantity')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="needed_by" class="req-label text-sm">Date needed</label>
                        <input id="needed_by" type="date" name="needed_by" value="{{ old('needed_by') }}" min="{{ now()->toDateString() }}" class="req-field mt-2 @error('needed_by') is-invalid @enderror">
                        @error('needed_by')<p data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
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
                <div class="req-label-row">
                    <label for="purpose" class="req-label">Purpose</label>
                    <span class="req-count" data-count-for="purpose" aria-hidden="true"></span>
                </div>
                <input id="purpose" name="purpose" value="{{ old('purpose') }}" maxlength="255" placeholder="What is this request for?" data-counter aria-invalid="@error('purpose')true @else false @enderror" @error('purpose') aria-describedby="purpose-err" @enderror class="req-field mt-2 @error('purpose') is-invalid @enderror">
                <p class="mt-1.5 text-xs text-gray-600">What the document is for — e.g. “business permit renewal.”</p>
                @error('purpose')<p id="purpose-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="req-label">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="5000" placeholder="Anything else the office should know…" aria-invalid="@error('description')true @else false @enderror" @error('description') aria-describedby="description-err" @enderror class="req-field mt-2 @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')<p id="description-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            </div>{{-- /left column --}}

            <div class="space-y-[30px]">

            <div>
                <div class="req-label-row">
                    <label for="citizen_name" class="req-label">Name <span class="req-star">*</span></label>
                    <span class="req-count" data-count-for="citizen_name" aria-hidden="true"></span>
                </div>
                <input id="citizen_name" name="citizen_name" value="{{ old('citizen_name') }}" required autocomplete="name" maxlength="255" placeholder="e.g. Juan Cruz" data-counter aria-invalid="@error('citizen_name')true @else false @enderror" @error('citizen_name') aria-describedby="citizen_name-err" @enderror class="req-field mt-2 @error('citizen_name') is-invalid @enderror">
                @error('citizen_name')<p id="citizen_name-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="req-label-row">
                    <label for="citizen_email" class="req-label">Email <span class="req-star">*</span></label>
                    <span class="req-count" data-count-for="citizen_email" aria-hidden="true"></span>
                </div>
                <input id="citizen_email" type="email" name="citizen_email" value="{{ old('citizen_email') }}" required autocomplete="email" inputmode="email" maxlength="255" placeholder="Your email..." data-counter aria-invalid="@error('citizen_email')true @else false @enderror" aria-describedby="citizen_email-hint @error('citizen_email') citizen_email-err @enderror" class="req-field mt-2 @error('citizen_email') is-invalid @enderror">
                <p id="citizen_email-hint" class="mt-1.5 text-xs text-gray-600">We'll send your tracking link here.</p>
                @error('citizen_email')<p id="citizen_email-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="req-label-row">
                    <label for="citizen_contact" class="req-label">Contact No.</label>
                    <span class="req-count" data-count-for="citizen_contact" aria-hidden="true"></span>
                </div>
                {{-- The number is exactly 11 digits (09XXXXXXXXX), so the field
                     ENFORCES that rather than only describing it: maxlength
                     refuses a 12th digit, data-digits-only drops letters and
                     symbols as they are typed, and the counter above shows how
                     far along the number is (Norman: physical constraints beat
                     instructions). The old "+63" affix was removed — it
                     contradicted the 11-digit national form we store, so the
                     field looked full at 10 digits. --}}
                <input id="citizen_contact" type="tel" name="citizen_contact" value="{{ old('citizen_contact') }}" autocomplete="tel" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" placeholder="09123456789" data-counter data-digits-only aria-invalid="@error('citizen_contact')true @else false @enderror" aria-describedby="citizen_contact-hint @error('citizen_contact') citizen_contact-err @enderror" class="req-field mt-2 @error('citizen_contact') is-invalid @enderror">
                <p id="citizen_contact-hint" class="mt-1.5 text-xs text-gray-600">11 digits, starting with 09 — e.g. 09123456789.</p>
                @error('citizen_contact')<p id="citizen_contact-err" data-field-error class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Privacy notice — RA 10173 consent, which the server requires. --}}
            <div class="req-note p-5">
                <p class="req-note-title text-center text-[20px] sm:text-[22px]">Privacy Notice</p>
                <label class="mt-3 flex items-start gap-4 text-left">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) class="req-check mt-0.5">
                    <span class="req-note-body text-[17px] leading-[22px] sm:text-[20px]">I agree that the information I provided will be collected and processed by the municipality solely to handle this request, in accordance with the Data Privacy Act of 2012 (RA 10173). Only the details needed to process and contact me about this request are collected.</span>
                </label>
            </div>

            <div class="flex justify-center pt-1">
                <button type="submit" class="req-submit w-full sm:w-[230px]">Submit Request</button>
            </div>

            </div>{{-- /right column --}}
            </div>{{-- /columns --}}
        </form>
    </main>

    {{-- The shared pop-up error report (window.ErrorAlert). --}}
    <x-error-alert />

    {{-- Client-side error PREVENTION: catch the required fields before the
         server round-trip and point the citizen straight at the problem.
         Progressive enhancement only — the form still posts and is fully
         re-validated server-side if JavaScript is unavailable. --}}
    <script>
        // Server-side errors: lift them out of the static summary into the same
        // pop-up, then hide the box so the report is not told twice.
        (function () {
            const summary = document.getElementById('serverErrorSummary');
            if (!summary) { return; }

            const messages = Array.from(summary.querySelectorAll('li')).map((li) => li.textContent.trim());
            if (!messages.length) { return; }

            summary.classList.add('hidden');

            // The per-field copies become screen-reader-only rather than being
            // dropped: they are what aria-describedby on each field points at.
            document.querySelectorAll('[data-field-error]').forEach((node) => node.classList.add('sr-only'));

            window.ErrorAlert.show(messages, document.querySelector('.req-field.is-invalid, [aria-invalid="true"]'));
        })();

        (function () {
            const form = document.querySelector('form[action="{{ route('public.request.store') }}"]');
            if (!form) { return; }

            form.addEventListener('submit', function (e) {
                const problems = [];
                const check = (el, ok, msg) => { if (!ok) { problems.push({ el, msg }); } };

                check(form.document_type, !!form.document_type.value, 'Please choose a request type.');
                check(form.citizen_name, form.citizen_name.value.trim().length > 0, 'Please enter your name.');
                const email = form.citizen_email.value.trim();
                check(form.citizen_email, email.length > 0 && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email), email.length ? 'Please enter a valid email address.' : 'Please enter your email.');
                check(form.consent, form.consent.checked, 'Please agree to the data privacy notice to submit.');

                // Contact number: optional, but if given it must be the exact
                // 11-digit national form. The message names the count entered
                // and what to do about it, mirroring App\Rules\ContactNumber so
                // the citizen reads the same explanation either side.
                const contact = form.citizen_contact.value.replace(/\D+/g, '');
                if (contact.length) {
                    let contactProblem = '';
                    const digitWord = (n) => `${n} digit${n === 1 ? '' : 's'}`;
                    if (contact.length > 11) {
                        contactProblem = `You entered ${digitWord(contact.length)}, but a mobile number is exactly 11 digits. Remove the extra ${digitWord(contact.length - 11)} — write it as 09123456789 (no +63 needed).`;
                    } else if (contact.length < 11) {
                        contactProblem = `You entered ${digitWord(contact.length)}, but a mobile number is exactly 11 digits. Add the missing ${digitWord(11 - contact.length)} — write it as 09123456789.`;
                    } else if (!contact.startsWith('09')) {
                        contactProblem = 'A Philippine mobile number starts with 09, e.g. 09123456789. Please check the first two digits.';
                    }
                    check(form.citizen_contact, !contactProblem, contactProblem);
                }

                if (!problems.length) {
                    window.ErrorAlert.hide();

                    return;
                }

                e.preventDefault();

                // The field keeps its tint and aria-invalid so it can still be
                // identified once the alert is dismissed; the explanation itself
                // now lives in the pop-up.
                problems.forEach(({ el }) => {
                    const surface = el.closest('.req-affix') || el;
                    if (el.type !== 'checkbox') { surface.classList.add('is-invalid'); }
                    el.setAttribute('aria-invalid', 'true');
                });

                window.ErrorAlert.show(problems.map(({ msg }) => msg), problems[0].el);
            });

            // Editing a tinted field clears its own mark, so the form stops
            // accusing a field the citizen has already dealt with.
            form.addEventListener('input', (e) => {
                const el = e.target;
                if (!el.getAttribute || el.getAttribute('aria-invalid') !== 'true') { return; }
                el.setAttribute('aria-invalid', 'false');
                (el.closest('.req-affix') || el).classList.remove('is-invalid');
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

        // Error PREVENTION on the phone field: a number can only be digits, so
        // anything else is dropped as it is typed (and on paste) instead of
        // being accepted and rejected later by the server. The caret is kept
        // where it was, otherwise editing the middle of a number jumps to the end.
        (function () {
            document.querySelectorAll('[data-digits-only]').forEach((el) => {
                const clean = () => {
                    const caret = el.selectionStart;
                    const before = el.value;
                    const after = before.replace(/\D+/g, '');
                    if (after === before) { return; }
                    el.value = after;
                    const removed = before.slice(0, caret).replace(/\D+/g, '').length;
                    el.setSelectionRange(removed, removed);
                    el.dispatchEvent(new Event('input', { bubbles: false }));
                };
                el.addEventListener('input', clean);
                el.addEventListener('paste', () => setTimeout(clean, 0));
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

            // Declared out here so the "Mostly Requested" chips below can refill
            // the narrowed type list before selecting their own type.
            let fillTypes = null;

            if (cat && catWrap) {
                catWrap.classList.remove('hidden');

                fillTypes = (kind, selected) => {
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

            // "Mostly Requested" chips drive the same select, so every downstream
            // panel (requirements, reservation, borrowing) reacts exactly as it
            // would to a manual pick. The cascade above may have replaced the
            // option list, so the chip re-adds its type when it is missing.
            const chips = document.querySelectorAll('[data-pick-type]');

            const syncChips = () => {
                chips.forEach((chip) => {
                    chip.setAttribute('aria-pressed', String(chip.dataset.pickType === sel.value));
                });
            };

            chips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const name = chip.dataset.pickType;

                    if (cat && catWrap && fillTypes) {
                        const kind = Object.keys(typesByCategory)
                            .find((k) => typesByCategory[k].includes(name));

                        if (kind) {
                            cat.value = kind;
                            fillTypes(kind, name);
                        }
                    }

                    sel.value = name;
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    syncChips();
                });
            });

            sel.addEventListener('change', syncChips);

            render(); // handle old() repopulation after a validation error
            syncChips();
        })();
    </script>
</body>
</html>
