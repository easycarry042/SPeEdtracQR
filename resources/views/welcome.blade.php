<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPeED TraQR — Document Tracking System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    {{-- No page header here: the hero's mark + wordmark lockup is the landing
         page's branding. Other layouts keep their own headers. --}}

    {{-- Hero --}}
    @php
        // Every photo present becomes a slide, in this order. Drop another file
        // at public/images/hero-image3.jpg and it joins the rotation — no code
        // change. A single photo simply renders as a still backdrop.
        $heroImages = collect([
            'hero-image', 'hero-image2', 'hero-image3',
            'municipality-hero',
        ])
            ->crossJoin(['jpg', 'png', 'webp'])
            ->map(fn (array $pair) => "{$pair[0]}.{$pair[1]}")
            ->filter(fn (string $f) => file_exists(public_path("images/{$f}")))
            ->map(fn (string $f) => asset("images/{$f}"))
            ->values();

        $slideSeconds = 6;   // time each photo holds
        $fadeSeconds = 1.6;  // cross-fade duration
    @endphp
    {{-- min-h-[100svh] so the hero fills the first screen without the mobile
         browser's collapsing toolbar pushing it past the fold. --}}
    <section class="relative isolate flex min-h-screen min-h-[100svh] items-center overflow-hidden bg-gradient-to-br from-[#eef4f0] via-[#f1f6f3] to-[#dfeee6]">
        {{-- Soft green shapes echoing the sweep behind the photo --}}
        <div class="pointer-events-none absolute -left-40 top-1/2 -z-10 h-[38rem] w-[38rem] -translate-y-1/2 rounded-full bg-emerald-200/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-56 left-1/4 -z-10 h-[34rem] w-[34rem] rounded-full bg-teal-200/25 blur-3xl"></div>

        {{-- City photos bleed in from the right and feather into the
             background, so the copy sits on clean light space instead of an
             overlay. Multiple photos cross-fade as a slideshow. --}}
        @if($heroImages->isNotEmpty())
            <div class="pointer-events-none absolute inset-y-0 right-0 -z-10 w-full opacity-25 lg:w-[60%] lg:opacity-100"
                 style="-webkit-mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,.55) 22%, #000 60%); mask-image: linear-gradient(to right, transparent 0%, rgba(0,0,0,.55) 22%, #000 60%);">
                @foreach($heroImages as $index => $image)
                    <img src="{{ $image }}" alt="{{ $index === 0 ? 'San Pedro City' : '' }}"
                         {{-- Every slide sits in the hero viewport, so none are lazy:
                              a not-yet-loaded slide would cross-fade to blank. --}}
                         @if($index === 0) fetchpriority="high" @else aria-hidden="true" @endif
                         class="hero-slide absolute inset-0 h-full w-full object-cover"
                         style="animation-delay: {{ $index * $slideSeconds - $heroImages->count() * $slideSeconds }}s">
                @endforeach

                {{-- Bottom fade blends the photo into the section below --}}
                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-[#dfeee6] to-transparent"></div>
            </div>

            @if($heroImages->count() > 1)
                @php
                    $cycle = $heroImages->count() * $slideSeconds;
                    $pct = fn (float $seconds): string => round($seconds / $cycle * 100, 3).'%';
                @endphp
                <style>
                    /* Each slide runs the same cycle, staggered by a negative delay,
                       so exactly one is at full opacity at any moment. */
                    .hero-slide {
                        opacity: 0;
                        animation: heroSlideFade {{ $cycle }}s linear infinite;
                    }
                    @keyframes heroSlideFade {
                        0%                                       { opacity: 1; }
                        {{ $pct($slideSeconds - $fadeSeconds) }} { opacity: 1; }
                        {{ $pct($slideSeconds) }}                { opacity: 0; }
                        {{ $pct($cycle - $fadeSeconds) }}        { opacity: 0; }
                        100%                                     { opacity: 1; }
                    }
                    /* Respect a reduced-motion preference: hold the first photo. */
                    @media (prefers-reduced-motion: reduce) {
                        .hero-slide { animation: none; opacity: 0; }
                        .hero-slide:first-child { opacity: 1; }
                    }
                </style>
            @endif
        @endif

        <div class="mx-auto w-full max-w-6xl px-6 py-20">
            <div class="max-w-xl">
                {{-- Mark + wordmark lockup above the headline. Sized well up from the
                     navbar's 36px, but kept below the 48px headline so it introduces
                     the page rather than competing with it. --}}
                <div class="mb-6 flex items-center gap-3">
                    <img src="{{ asset('images/icon.png') }}" alt=""
                         class="h-14 w-14 shrink-0 sm:h-16 sm:w-16">
                    <span class="text-2xl font-extrabold tracking-tight text-emerald-950 sm:text-3xl">
                        SPeED <span class="font-bold text-emerald-700">TraQR</span>
                    </span>
                </div>

                <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-emerald-950 sm:text-5xl">
                    Your requests,<br>
                    <span class="text-emerald-700">tracked in real time.</span>
                </h1>
                <p class="mt-5 max-w-lg text-lg text-gray-600">
                    Submit a request online, get a QR-coded tracking number, and follow
                    every update from filing to release anytime.
                </p>

                {{-- Two primary buttons --}}
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('citizen.dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 active:scale-95">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Citizen Portal
                    </a>
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white/80 px-6 py-3.5 text-sm font-semibold text-emerald-800 shadow-sm backdrop-blur-sm transition hover:border-emerald-300 hover:bg-white active:scale-95">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14M9 4H7a2 2 0 00-2 2v12a2 2 0 002 2h2"/>
                        </svg>
                        Staff Login
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white py-20">
        <div class="mx-auto max-w-6xl px-6">
            <h2 class="text-center text-3xl font-bold text-emerald-950">How it works</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-gray-500">Every request gets a unique QR-coded tracking number. As staff process it, each status change is timestamped and recorded so you can see every move.</p>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-200/80 bg-[#f8faf8] p-7 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Submit</h3>
                    <p class="mt-2 text-sm text-gray-500">File your request online with your supporting documents attached. You get a QR-coded tracking number right away.</p>
                </div>

                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-7 text-center ring-1 ring-emerald-200">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2M3 17v2a2 2 0 002 2h2m10 0h2a2 2 0 002-2v-2M9 12h.01M12 12h.01M15 12h.01"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Assigned and processed</h3>
                    <p class="mt-2 text-sm text-gray-500">Your request is assigned to a staff member who takes it through each stage, from In Progress to In Review to Approved to Completed. Every change is timestamped.</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-[#f8faf8] p-7 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Track anytime</h3>
                    <p class="mt-2 text-sm text-gray-500">Scan your QR code or enter your tracking number to see the current stage, who is handling it, and the full history.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-emerald-200/60 bg-[#f1f2f1] py-8">
        <div class="mx-auto max-w-6xl px-6 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} SPeED TraQR — Document Tracking System. All rights reserved.
        </div>
    </footer>

    @include('layouts.partials.bfcache-guard')

</body>
</html>
