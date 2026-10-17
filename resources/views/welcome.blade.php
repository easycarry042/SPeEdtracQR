<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="motion-safe:scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="File a request with the City Government of San Pedro, Laguna online and track it from filing to pickup with a QR-coded tracking number.">
    <title>SPeED TraQR — Track Your City Hall Requests | City of San Pedro</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.accessibility-widget')
</head>
<body class="min-h-screen bg-white antialiased text-ink">
@php
    /* The page's two audiences each get exactly one way in: citizens the two
       hero actions (repeated once at the end), staff a quiet header link. */
    $submitUrl = route('public.request.create');
    $trackUrl = route('citizen.track');

    // The shared citizen buttons (resources/css/app.css, "Citizen surface set").
    $btnPrimary = 'cz-btn cz-btn-primary';
    $btnSecondary = 'cz-btn cz-btn-secondary';
@endphp

    {{-- First tab stop: jumps the header. --}}
    <a href="#main" class="skip-link">Skip to content</a>

    {{-- The shared citizen header, in its landing-page form: section links and
         the staff sign-in. Every section it targets sets scroll-margin to clear
         it. --}}
    @include('layouts.partials.portal-header', ['landing' => true])

    <main id="main">

    {{-- ── Hero ─────────────────────────────────────────────────────────────
         City Hall photo on the right, feathered into white by the exported
         vignette, so the copy on the left sits on clean paper. On phones the
         photo moves under the actions instead of behind the text, where it
         only cost contrast.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="top" class="relative isolate overflow-hidden bg-white">
        <div class="pointer-events-none absolute inset-y-0 left-[36%] right-0 -z-10 hidden lg:block" aria-hidden="true">
            {{-- 2.84% = the design's 36px inside a 1268px vignette: the feather
                 has to start before the photo does or its raw edge shows. --}}
            <div class="absolute inset-y-0 left-[2.84%] right-0">
                <img src="{{ asset('images/landing/hero-cityhall.jpg') }}" alt=""
                     width="2000" height="1500" fetchpriority="high"
                     class="absolute inset-0 h-full w-full object-cover">
            </div>
            <img src="{{ asset('images/landing/hero-gradient.svg') }}" alt=""
                 class="absolute inset-0 h-full w-full">
            {{-- The vignette's top feather starts a few pixels in, which left a
                 raw strip of sky under the header; this closes it. --}}
            <div class="absolute inset-x-0 top-0 h-28 bg-gradient-to-b from-white via-white/70 to-transparent"></div>
        </div>

        <div class="mx-auto flex max-w-[1209.5px] flex-col px-4 pb-14 pt-12 sm:px-6 sm:pt-16 lg:min-h-[min(calc(100svh/var(--ui-zoom)-72px),720px)] lg:justify-center lg:px-8 lg:py-20">
            <div class="max-w-[560px]">
                <p class="inline-flex items-center gap-2 rounded-full bg-[#eef8ea] px-3.5 py-1.5 text-[13px] font-bold text-[#004004]">
                    <span class="h-2 w-2 rounded-full bg-[#01721a]" aria-hidden="true"></span>
                    City Government of San Pedro, Laguna
                </p>

                <h1 class="mt-5 font-display text-[38px] font-black leading-[1.05] tracking-tight sm:text-[50px] lg:text-[58px]">
                    <span class="block text-[#004004]">Your requests,</span>
                    <span class="block text-[#01721a]">tracked in real time.</span>
                </h1>

                <p class="mt-5 max-w-[440px] text-pretty text-[17px] font-medium leading-relaxed text-[#353535] sm:text-[18px]">
                    File a request online, get a QR-coded tracking number by email, and follow it
                    from filing to pickup. No account needed.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ $submitUrl }}" class="{{ $btnPrimary }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M12 11v6M9 14h6"/>
                        </svg>
                        Submit a request
                    </a>
                    <a href="{{ $trackUrl }}" class="{{ $btnSecondary }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3.5" y="3.5" width="6" height="6" rx="1"/>
                            <rect x="14.5" y="3.5" width="6" height="6" rx="1"/>
                            <rect x="3.5" y="14.5" width="6" height="6" rx="1"/>
                            <path stroke-linecap="round" d="M14.5 14.5h2m4 0h-2m0 0v2m0 4h-6v-2"/>
                        </svg>
                        Track a request
                    </a>
                </div>
            </div>

            <img src="{{ asset('images/landing/hero-cityhall.jpg') }}" alt="San Pedro City Hall, Laguna"
                 width="2000" height="1500" loading="lazy"
                 class="mt-10 aspect-[16/10] w-full rounded-[20px] object-cover shadow-[0_1px_2px_rgba(0,0,0,0.08),0_12px_32px_-16px_rgba(0,64,4,0.45)] lg:hidden">
        </div>
    </section>

    {{-- ── How it works ─────────────────────────────────────────────────────
         The spine of the page, so it comes straight after the hero. Four
         stages on one rule: a single 3px line behind the tiles rather than
         three segments, so it cannot drift when the columns resize. (The old
         green bloom behind the row was clipped flat by the section edge.)
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="how-it-works" class="relative isolate scroll-mt-20 bg-white px-4 py-20 sm:px-6 lg:py-24">
        <div class="mx-auto max-w-[1209.5px]">
            <div class="mx-auto max-w-[640px] text-center">
                <h2 class="font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
                    How it <span class="text-[#01721a]">works</span>
                </h2>
                <p class="mt-3 text-balance text-[17px] font-medium leading-relaxed text-[#4b5a50] sm:text-[19px]">
                    Four steps from filing your request to picking it up.
                </p>
            </div>

            <div class="relative mx-auto mt-14 max-w-[1140px]">
                {{-- Starts and ends at the centre of the outer columns (1/8 and
                     7/8 of the row), so no stub pokes out past the end tiles. --}}
                <div class="pointer-events-none absolute left-[12.5%] right-[12.5%] top-[57px] hidden h-[3px] bg-[#017209] lg:block" aria-hidden="true"></div>

                <ol class="grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['icon' => 'step-1.svg', 'num' => 'step-num-1.svg', 'w' => 'w-[36px]', 'title' => 'Submit a request',
                         'body' => 'Fill out the online form. Your tracking number and QR code arrive by email.'],
                        ['icon' => 'step-2.svg', 'num' => 'step-num-2.svg', 'w' => 'w-[48px]', 'title' => 'City Hall processes it',
                         'body' => 'Staff scan your request in and out at every office it passes through.'],
                        ['icon' => 'step-3.svg', 'num' => 'step-num-3.svg', 'w' => 'w-[48px]', 'title' => 'Track its progress',
                         'body' => 'Check the status and current office anytime with your tracking number or QR code.'],
                        ['icon' => 'step-4.svg', 'num' => 'step-num-4.svg', 'w' => 'w-[48px]', 'title' => 'Claim your document',
                         'body' => 'When the status says “Ready for pickup”, bring your QR code to City Hall.'],
                    ] as $i => $step)
                        <li class="relative flex flex-col items-center text-center">
                            {{-- The pale green plate is 20px larger than the white
                                 tile on every side; the number rides its corner. --}}
                            <div class="relative flex h-[117px] w-[119px] shrink-0 items-center justify-center rounded-[10px] bg-[#d9eadb]">
                                <div class="flex h-[95px] w-[98px] items-center justify-center rounded-[20px] border border-[#01721a] bg-white">
                                    <img src="{{ asset('images/landing/'.$step['icon']) }}" alt="" aria-hidden="true"
                                         class="h-[48px] {{ $step['w'] }} object-contain">
                                </div>
                                <img src="{{ asset('images/landing/'.$step['num']) }}" alt="" aria-hidden="true"
                                     class="absolute -top-[6px] right-[-16px] h-[44px] w-[39px] object-contain">
                            </div>

                            <h3 class="mt-7 text-[20px] font-black leading-tight text-[#1a1b1a]">
                                <span class="sr-only">Step {{ $i + 1 }}: </span>{{ $step['title'] }}
                            </h3>
                            <p class="mt-2 max-w-[260px] text-[16px] leading-relaxed text-[#4b5a50]">{{ $step['body'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- ── Features ─────────────────────────────────────────────────────────
         One grid in place of the old "What We Do", "Key Features" and
         "Security" sections, which described the same five ideas three times.
         Each card states one thing the system actually does, in a citizen's
         terms; the icons are inline so they share one stroke and colour.
         ───────────────────────────────────────────────────────────────────── --}}
    <section id="features" class="relative isolate scroll-mt-20 overflow-hidden bg-[#f5faf3] px-4 py-20 sm:px-6 lg:py-24">
        <div class="mx-auto max-w-[1209.5px]">
            <div class="mx-auto max-w-[680px] text-center">
                <h2 class="font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
                    Fast, transparent &amp; <span class="text-[#01721a]">private</span>
                </h2>
                <p class="mt-3 text-balance text-[17px] font-medium leading-relaxed text-[#4b5a50] sm:text-[19px]">
                    One system follows your request from the counter where it is filed to the
                    desk that releases it.
                </p>
            </div>

            @php
                $features = [
                    ['title' => 'Real-time status',
                     'body' => 'See which office has your request and what happens next, from filing to pickup.',
                     'icon' => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>'],
                    ['title' => 'QR codes only City Hall can read',
                     'body' => 'Your slip’s QR code is signed. Ordinary scanner apps can’t open it, so it can’t be faked or misused.',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>'],
                    ['title' => 'Routed office to office',
                     'body' => 'Staff scan your paper in and out at every department, so its trail is always current.',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h11m0 0-3-3m3 3-3 3M20 17H9m0 0 3-3m-3 3 3 3"/>'],
                    ['title' => 'Processing-time targets',
                     'body' => 'Every office works to a set processing time under RA&nbsp;11032, and late requests are flagged to staff.',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 2h4M12 14l3-3"/><circle cx="12" cy="14" r="8"/>'],
                    ['title' => 'Your details stay private',
                     'body' => 'The public tracking page shows your request’s status and location. Contact details are masked and files stay hidden, per RA&nbsp;10173.',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.6 6.6C4.5 8 3 10 2 12c1 2.5 5 7 10 7 1.9 0 3.6-.6 5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2"/>'],
                    ['title' => 'Every action on record',
                     'body' => 'Each scan, update and release is logged with the staff member who made it.',
                     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 12h6M9 16h4"/>'],
                ];
            @endphp

            <ul class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <li class="rounded-[20px] border border-[rgba(0,64,4,0.08)] bg-white p-7 shadow-[0_1px_2px_rgba(0,64,4,0.06),0_8px_24px_-12px_rgba(0,64,4,0.18)]">
                        <span class="flex h-14 w-14 items-center justify-center rounded-[16px] bg-[#eef8ea] text-[#01721a]">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">{!! $feature['icon'] !!}</svg>
                        </span>
                        <h3 class="mt-5 font-display text-[21px] font-black leading-snug text-[#1a1b1a]">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-[16px] leading-relaxed text-[#4b5a50]">{!! $feature['body'] !!}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- ── FAQ ──────────────────────────────────────────────────────────────
         Native <details>: keyboard-operable, searchable with find-in-page, and
         the answers are in the HTML for readers without JavaScript. Closed by
         default — six open answers made the questions a wall to scan.
         ───────────────────────────────────────────────────────────────────── --}}
    {{-- No bottom padding: the footer brings its own 120px of air. --}}
    <section id="faq" class="relative isolate scroll-mt-20 bg-white px-4 pt-20 sm:px-6 lg:pt-24">
        <div class="mx-auto max-w-[820px]">
            <h2 class="text-center font-display text-[32px] font-black leading-tight text-[#1a1b1a] sm:text-[42px]">
                Frequently asked <span class="text-[#01721a]">questions</span>
            </h2>

            @php
                $faqs = [
                    ['q' => 'How can I track the status of my document or request?',
                     'a' => 'Open the <a href="'.e($trackUrl).'" class="font-bold text-[#01721a] underline underline-offset-2 hover:text-[#004004]">Track page</a> and enter your tracking number, or scan the QR code from your confirmation email or printed slip.'],
                    ['q' => 'Do I need an account to use SPeED TraQR?',
                     'a' => 'No. Anyone can file and track a request without signing up. Keep your tracking number: it is how you follow your request and claim it.'],
                    ['q' => 'Is my personal information safe when I track a document publicly?',
                     'a' => 'Yes. In compliance with the Data Privacy Act of 2012 (RA&nbsp;10173), the public tracking page shows the document’s current status, location and routing history. Your email and phone number appear only partly hidden, and attached files are never shown.'],
                    ['q' => 'What should I do if I lose my tracking number or QR code?',
                     'a' => 'Look for the confirmation email we sent when you filed. It has both your tracking number and your QR code. If you can’t find it, visit the City Hall office where you filed your request.'],
                    ['q' => 'What types of documents can be tracked through SPeED TraQR?',
                     'a' => 'Official documents, citizen service requests, permits, endorsements and internal office communications routed across departments of the City Government of San Pedro.'],
                    ['q' => 'How does the system estimate when my document will be ready?',
                     'a' => 'It looks at how long similar requests took to finish. Until there is enough history, it uses the standard processing times set under the Ease of Doing Business Act (RA&nbsp;11032).'],
                ];
            @endphp

            <div class="mt-10 flex flex-col gap-3">
                @foreach ($faqs as $faq)
                    <details class="group rounded-[16px] border border-[rgba(0,64,4,0.12)] bg-white shadow-[0_1px_2px_rgba(0,64,4,0.05)] open:border-[rgba(1,114,26,0.4)] open:bg-[#fbfdfa]">
                        <summary class="flex cursor-pointer touch-manipulation list-none items-start justify-between gap-6 rounded-[16px] px-6 py-5 focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[#01721a] [&::-webkit-details-marker]:hidden">
                            <span class="font-display text-[19px] font-bold leading-snug text-[#004004] sm:text-[21px]">{{ $faq['q'] }}</span>
                            <svg class="mt-1 h-5 w-5 shrink-0 text-[#01721a] transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </summary>
                        <p class="px-6 pb-6 text-[16px] leading-relaxed text-[#353535] sm:text-[17px]">{!! $faq['a'] !!}</p>
                    </details>
                @endforeach
            </div>

            {{-- The page ends on a next step, not on the last answer: the footer
                 no longer carries navigation. --}}
            <div class="mt-16 rounded-[24px] bg-[#eef8ea] px-6 py-10 text-center sm:px-10">
                <h2 class="font-display text-[26px] font-black leading-tight text-[#004004] sm:text-[30px]">Ready to file a request?</h2>
                <p class="mx-auto mt-2 max-w-[460px] text-balance text-[16px] leading-relaxed text-[#4b5a50]">
                    It takes a few minutes. You’ll get your tracking number by email right away.
                </p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ $submitUrl }}" class="{{ $btnPrimary }}">Submit a request</a>
                    <a href="{{ $trackUrl }}" class="{{ $btnSecondary }}">Track a request</a>
                </div>
            </div>
        </div>
    </section>

    </main>

    <x-site-footer />

    @include('layouts.partials.bfcache-guard')

</body>
</html>
