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
                        <span class="font-display">SPeED</span>
                        <span class="font-sans font-bold text-emerald-700">TraQR</span>
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

                {{-- Staff get a quiet text link; citizens get the one primary button. --}}
                <p class="mt-5 text-[15px] text-emerald-700">
                    Are you a municipal staff?
                    <a href="{{ route('login') }}" class="font-semibold underline underline-offset-2 transition hover:text-emerald-900">Click here</a>
                </p>

                <div class="mt-6">
                    <a href="{{ route('citizen.dashboard') }}"
                       class="inline-flex items-center justify-center gap-2.5 rounded-full bg-emerald-600 px-8 py-4 text-base font-semibold text-white shadow-[0_0_28px_rgba(16,185,129,0.55)] ring-4 ring-white/70 transition hover:bg-emerald-700 hover:shadow-[0_0_36px_rgba(16,185,129,0.7)] active:scale-95">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/>
                        </svg>
                        Citizen Portal
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works — one copy of the arrow doodle scaled to cover the band, so
         no tile seams show, washed back so the cards and copy stay readable. --}}
    <section class="relative isolate overflow-hidden bg-[#f1f2f1] py-20">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-cover bg-center bg-no-repeat opacity-[0.18]"
             style="background-image: url('{{ asset('images/doodle-bg.png') }}');"></div>

        <div class="mx-auto max-w-6xl px-6">
            <div class="text-center">
                <span class="block text-5xl font-extrabold leading-none text-emerald-700" aria-hidden="true">?</span>
                <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-emerald-900">How It Works</h2>
                <p class="mx-auto mt-3 max-w-xl text-gray-500">Every request gets a unique QR-coded tracking number. As staff process it, each status change is timestamped and recorded so you can see every move.</p>
            </div>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="rounded-3xl bg-emerald-100/80 p-8 text-center shadow-sm ring-1 ring-emerald-200/60">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-200/70 text-emerald-800">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5m-9.5 1.5L19 5a1.914 1.914 0 00-2.707-2.707L7.5 11.086V16.5H13z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-extrabold text-emerald-900">Submit</h3>
                    <p class="mt-2 text-sm text-emerald-950/70">File your request online with your supporting documents attached. You get a QR-coded tracking number right away.</p>
                </div>

                <div class="rounded-3xl bg-emerald-100/80 p-8 text-center shadow-sm ring-1 ring-emerald-200/60">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-200/70 text-emerald-800">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8V5a2 2 0 012-2h3m8 0h3a2 2 0 012 2v3m0 8v3a2 2 0 01-2 2h-3m-8 0H5a2 2 0 01-2-2v-3"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-extrabold text-emerald-900">Assigned and Processed</h3>
                    <p class="mt-2 text-sm text-emerald-950/70">Your request is assigned to a staff member who takes it through each stage, from In Progress to In Review to Approved to Completed. Every change is timestamped.</p>
                </div>

                <div class="rounded-3xl bg-emerald-100/80 p-8 text-center shadow-sm ring-1 ring-emerald-200/60">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-200/70 text-emerald-800">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 text-lg font-extrabold text-emerald-900">Track anytime</h3>
                    <p class="mt-2 text-sm text-emerald-950/70">Scan your QR code or enter your tracking number to see the current stage, who is handling it, and the full history.</p>
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
