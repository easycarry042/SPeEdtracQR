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

        {{-- ── Card 2: More Services (Coming Soon) ──────────────────────────── --}}
        <div x-data="{ modal: false }"
             @keydown.escape.window="modal = false"
             class="group relative flex flex-col items-center gap-5 rounded-2xl border-2 border-gray-200 bg-white p-8 text-center shadow-sm transition hover:border-gray-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer select-none"
             @click="modal = true"
             role="button"
             tabindex="0"
             @keydown.enter="modal = true">

            {{-- Coming Soon badge --}}
            <span class="absolute right-3 top-3 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                Coming Soon
            </span>

            {{-- Icon --}}
            <span class="flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition group-hover:bg-gray-200">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 6h16M4 10h16M4 14h10M4 18h6"/>
                </svg>
            </span>

            <div>
                <h2 class="text-xl font-bold text-gray-600">
                    More Services
                </h2>
                <p class="mt-2 text-sm text-gray-400">
                    Additional citizen services will be available here soon.
                </p>
            </div>

            <span class="mt-auto inline-flex items-center gap-1.5 rounded-full bg-gray-200 px-4 py-1.5 text-sm font-semibold text-gray-500">
                Coming Soon
            </span>

            {{-- Coming-Soon Modal --}}
            <div x-show="modal"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 @click.self="modal = false">
                <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl text-center" @click.stop>
                    <span class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-500">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                    </span>
                    <h3 class="text-xl font-bold text-gray-800">Coming Soon</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        We're working on additional services for citizens. Check back soon!
                    </p>
                    <button @click="modal = false"
                            class="mt-6 w-full rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                        Got it
                    </button>
                </div>
            </div>
        </div>

    </div>
</x-citizen-layout>
