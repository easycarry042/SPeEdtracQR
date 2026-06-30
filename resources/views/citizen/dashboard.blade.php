<x-citizen-layout>
    <x-slot name="title">Citizen Portal</x-slot>

    {{-- Hero / Welcome --}}
    <div class="mb-10 text-center">
        <h1 class="text-3xl font-extrabold tracking-tight text-emerald-950 sm:text-4xl">
            Welcome to the Citizen Portal
        </h1>
        <p class="mt-3 text-lg text-gray-600">
            How can we help you today?
        </p>
    </div>

    {{-- Option Cards --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 max-w-3xl mx-auto">

        {{-- ── Card 1: Track a Document ──────────────────────────────────────── --}}
        <a href="{{ route('citizen.track') }}"
           class="group relative flex flex-col items-center gap-5 rounded-2xl border-2 border-emerald-200 bg-white p-8 text-center shadow-sm transition hover:border-emerald-400 hover:shadow-lg hover:-translate-y-1 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-400">

            {{-- Icon --}}
            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-500 group-hover:text-white">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                    <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>

            <div>
                <h2 class="text-xl font-bold text-emerald-950 group-hover:text-emerald-700 transition">
                    Track a Document
                </h2>
                <p class="mt-2 text-sm text-gray-500">
                    Enter your tracking ID or scan a QR code to check the status and location of your document.
                </p>
            </div>

            <span class="mt-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition group-hover:bg-emerald-600">
                Track Now
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        </a>

        {{-- ── Card 2: Submit a Request ──────────────────────────────────────── --}}
        <a href="{{ route('public.request.create') }}"
           class="group relative flex flex-col items-center gap-5 rounded-2xl border-2 border-emerald-200 bg-white p-8 text-center shadow-sm transition hover:border-emerald-400 hover:shadow-lg hover:-translate-y-1 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-400">

            {{-- Icon --}}
            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 transition group-hover:bg-emerald-500 group-hover:text-white">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-3-3v6M5 3h9l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM14 3v5h5"/>
                </svg>
            </span>

            <div>
                <h2 class="text-xl font-bold text-emerald-950 group-hover:text-emerald-700 transition">
                    Submit a Request
                </h2>
                <p class="mt-2 text-sm text-gray-500">
                    Create and submit a new request online — get a tracking number and QR code by email.
                </p>
            </div>

            <span class="mt-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-500 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition group-hover:bg-emerald-600">
                Submit Now
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        </a>

    </div>
</x-citizen-layout>
