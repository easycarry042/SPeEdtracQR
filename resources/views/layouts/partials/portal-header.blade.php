{{-- Citizen portal top bar (Figma: CITIZEN PORTAL / TRACK / REQS FORM).
     A plain white bar with a soft drop shadow carrying the brand lockup — the
     public pages sit on the arrow doodle, so the bar needs its own surface to
     keep the wordmark legible. --}}
@php
    // Long forms collide with a bar that never leaves; those pages pass
    // `sticky: false` and it scrolls away instead.
    $sticky ??= true;
@endphp
<header @class(['z-40', 'sticky top-0' => $sticky, 'relative' => ! $sticky])>
    <div class="flex h-[74px] items-center justify-between bg-white px-5 shadow-[0_8px_24px_-18px_rgba(0,0,0,0.45)] sm:h-[104px] sm:px-[63px]">
        <a href="{{ route('citizen.dashboard') }}" class="group flex items-center gap-[13px]">
            <img src="{{ asset('images/landing/speed-icon.png') }}" alt=""
                 class="h-[44px] w-[44px] shrink-0 object-contain sm:h-[57px] sm:w-[57px]">
            <span class="text-[22px] leading-none sm:text-[30px]">
                <span class="font-display font-black text-[#004004]">SPeED</span>
                <span class="font-sans font-bold text-[#017209]">TraQR</span>
            </span>
        </a>

        {{-- One wayfinding control. The design leaves this side of the bar empty,
             but a public page with no way back strands the visitor, so it stays —
             quiet enough not to compete with the lockup. --}}
        <div class="flex items-center gap-1.5 sm:gap-3">
            @if(request()->routeIs('citizen.dashboard'))
                <a href="{{ route('welcome') }}"
                   class="inline-flex items-center gap-1.5 rounded-full border border-[rgba(0,64,4,0.35)] px-4 py-2 text-[15px] font-bold text-[#01721a] transition hover:bg-[rgba(141,255,60,0.2)]">
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
                   class="inline-flex items-center gap-1.5 rounded-full border border-[rgba(0,64,4,0.35)] px-4 py-2 text-[15px] font-bold text-[#01721a] transition hover:bg-[rgba(141,255,60,0.2)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7 7-7M3 12h18"/>
                    </svg>
                    Back
                </a>
            @endif
        </div>
    </div>
</header>
