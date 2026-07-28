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

        {{-- Nav actions --}}
        <div class="flex items-center gap-1.5 sm:gap-3">
            @unless(request()->routeIs('citizen.dashboard'))
                {{-- Real browser back when there is history; portal home otherwise. --}}
                <a href="{{ route('citizen.dashboard') }}"
                   onclick="if (document.referrer && history.length > 1) { event.preventDefault(); history.back(); }"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18"/>
                    </svg>
                    Back
                </a>
            @endunless
            @unless(request()->routeIs('citizen.track'))
                <a href="{{ route('citizen.track') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 4l13 8-13 8V4z"/>
                    </svg>
                    <span class="hidden sm:inline">Track Document</span>
                </a>
            @endunless
            <a href="{{ route('login') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 transition">
                Staff Login
            </a>
        </div>
    </div>
</header>
