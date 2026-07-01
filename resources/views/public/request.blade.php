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
        <h1 class="text-3xl font-extrabold tracking-tight text-emerald-950">Submit a request online</h1>
        <p class="mt-2 text-sm text-gray-600">Fill in the form below instead of going to the municipality. You'll get a tracking number to follow your request.</p>

        @if($errors->any())
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-inside list-disc space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.request.store') }}" enctype="multipart/form-data" class="mt-6 space-y-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf

            {{-- Honeypot: must stay empty. Hidden from real users. --}}
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Request type <span class="text-red-500">*</span></label>
                <select name="document_type" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    <option value="">Select type…</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(old('document_type') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Purpose</label>
                <input name="purpose" value="{{ old('purpose') }}" maxlength="255" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
                <textarea name="description" rows="3" maxlength="5000" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Your name <span class="text-red-500">*</span></label>
                    <input name="citizen_name" value="{{ old('citizen_name') }}" required maxlength="255" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="citizen_email" value="{{ old('citizen_email') }}" required maxlength="255" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Contact number</label>
                <input name="citizen_contact" value="{{ old('citizen_contact') }}" maxlength="255" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Supporting files (optional — images, PDF or Word, up to 5)</label>
                <input type="file" name="attachments[]" accept="image/*,.pdf,.doc,.docx" multiple class="w-full text-sm text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800">
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
