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
                <label for="attachments" class="mb-1 block text-sm font-semibold text-gray-700">Supporting files (optional — images, PDF or Word, up to 5)</label>
                <input id="attachments" type="file" name="attachments[]" accept="image/*,.pdf,.doc,.docx" multiple aria-describedby="attachments-hint @error('attachments.*') attachments-err @enderror" class="w-full text-sm text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800">
                <p id="attachments-hint" class="mt-1 text-xs text-gray-500">Accepted: JPG, PNG, WEBP, PDF, or DOC/DOCX — max 10 MB each.</p>
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
</body>
</html>
