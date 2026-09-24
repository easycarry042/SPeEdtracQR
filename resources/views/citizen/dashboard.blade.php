<x-citizen-layout>
    <x-slot name="title">Citizen Portal</x-slot>

    {{-- Hero / Welcome --}}
    <div class="mb-12 text-center">
        {{-- Two-citizen mark: this portal is the public's door, not staff's. --}}
        <svg class="mx-auto h-16 w-16 text-emerald-800" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 48 48" aria-hidden="true">
            <circle cx="17" cy="13" r="5.5" fill="currentColor" stroke="none"/>
            <circle cx="32" cy="13" r="5.5" fill="currentColor" stroke="none"/>
            <path stroke-linecap="round" d="M4 31c0-5.5 5.5-9.5 13-9.5s13 4 13 9.5"/>
            <path stroke-linecap="round" d="M19 31c0-5.5 5.5-9.5 13-9.5s13 4 13 9.5"/>
        </svg>

        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-emerald-900 sm:text-4xl">
            Welcome to the Citizen Portal
        </h1>
        {{-- emerald-900, not -700: on a short viewport this line lands on the
             wash's mid-green band, where the lighter green drops under AA. --}}
        <p class="mt-2 text-xl text-emerald-900">
            How can we help you today?
        </p>
    </div>

    {{-- Option cards. Each one reads icon → what it is → the action → the detail,
         so the button is reachable without finishing the paragraph. --}}
    <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

        {{-- ── Card 1: Track a Document ──────────────────────────────────────── --}}
        <a href="{{ route('citizen.track') }}"
           class="portal-card group flex flex-col items-center rounded-2xl p-7 text-center transition hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500">

            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-800 text-white transition group-hover:bg-emerald-900">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8V5a2 2 0 012-2h3m8 0h3a2 2 0 012 2v3m0 8v3a2 2 0 01-2 2h-3m-8 0H5a2 2 0 01-2-2v-3"/>
                </svg>
            </span>

            <h2 class="mt-5 text-lg font-extrabold text-emerald-800">
                Track a Document
            </h2>

            <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-emerald-800 px-5 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:bg-emerald-900">
                Track Now
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>

            <p class="mt-5 text-sm text-emerald-950/70">
                Enter your tracking ID or scan a QR code to check the status and location of your document.
            </p>
        </a>

        {{-- ── Card 2: Submit a Request ──────────────────────────────────────── --}}
        <a href="{{ route('public.request.create') }}"
           class="portal-card group flex flex-col items-center rounded-2xl p-7 text-center transition hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500">

            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-800 text-white transition group-hover:bg-emerald-900">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-9.5 1.5L19 5a1.914 1.914 0 00-2.707-2.707L7.5 11.086V16.5H13z"/>
                </svg>
            </span>

            <h2 class="mt-5 text-lg font-extrabold text-emerald-800">
                Submit a Request
            </h2>

            <span class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-emerald-800 px-5 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:bg-emerald-900">
                Submit Now
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>

            <p class="mt-5 text-sm text-emerald-950/70">
                Create and submit a new request online, get a tracking number and QR code by email.
            </p>
        </a>

        {{-- ── Card 3: Speedy, the assistant ─────────────────────────────────────
             Speedy answers questions about one specific document, so this card
             routes through the tracking lookup — the assistant opens on the
             document's page once the citizen has identified it. --}}
        <a href="{{ route('citizen.track') }}"
           class="portal-card speedy-card group flex flex-col items-center rounded-2xl p-7 text-center transition hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-brass">

            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-800 text-white transition group-hover:bg-emerald-900">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m-6 2h12a2 2 0 012 2v7a2 2 0 01-2 2H6a2 2 0 01-2-2v-7a2 2 0 012-2zM2 12v3m20-3v3"/>
                    <circle cx="9.5" cy="13" r="1.2" fill="currentColor" stroke="none"/>
                    <circle cx="14.5" cy="13" r="1.2" fill="currentColor" stroke="none"/>
                </svg>
            </span>

            <h2 class="mt-5 text-lg font-extrabold text-emerald-800">
                Talk to our Chatbot <span class="speedy-text">Speedy</span>!
            </h2>

            <span class="speedy-btn mt-4 inline-flex items-center gap-1.5 rounded-full px-5 py-2 text-sm font-semibold text-white shadow-sm transition group-hover:brightness-110">
                Chat Speedy
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>

            <p class="mt-5 text-sm text-emerald-950/70">
                Pull up your document and ask Speedy anything about it, from what happens next to what to bring at pickup.
            </p>
        </a>

    </div>
</x-citizen-layout>
