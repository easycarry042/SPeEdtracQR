{{-- Public portal top bar — shared by the citizen layout and the guest view of
     app-layout pages (e.g. /track) so both have the same separation on top. --}}
<header class="sticky top-0 z-40 border-b border-emerald-200/60 bg-white/90 backdrop-blur-md shadow-sm">
    <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
        {{-- Brand --}}
        <a href="{{ route('citizen.dashboard') }}" class="flex items-center gap-3 group">
            <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-9 w-9 rounded-lg">
            <span class="text-lg font-extrabold tracking-tight text-emerald-950 group-hover:text-emerald-700 transition">
                SPeED <span class="text-emerald-600">TraQR</span>
            </span>
        </a>

        {{-- Nav actions — a single wayfinding control, always in a boxed button.
             Sub-pages step back one level; the portal home offers the way out to
             the public landing page. --}}
        <div class="flex items-center gap-1.5 sm:gap-3">
            @if(request()->routeIs('citizen.dashboard'))
                <a href="{{ route('welcome') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-8 9 8M5 10v10h14V10"/>
                    </svg>
                    <span class="hidden sm:inline">Back to Homepage</span>
                    <span class="sm:hidden">Home</span>
                </a>
            @else
                {{-- Always the citizen portal, never history.back(): browser history
                     could return the visitor anywhere (a search engine, the staff
                     login, an unrelated tab's page) instead of one level up. --}}
                <a href="{{ route('citizen.dashboard') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18"/>
                    </svg>
                    Back
                </a>
            @endif
        </div>
    </div>
</header>
