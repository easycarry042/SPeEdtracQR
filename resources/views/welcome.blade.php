<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPeED TraQR — Document Tracking System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
<body class="min-h-screen bg-white antialiased text-ink">

    {{-- ─────────────────────────────────────────────────────────────────────
         Floating navigation pill. Per the design it is wider than the page and
         bleeds past both edges (‑20px each side of a 1512 frame), so it reads as
         a capsule laid over the hero rather than a bar attached to it.
         ───────────────────────────────────────────────────────────────────── --}}
    <header class="absolute inset-x-0 top-[4px] z-50 px-0">
        <nav class="-mx-5 flex h-[58px] w-[calc(100%+40px)] items-center justify-end rounded-[200px] bg-white px-[7%] shadow-[0_18px_39px_-29px_rgba(0,0,0,0.67)] sm:h-[72px]"
             aria-label="Primary">
            <ul class="flex items-center gap-7 text-[13px] font-bold sm:gap-[46px] sm:text-[16px]">
                {{-- The active item is the solid grey; the rest sit back at 72%
                     opacity, exactly as the design distinguishes them. --}}
                <li><a href="#top" class="text-[#969696] transition hover:text-[#01721a]">Home</a></li>
                <li><a href="#features" class="text-[rgba(109,109,109,0.72)] transition hover:text-[#01721a]">Features</a></li>
                <li><a href="#how-it-works" class="whitespace-nowrap text-[rgba(109,109,109,0.72)] transition hover:text-[#01721a]">How It Works</a></li>
                <li><a href="#security" class="text-[rgba(109,109,109,0.72)] transition hover:text-[#01721a]">Security</a></li>
                <li><a href="#faq" class="text-[rgba(109,109,109,0.72)] transition hover:text-[#01721a]">FAQ</a></li>
            </ul>
        </nav>
    </header>

    {{-- ── Hero ─────────────────────────────────────────────────────────────
         City Hall photo occupies everything right of 16.3% of the frame; the
         exported vignette feathers all four of its edges into white so the copy
         on the left sits on clean paper with no hard photo edge.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="top" class="relative isolate flex min-h-[100svh] items-center overflow-hidden bg-white">
        <div class="pointer-events-none absolute inset-y-0 left-0 right-0 -z-10 opacity-40 lg:left-[16.3%] lg:opacity-100">
            {{-- The photo sits 36px in from the vignette's left edge, as in the
                 design: the feather has to start before the photo does or its
                 raw edge shows as a hard vertical seam. The offset lives on this
                 wrapper, not the <img> — an image sized with `width:auto` takes
                 its natural aspect ratio instead of filling, which left the
                 photo ending mid-screen on a wide monitor. --}}
            {{-- 2.84% = the design's 36px inside a 1268px vignette. Kept as a
                 ratio, not a fixed 36px: the feather scales with the box, so a
                 fixed inset leaves the photo's raw edge outside it on a wide
                 monitor. --}}
            <div class="absolute inset-y-0 right-0 left-0 lg:left-[2.84%]">
                <img src="{{ asset('images/landing/hero-cityhall.jpg') }}"
                     alt="San Pedro City Hall, Laguna"
                     fetchpriority="high"
                     class="absolute inset-0 h-full w-full object-cover">
            </div>
            {{-- preserveAspectRatio is stripped by the export, so the SVG
                 stretches to the photo box and keeps the feather aligned. --}}
            <img src="{{ asset('images/landing/hero-gradient.svg') }}" alt="" aria-hidden="true"
                 class="absolute inset-0 h-full w-full">
        </div>

        <div class="relative mx-auto w-full max-w-[1512px] px-6 py-28 sm:px-10 lg:px-[6.4%]">
            {{-- Mark + wordmark lockup introduces the page above the headline. --}}
            <div class="flex items-center gap-[9px]">
                <img src="{{ asset('images/landing/speed-icon.png') }}" alt=""
                     class="h-[52px] w-[52px] shrink-0 object-contain sm:h-[70px] sm:w-[70px]">
                <p class="text-[24px] leading-none sm:text-[30px]">
                    <span class="font-display font-black text-[#004004]">SPeED</span>
                    <span class="font-sans font-bold text-[#017209]">TraQR</span>
                </p>
            </div>

            {{-- Two fixed lines, as drawn — the break is part of the design, so
                 neither line is allowed to re-wrap on its own. --}}
            <h1 class="mt-[34px] font-display text-[32px] font-black leading-[1.125] sm:text-[40px]">
                <span class="block whitespace-nowrap text-[#004004]">Your requests,</span>
                <span class="block whitespace-nowrap text-[#01721a]">tracked in real time.</span>
            </h1>

            <p class="mt-[28px] max-w-[459px] text-[17px] font-medium leading-snug text-black sm:text-[20px]">
                Submit a request online, get a QR-coded tracking number, and follow
                every update from filing to release anytime.
            </p>

            {{-- Staff get a quiet text link; citizens get the one primary button. --}}
            <p class="mt-[24px] text-[18px] font-medium text-[#01721a] sm:text-[22px]">
                Are you a municipal staff?
                <a href="{{ route('login') }}" class="font-bold underline underline-offset-2 transition hover:text-[#004004]">Click here</a>
            </p>

            {{-- Double ring: the filled capsule sits inside a 3px outline of the
                 same green, with the lime glow the design puts behind it. --}}
            <div class="mt-[30px]">
                <a href="{{ route('citizen.dashboard') }}"
                   class="group inline-flex rounded-[40px] p-[3px] ring-1 ring-[#01721a] transition active:scale-[.98]">
                    <span class="inline-flex h-[64px] w-[253px] items-center justify-center gap-[11px] rounded-[40px] bg-[#01721a] text-[20px] font-medium text-white shadow-[0_0_30px_5px_rgba(104,244,61,0.8)] transition group-hover:bg-[#004004]">
                        <img src="{{ asset('images/landing/icon-citizen.svg') }}" alt=""
                             class="h-[20px] w-[15px] shrink-0 brightness-0 invert">
                        Citizen Portal
                    </span>
                </a>
            </div>
        </div>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────────
         One copy of the arrow doodle scaled to cover the band at 15%, so no
         tile seams show and the cards stay readable over it.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="relative isolate overflow-hidden bg-[#f0f0f0] pb-[118px] pt-[56px]">
        <img src="{{ asset('images/landing/doodle-pattern.jpg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 -z-10 h-full w-full object-cover opacity-15">

        <div class="mx-auto max-w-[1512px] px-6 sm:px-10">
            <div class="text-center">
                <img src="{{ asset('images/landing/how-icon.svg') }}" alt="" aria-hidden="true"
                     class="mx-auto h-[72px] w-[45px] rotate-[2.58deg]">
                <h2 class="mt-[19px] text-[26px] font-black text-[#004004] sm:text-[30px]">How It Works</h2>
                <p class="mx-auto mt-[19px] max-w-[488px] text-[17px] font-medium leading-snug text-[#353535] sm:text-[20px]">
                    Every request gets a unique QR-coded tracking number. As staff process it,
                    each status change is timestamped and recorded so you can see every move.
                </p>
            </div>

            <div id="steps" class="mt-[110px] grid grid-cols-1 gap-[62px] sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'card-submit.svg', 'w' => 'w-[45px]', 'title' => 'Submit',
                     'body' => 'File your request online with your supporting documents attached. You get a QR-coded tracking number right away.'],
                    ['icon' => 'card-assigned.svg', 'w' => 'w-[45px]', 'title' => 'Assigned and Processed',
                     'body' => 'Your request is assigned to a staff member who takes it through each stage, from In Progress to In Review to Approved to Completed. Every change is timestamped.'],
                    ['icon' => 'card-clock.svg', 'w' => 'w-[46px]', 'title' => 'Track anytime',
                     'body' => 'Scan your QR code or enter your tracking number to see the current stage, who is handling it, and the full history.'],
                ] as $step)
                    <div class="flex min-h-[290px] flex-col items-center rounded-[30px] border-[0.5px] border-[rgba(0,64,4,0.5)] bg-[rgba(141,255,60,0.2)] px-[28px] pb-[24px] pt-[23px] text-center backdrop-blur-[10px]">
                        <div class="flex h-[75px] w-[75px] items-center justify-center rounded-[10px] bg-[rgba(139,212,144,0.3)]">
                            <img src="{{ asset('images/landing/'.$step['icon']) }}" alt=""
                                 class="h-[45px] {{ $step['w'] }}">
                        </div>
                        <h3 class="mt-[29px] text-[22px] font-black leading-tight text-[#245501] sm:text-[26px]">{{ $step['title'] }}</h3>
                        <p class="mt-[16px] text-[16px] font-medium leading-snug text-[#1e1e1e] sm:text-[18px]">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-black/5 bg-[#f0f0f0] py-8">
        <div class="mx-auto max-w-[1512px] px-6 text-center text-[15px] text-[#8a8a8a]">
            &copy; {{ date('Y') }} SPeED TraQR — Document Tracking System. All rights reserved.
        </div>
    </footer>

    @include('layouts.partials.bfcache-guard')

</body>
</html>
