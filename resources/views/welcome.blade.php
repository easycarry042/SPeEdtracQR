<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>SPeED TraQR — Document Tracking System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
<body class="min-h-screen bg-white antialiased text-ink">

    {{-- First tab stop: jumps the floating nav pill. --}}
    <a href="#main" class="skip-link">Skip to content</a>

    {{-- ─────────────────────────────────────────────────────────────────────
         Floating navigation pill. Per the design it is wider than the page and
         bleeds past both edges (‑20px each side of a 1512 frame), so it reads as
         a capsule laid over the hero rather than a bar attached to it.
         ───────────────────────────────────────────────────────────────────── --}}
    <header class="absolute inset-x-0 top-[4px] z-50 px-0">
        <nav class="-mx-5 flex h-[46.5px] w-[calc(100%+40px)] items-center justify-end rounded-[160px] bg-white px-[7%] shadow-[0_14.5px_31px_-23px_rgba(0,0,0,0.67)] sm:h-[57.5px]"
             aria-label="Primary">
            <ul class="flex items-center gap-7 text-[13px] font-bold sm:gap-[37px] sm:text-[14px]">
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

    <main id="main">

    {{-- ── Hero ─────────────────────────────────────────────────────────────
         City Hall photo occupies everything right of 16.3% of the frame; the
         exported vignette feathers all four of its edges into white so the copy
         on the left sits on clean paper with no hard photo edge.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="top" class="relative isolate flex min-h-[calc(100svh/var(--ui-zoom))] items-center overflow-hidden bg-white">
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

        <div class="relative mx-auto w-full max-w-[1209.5px] px-6 py-28 sm:px-10 lg:px-[6.4%]">
            {{-- Mark + wordmark lockup introduces the page above the headline. --}}
            <div class="flex items-center gap-[7px]">
                <img src="{{ asset('images/landing/speed-icon.png') }}" alt=""
                     class="h-[41.5px] w-[41.5px] shrink-0 object-contain sm:h-[56px] sm:w-[56px]">
                <p class="text-[19px] leading-none sm:text-[24px]">
                    <span class="font-display font-black text-[#004004]">SPeED</span>
                    <span class="font-sans font-bold text-[#017209]">TraQR</span>
                </p>
            </div>

            {{-- Two fixed lines, as drawn — the break is part of the design, so
                 neither line is allowed to re-wrap on its own. --}}
            <h1 class="mt-[27px] font-display text-[26px] font-black leading-[1.125] sm:text-[32px]">
                <span class="block whitespace-nowrap text-[#004004]">Your requests,</span>
                <span class="block whitespace-nowrap text-[#01721a]">tracked in real time.</span>
            </h1>

            <p class="mt-[22.5px] max-w-[367px] text-[14px] font-medium leading-snug text-black sm:text-[16px]">
                Submit a request online, get a QR-coded tracking number, and follow
                every update from filing to release anytime.
            </p>

            {{-- Staff get a quiet text link; citizens get the one primary button. --}}
            <p class="mt-[19px] text-[14px] font-medium text-[#01721a] sm:text-[18px]">
                Are you a municipal staff?
                <a href="{{ route('login') }}" class="font-bold underline underline-offset-2 transition hover:text-[#004004]">Click here</a>
            </p>

            {{-- Double ring: the filled capsule sits inside a 3px outline of the
                 same green, with the lime glow the design puts behind it. --}}
            <div class="mt-[24px]">
                <a href="{{ route('citizen.dashboard') }}"
                   class="group inline-flex rounded-[32px] p-[3px] ring-1 ring-[#01721a] transition active:scale-[.98]">
                    <span class="inline-flex h-[51px] w-[202.5px] items-center justify-center gap-[9px] rounded-[32px] bg-[#01721a] text-[16px] font-medium text-white shadow-[0_0_24px_5px_rgba(104,244,61,0.8)] transition group-hover:bg-[#004004]">
                        <img src="{{ asset('images/landing/icon-citizen.svg') }}" alt=""
                             class="h-[16px] w-[12px] shrink-0 brightness-0 invert">
                        Citizen Portal
                    </span>
                </a>
            </div>
        </div>
    </section>

<<<<<<< HEAD
    {{-- ── How it works ───────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="relative isolate overflow-hidden bg-[#f0f0f0] pb-[94.5px] pt-[45px]">

        <div class="mx-auto max-w-[1209.5px] px-6 sm:px-10">
            <div class="text-center">
                <img src="{{ asset('images/landing/how-icon.svg') }}" alt="" aria-hidden="true"
                     class="mx-auto h-[57.5px] w-[36px] rotate-[2.58deg]">
                <h2 class="mt-[15px] text-[21px] font-black text-[#004004] sm:text-[24px]">How It Works</h2>
                <p class="mx-auto mt-[15px] max-w-[390.5px] text-[14px] font-medium leading-snug text-[#353535] sm:text-[16px]">
                    Every request gets a unique QR-coded tracking number. As staff process it,
                    each status change is timestamped and recorded so you can see every move.
                </p>
            </div>

            <div id="steps" class="mt-[88px] grid grid-cols-1 gap-[49.5px] sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'card-submit.svg', 'w' => 'w-[36px]', 'title' => 'Submit',
                     'body' => 'File your request online with your supporting documents attached. You get a QR-coded tracking number right away.'],
                    ['icon' => 'card-assigned.svg', 'w' => 'w-[36px]', 'title' => 'Assigned and Processed',
                     'body' => 'Your request is assigned to a staff member who takes it through each stage, from In Progress to In Review to Approved to Completed. Every change is timestamped.'],
                    ['icon' => 'card-clock.svg', 'w' => 'w-[37px]', 'title' => 'Track anytime',
                     'body' => 'Scan your QR code or enter your tracking number to see the current stage, who is handling it, and the full history.'],
                ] as $step)
                    <div class="flex min-h-[232px] flex-col items-center rounded-[24px] border-[0.5px] border-[rgba(0,64,4,0.5)] bg-[rgba(141,255,60,0.2)] px-[22.5px] pb-[19px] pt-[18.5px] text-center backdrop-blur-[8px]">
                        <div class="flex h-[60px] w-[60px] items-center justify-center rounded-[8px] bg-[rgba(139,212,144,0.3)]">
                            <img src="{{ asset('images/landing/'.$step['icon']) }}" alt=""
                                 class="h-[36px] {{ $step['w'] }}">
                        </div>
                        <h3 class="mt-[23px] text-[18px] font-black leading-tight text-[#245501] sm:text-[21px]">{{ $step['title'] }}</h3>
                        <p class="mt-[13px] text-[14px] font-medium leading-snug text-[#1e1e1e] sm:text-[14px]">{{ $step['body'] }}</p>
=======
    {{-- ── What We Do ───────────────────────────────────────────────────────
         Three translucent cards introducing the service. The middle card is
         drawn 24px taller than its neighbours (it starts higher in the frame),
         which is why the row is bottom-aligned rather than stretched.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="features" class="relative bg-white px-6 pb-[70px] pt-[86px] sm:px-10">
        <div class="mx-auto max-w-[1512px]">
            <p class="text-center text-[18px] font-bold text-[#004004] sm:text-[22px]">What We Do</p>

            <h2 class="mt-[6px] text-center font-display text-[26px] font-black leading-tight sm:text-[36px]">
                <span class="text-[#1a1b1a]">Fast, Transparent &amp; Reliable </span>
                <span class="text-[#01721a]">Document Tracking</span>
            </h2>

            <p class="mx-auto mt-[10px] max-w-[760px] text-center text-[17px] font-medium leading-snug text-[#353535] sm:text-[20px]">
                Streamlining public services for the City Government of San Pedro through
                integrated QR technology.
            </p>

            {{-- items-end, not items-stretch: the design staggers the middle card
                 upward, so the three share a baseline rather than a top edge. --}}
            <div class="mx-auto mt-[62px] grid max-w-[1140px] grid-cols-1 items-end gap-6 sm:grid-cols-3">
                {{-- Card 1 — its icon is two layered glyphs (a map pin with a
                     crosshair overlapping its lower right), so it keeps the 79×60
                     box the design groups them in. --}}
                <div class="flex min-h-[340px] flex-col rounded-[22px] bg-[rgba(255,255,255,0.76)] p-[40px] shadow-[0_4px_17px_0_rgba(0,0,0,0.25)] ring-1 ring-black/[0.04] sm:min-h-[366px]">
                    <div class="relative h-[60px] w-[79px] shrink-0">
                        <img src="{{ asset('images/landing/wwd-pin.svg') }}" alt="" aria-hidden="true"
                             class="absolute left-0 top-0 h-[60px] w-[60px]">
                        <img src="{{ asset('images/landing/wwd-crosshair.svg') }}" alt="" aria-hidden="true"
                             class="absolute left-[31px] top-[11px] h-[48px] w-[48px]">
                    </div>
                    <h3 class="mt-[30px] font-display text-[25px] font-black leading-none text-black">Citizen Tracking</h3>
                    <p class="mt-[14px] text-[18px] font-medium leading-snug text-[#353535] sm:text-[22px]">
                        Monitor the real-time status and location of your requests and
                        documents using a unique Tracking ID/QR code—no account needed.
                    </p>
                </div>

                {{-- Card 2 --}}
                <div class="flex min-h-[340px] flex-col rounded-[22px] bg-[rgba(255,255,255,0.76)] p-[40px] shadow-[0_4px_17px_0_rgba(0,0,0,0.25)] ring-1 ring-black/[0.04] sm:min-h-[390px]">
                    <img src="{{ asset('images/landing/wwd-qr.svg') }}" alt="" aria-hidden="true"
                         class="h-[60px] w-[60px] shrink-0">
                    <h3 class="mt-[30px] font-display text-[25px] font-black leading-none text-black">QR-Code Verification</h3>
                    <p class="mt-[14px] text-[18px] font-medium leading-snug text-[#353535] sm:text-[22px]">
                        Instant scanning for city staff and officials to route, update, and
                        process incoming and outgoing files seamlessly.
                    </p>
                </div>

                {{-- Card 3 --}}
                <div class="flex min-h-[340px] flex-col rounded-[22px] bg-[rgba(255,255,255,0.76)] p-[40px] shadow-[0_4px_17px_0_rgba(0,0,0,0.25)] ring-1 ring-black/[0.04] sm:min-h-[366px]">
                    <img src="{{ asset('images/landing/wwd-chat.svg') }}" alt="" aria-hidden="true"
                         class="h-[57.5px] w-[60px] shrink-0">
                    <h3 class="mt-[30px] font-display text-[25px] font-black leading-none text-black">Smart Chatbot Assistance</h3>
                    <p class="mt-[14px] text-[18px] font-medium leading-snug text-[#353535] sm:text-[22px]">
                        Get 24/7 assistance on document requirements, city service procedures,
                        and tracking guidance through our smart chatbot.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Key Features ─────────────────────────────────────────────────────
         Sits on the green→white wash the design lays across the full bleed. The
         copy occupies the right half of the 1512 frame; the left half carries
         the service illustration.
         ───────────────────────────────────────────────────────────────────── --}}
    <section class="relative isolate overflow-hidden bg-white px-6 py-[72px] sm:px-10">
        {{-- band-gradient.svg exports with preserveAspectRatio="none", so it
             stretches edge-to-edge instead of tiling. --}}
        <img src="{{ asset('images/landing/band-gradient.svg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 -z-10 h-full w-full">

        <div class="mx-auto grid max-w-[1512px] grid-cols-1 items-center gap-12 lg:grid-cols-2 lg:gap-6">
            {{-- Illustration holds the left half of the band. --}}
            <div class="order-2 flex justify-center lg:order-1">
                <img src="{{ asset('images/landing/features-person.png') }}" alt=""
                     loading="lazy"
                     class="w-full max-w-[560px] object-contain">
            </div>

            <div class="order-1 lg:order-2">
                <p class="text-[20px] font-bold text-[#004004] sm:text-[25px]">FEATURES</p>

                <h2 class="mt-[8px] font-display text-[32px] font-black leading-none sm:text-[45px]">
                    <span class="text-[#1a1b1a]">Key </span><span class="text-[#01721a]">Features</span>
                </h2>

                <p class="mt-[18px] max-w-[623px] text-[20px] font-bold leading-snug text-[#353535] sm:text-[30px]">
                    Integrated Tools for efficient document processing and tracking.
                </p>

                {{-- Two columns of 80px frosted tiles; the fifth item opens a third
                     row on its own, exactly as the design leaves it. --}}
                <ul class="mt-[46px] grid grid-cols-1 gap-x-[21px] gap-y-[22px] sm:grid-cols-2">
                    @foreach ([
                        ['icon' => 'feat-file-text.svg', 'label' => 'Document Routing'],
                        ['icon' => 'feat-shield.svg',    'label' => 'Secure QR Identification'],
                        ['icon' => 'feat-search.svg',    'label' => 'Accountability Audit'],
                        ['icon' => 'feat-people.svg',    'label' => 'Role-Based Access'],
                        ['icon' => 'feat-clock.svg',     'label' => 'Real-Time Status'],
                    ] as $feature)
                        <li class="flex items-center gap-[15px]">
                            <span class="flex h-[80px] w-[80px] shrink-0 items-center justify-center rounded-[15px] bg-[rgba(255,255,255,0.2)] shadow-[0_0_4px_1px_rgba(0,0,0,0.25)] backdrop-blur-[25px]">
                                <img src="{{ asset('images/landing/'.$feature['icon']) }}" alt="" aria-hidden="true"
                                     class="h-[42px] w-[42px] object-contain">
                            </span>
                            <span class="text-[18px] font-medium leading-tight text-[#353535] sm:text-[22px]">{{ $feature['label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ── How It Works ─────────────────────────────────────────────────────
         Four stages on one rule. The connector is a single 3px line behind the
         tiles rather than three separate segments, so it cannot drift out of
         alignment when the columns resize.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="relative isolate overflow-hidden bg-white px-6 pb-[96px] pt-[86px] sm:px-10">
        {{-- Soft green bloom the design floats behind the middle of the row. --}}
        <img src="{{ asset('images/landing/steps-glow.svg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute left-1/2 top-[270px] -z-10 hidden h-[544px] w-[567px] -translate-x-1/2 lg:block">

        <div class="mx-auto max-w-[1512px]">
            <p class="text-center text-[20px] font-bold text-[#004004] sm:text-[25px]">HOW IT WORKS</p>

            <h2 class="mt-[8px] text-center font-display text-[32px] font-black leading-none sm:text-[45px]">
                <span class="text-[#01721a]">4-Step</span><span class="text-[#1a1b1a]"> Tracking Flow</span>
            </h2>

            <p class="mx-auto mt-[22px] max-w-[1031px] text-center text-[20px] font-bold leading-snug text-[#353535] sm:text-[30px]">
                A quick guide on how documents are submitted, processed, and tracked.
            </p>

            <div class="relative mx-auto mt-[72px] max-w-[1250px]">
                {{-- The rule spans only the inner three gaps: it starts and ends at
                     the centre of the outer columns (1/8 and 7/8 of the row) so no
                     stub pokes out past the first and last tile. --}}
                <div class="pointer-events-none absolute left-[12.5%] right-[12.5%] top-[57px] hidden h-[3px] bg-[#017209] sm:block" aria-hidden="true"></div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-[56px] sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['icon' => 'step-1.svg', 'num' => 'step-num-1.svg', 'w' => 'w-[36px]', 'title' => 'Submit Request',
                         'body' => 'Fill out the request form and receive your unique Tracking ID.'],
                        ['icon' => 'step-2.svg', 'num' => 'step-num-2.svg', 'w' => 'w-[48px]', 'title' => 'Document Processing',
                         'body' => 'LGU staff log and route your paper across departments in real time.'],
                        ['icon' => 'step-3.svg', 'num' => 'step-num-3.svg', 'w' => 'w-[48px]', 'title' => 'Track Progress',
                         'body' => 'Check live status updates anytime using the Public Tracking Module or AI Chatbot.'],
                        ['icon' => 'step-4.svg', 'num' => 'step-num-4.svg', 'w' => 'w-[48px]', 'title' => 'Claim Document',
                         'body' => 'Once the status shows "Ready for Release," proceed to City Hall to collect your document.'],
                    ] as $step)
                        <div class="relative flex flex-col items-center text-center">
                            {{-- The pale green plate is 20px larger than the white
                                 tile on every side; the number rides its top-right
                                 corner as a badge. --}}
                            <div class="relative flex h-[117px] w-[119px] shrink-0 items-center justify-center rounded-[10px] bg-[rgba(1,114,9,0.15)]">
                                <div class="flex h-[95px] w-[98px] items-center justify-center rounded-[20px] border border-[#01721a] bg-white">
                                    <img src="{{ asset('images/landing/'.$step['icon']) }}" alt="" aria-hidden="true"
                                         class="h-[48px] {{ $step['w'] }} object-contain">
                                </div>
                                <img src="{{ asset('images/landing/'.$step['num']) }}" alt="" aria-hidden="true"
                                     class="absolute -top-[6px] right-[-16px] h-[44px] w-[39px] object-contain">
                            </div>

                            <h3 class="mt-[34px] text-[22px] font-black leading-tight text-[#353535] sm:text-[25px]">{{ $step['title'] }}</h3>
                            <p class="mt-[12px] max-w-[273px] text-[18px] leading-snug text-black sm:text-[22px]">{{ $step['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── Security ─────────────────────────────────────────────────────────
         Same wash as the Key Features band. Copy on the left, four cards on the
         right stepped 64px in and out so they read as a staggered column.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="security" class="relative isolate overflow-hidden bg-white px-6 py-[80px] sm:px-10">
        <img src="{{ asset('images/landing/band-gradient.svg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 -z-10 h-full w-full">

        <div class="mx-auto grid max-w-[1512px] grid-cols-1 items-center gap-12 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-10">
            <div>
                <p class="text-[20px] font-bold text-[#004004] sm:text-[25px]">SECURITY</p>

                <h2 class="mt-[8px] font-display text-[32px] font-black leading-none sm:text-[45px]">
                    <span class="text-[#1a1b1a]">How We Keep Your </span><span class="text-[#01721a]">Data Safe</span>
                </h2>

                <p class="mt-[36px] max-w-[590px] text-[20px] font-bold leading-snug text-[#353535] sm:text-[30px]">
                    SPeED TraQR is built to protect official City Hall records and keep your
                    personal information private.
                </p>
            </div>

            {{-- Each card is offset by its own margin rather than a grid column,
                 which keeps the 64px stagger on desktop and collapses cleanly to a
                 plain stack on narrow screens. --}}
            <div class="flex flex-col gap-[21px]">
                {{-- `box` is the icon frame the design draws the glyph layers
                     inside; each layer keeps its own exported size and offset, so
                     a two-part glyph (the scales) stays assembled. --}}
                @foreach ([
                    ['glow' => 'sec-glow.svg',   'box' => 38, 'offset' => '',
                     'layers' => [
                         ['src' => 'sec-law-a.svg', 'l' => -0.53, 't' => 1.08, 'w' => 33.01, 'h' => 33.84],
                         ['src' => 'sec-law-b.svg', 'l' => 21.47, 't' => 7.95, 'w' => 16.53, 'h' => 18.66],
                     ],
                     'title' => 'Law-Compliant Service', 'body' => 'Follows government rules for safe document handling.'],
                    ['glow' => 'sec-glow-2.svg', 'box' => 32, 'offset' => 'lg:ml-[64px]',
                     'layers' => [['src' => 'sec-logs.svg', 'l' => 0.66, 't' => 0, 'w' => 30.61, 'h' => 32.04]],
                     'title' => 'Staff Logs', 'body' => 'Records every staff action for complete accountability.'],
                    ['glow' => 'sec-glow-3.svg', 'box' => 40, 'offset' => '',
                     'layers' => [['src' => 'sec-eye-off.svg', 'l' => 0, 't' => 3.57, 'w' => 40.06, 'h' => 32.92]],
                     'title' => 'Private Tracking', 'body' => 'Shows status updates while keeping personal details hidden.'],
                    ['glow' => 'sec-glow-4.svg', 'box' => 40, 'offset' => 'lg:ml-[64px]',
                     'layers' => [['src' => 'sec-wifi.svg', 'l' => 0, 't' => 3, 'w' => 40.02, 'h' => 34]],
                     'title' => 'Offline Backup', 'body' => 'Saves tracking data even when the internet is down.'],
                ] as $card)
                    <div class="flex w-full items-center gap-[27px] rounded-[15px] bg-white px-[8px] py-[18px] shadow-[0_0_4px_0_rgba(0,0,0,0.25)] lg:h-[127px] lg:w-[427px] lg:py-0 {{ $card['offset'] }}">
                        {{-- The icon sits on an 87px blue bloom, so the glyph needs
                             to be centred on the bloom, not on the padding box. --}}
                        <div class="relative flex h-[87px] w-[87px] shrink-0 items-center justify-center">
                            <img src="{{ asset('images/landing/'.$card['glow']) }}" alt="" aria-hidden="true"
                                 class="absolute inset-0 h-[87px] w-[87px]">
                            <span class="relative block" style="width:{{ $card['box'] }}px;height:{{ $card['box'] }}px">
                                @foreach ($card['layers'] as $layer)
                                    <img src="{{ asset('images/landing/'.$layer['src']) }}" alt="" aria-hidden="true"
                                         class="absolute max-w-none"
                                         style="left:{{ $layer['l'] }}px;top:{{ $layer['t'] }}px;width:{{ $layer['w'] }}px;height:{{ $layer['h'] }}px">
                                @endforeach
                            </span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-display text-[20px] leading-tight text-black sm:text-[22px]">{{ $card['title'] }}</h3>
                            <p class="mt-[6px] text-[17px] leading-snug text-black sm:text-[20px]">{{ $card['body'] }}</p>
                        </div>
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
                    </div>
                @endforeach
            </div>
        </div>
    </section>

<<<<<<< HEAD
    {{-- Footer --}}
    <footer class="border-t border-black/5 bg-[#f0f0f0] py-8">
        <div class="mx-auto max-w-[1209.5px] px-6 text-center text-[14px] text-[#8a8a8a]">
=======
    {{-- ── FAQ ──────────────────────────────────────────────────────────────
         The design draws every answer visible, so the panels open by default;
         the chevron is still live so a reader can collapse one they have read.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="faq" class="relative isolate overflow-hidden bg-white px-6 pb-[110px] pt-[90px] sm:px-10">
        {{-- Two oversized lime blooms, bled off the left and right edges. --}}
        <img src="{{ asset('images/landing/faq-glow.svg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute left-[-175px] top-[36px] -z-10 hidden h-[1217px] w-[1217px] max-w-none lg:block">
        <img src="{{ asset('images/landing/faq-glow.svg') }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute left-[460px] top-[503px] -z-10 hidden h-[1217px] w-[1217px] max-w-none lg:block">

        <div class="mx-auto max-w-[1512px]">
            <p class="text-center text-[20px] font-bold text-[#004004] sm:text-[25px]">FAQ</p>

            <h2 class="mt-[6px] text-center font-display text-[32px] font-black leading-none sm:text-[45px]">
                <span class="text-black">Frequently Asked </span><span class="text-[#01721a]">Questions</span>
            </h2>

            <div class="mx-auto mt-[58px] flex max-w-[952px] flex-col gap-[17px]">
                @foreach ([
                    ['q' => 'How can I track the status of my document or request?',
                     'a' => 'Simply enter your unique Tracking ID or scan the assigned QR code on the Public Tracking Module on our homepage. No user account or registration is required.'],
                    ['q' => 'Do I need an account to use SPeED TraQR?',
                     'a' => 'Simply enter your unique Tracking ID or scan the assigned QR code on the Public Tracking Module on our homepage. No user account or registration is required.'],
                    ['q' => 'Is my personal information safe when I track a document publicly?',
                     'a' => 'Yes. In compliance with the Data Privacy Act of 2012 (RA 10173), the Public Tracking Module only displays the document’s current status, location, and routing history. Personal details and attached files remain strictly confidential and hidden from public view.'],
                    ['q' => 'What should I do if I lose my Tracking ID or QR Code?',
                     'a' => 'You can consult our integrated AI Chatbot on the landing page for guidance, or visit the specific City Hall office where you submitted your document to retrieve your tracking information.'],
                    ['q' => 'What types of documents can be tracked through SPeED TraQR?',
                     'a' => 'The system tracks official documents, citizen service requests, permits, endorsements, and internal office communications routed across departments within the City Government of San Pedro.'],
                    ['q' => 'How does the system estimate when my document will be ready?',
                     'a' => 'SPeED TraQR uses system-calculated processing metrics based on standard Service Level Agreements (SLA) under the Ease of Doing Business Act (RA 11032) to give you an estimated completion timeframe.'],
                ] as $i => $faq)
                    <div x-data="{ open: true }"
                         class="overflow-hidden rounded-[10px] border border-[rgba(252,252,252,0.54)] bg-[rgba(255,255,255,0.6)] shadow-[0_0_4px_0_rgba(0,0,0,0.6)] backdrop-blur-[25px]">
                        <button type="button"
                                x-on:click="open = !open"
                                x-bind:aria-expanded="open ? 'true' : 'false'"
                                aria-controls="faq-panel-{{ $i }}"
                                class="flex w-full items-start gap-6 px-[39px] py-[23px] text-left">
                            <span class="min-w-0 flex-1 font-display text-[22px] font-bold leading-tight text-[#005606] sm:text-[30px]">{{ $faq['q'] }}</span>
                            {{-- The exported chevron points right; rotating it a
                                 quarter turn is what marks the panel as open. --}}
                            <img src="{{ asset('images/landing/faq-chevron.svg') }}" alt="" aria-hidden="true"
                                 class="mt-[6px] h-[23px] w-[12px] shrink-0 transition-transform duration-200"
                                 x-bind:class="open ? 'rotate-90' : ''">
                        </button>
                        {{-- x-show + a fade, not x-collapse: the collapse plugin
                             isn't among this project's dependencies. --}}
                        <div id="faq-panel-{{ $i }}" x-show="open" x-transition.opacity.duration.200ms>
                            <p class="px-[39px] pb-[26px] text-[18px] font-medium leading-snug text-black sm:text-[24px]">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    </main>

<<<<<<< HEAD
    {{-- Footer — #707170 laid down at 10%, as the design's flat plate. --}}
    <footer class="bg-[rgba(112,113,112,0.1)] py-[72px]">
        <div class="mx-auto max-w-[1512px] px-6 text-center text-[15px] text-[rgba(109,109,109,0.72)] sm:text-[16px]">
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
            &copy; {{ date('Y') }} SPeED TraQR — Document Tracking System. All rights reserved.
        </div>
    </footer>
=======
    {{-- The design's flat grey plate carried only a copyright line. It is now
         the shared fat footer, so the landing page ends with the same contact
         details and doormat navigation as every other page. --}}
    <x-site-footer />
>>>>>>> 70f29ff6dcc8bd335ec24d7be8feb681edb34c95

    @include('layouts.partials.bfcache-guard')

</body>
</html>
