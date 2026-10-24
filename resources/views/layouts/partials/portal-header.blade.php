{{-- The one header every citizen-facing page wears: the landing page, the
     request form and its receipt, the track lookup, the tracking result and the
     privacy notice. It used to be three different bars, so moving from the
     landing page into the form felt like leaving the site.

     Owner first: the City seal leads, so a visitor knows whose service this is
     before they read the product name. Sticky on a frosted white surface, so
     it stays legible over anything scrolling beneath it.

     The landing page passes `landing: true` for its section links and the
     staff sign-in; every other page gets one way home instead. --}}
@php
    $landing ??= false;
    $homeUrl = \App\Support\CitizenView::homeUrl();
    $navLink = 'inline-flex h-11 touch-manipulation items-center rounded-full px-3.5 text-[15px] font-semibold text-[#3a4a3f] transition-colors hover:bg-[#eef8ea] hover:text-[#004004] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#01721a]';
@endphp
<header class="sticky top-0 z-50 border-b border-[rgba(0,64,4,0.08)] bg-white/90 backdrop-blur-md">
    <div class="mx-auto flex h-16 max-w-[1209.5px] items-center justify-between gap-4 px-4 sm:h-[72px] sm:px-6 lg:px-8">
        <a href="{{ $landing ? '#top' : $homeUrl }}" class="flex min-w-0 items-center gap-2.5 rounded-xl focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-4 focus-visible:outline-[#01721a] sm:gap-3">
            <img src="{{ asset('images/staff/seal-2.png') }}" alt="City of San Pedro seal"
                 width="44" height="44" class="h-10 w-10 shrink-0 sm:h-11 sm:w-11">
            <span class="hidden h-9 w-px shrink-0 bg-[rgba(0,64,4,0.15)] sm:block" aria-hidden="true"></span>
            <img src="{{ asset('images/landing/speed-icon.png') }}" alt=""
                 width="36" height="36" class="hidden h-9 w-9 shrink-0 object-contain sm:block">
            <span class="flex min-w-0 flex-col">
                <span class="text-[19px] leading-none sm:text-[22px]" translate="no">
                    <span class="font-display font-black text-[#004004]">SPeED</span>
                    <span class="font-sans font-bold text-[#017209]">TraQR</span>
                </span>
                <span class="mt-1 truncate text-[12px] font-semibold leading-none text-[#4b5a50] sm:text-[13px]">City of San Pedro, Laguna</span>
            </span>
        </a>

        @if($landing)
            <nav aria-label="Primary" class="flex shrink-0 items-center gap-1">
                {{-- Section links only fit from tablet up; below that the page is
                     one short scroll and the hero already holds both actions. --}}
                <ul class="hidden items-center gap-0.5 md:flex">
                    <li><a href="#how-it-works" class="{{ $navLink }}">How it works</a></li>
                    <li><a href="#features" class="{{ $navLink }}">Features</a></li>
                    <li><a href="#faq" class="{{ $navLink }}">FAQ</a></li>
                </ul>
                <span class="mx-2 hidden h-6 w-px bg-[rgba(0,64,4,0.15)] md:block" aria-hidden="true"></span>
                {{-- Staff are the minority audience: a quiet outlined link, never
                     a filled button competing with the citizen actions. --}}
                <a href="{{ route('login') }}" class="cz-btn cz-btn-secondary cz-btn-sm">Staff sign in</a>
            </nav>
        @else
            {{-- One way out, to the landing page — the citizen's home. Never
                 history.back(): browser history could return the visitor
                 anywhere (a search engine, the staff login, an unrelated tab).
                 Citizen-flagged, so a staff login on a shared counter PC cannot
                 divert it to the staff desk. --}}
            <a href="{{ $homeUrl }}" class="cz-btn cz-btn-secondary cz-btn-sm shrink-0">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-8 9 8M5 10v10h14V10"/>
                </svg>
                <span class="hidden sm:inline">Back to Homepage</span>
                <span class="sm:hidden">Home</span>
            </a>
        @endif
    </div>
</header>
