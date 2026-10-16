{{-- Shared "fat" footer — the last thing on every page that scrolls.

     Four labelled columns rather than one long link list: a visitor who has
     scrolled this far is either lost or finished, and both need the same
     things to hand — who runs this, how to reach them, and the one or two
     pages they actually came for.

     The plate is the brand's dark green so it reads as the end of the page on
     every backdrop it sits on: the landing page's white paper, the citizen
     portal's doodle wash, and the staff workspace's light chrome.

     `office hours` below is the ordinary LGU schedule, not a value read from
     the system — correct it here if San Pedro's windows differ. --}}
@php
    $home = route('welcome');

    /** Doormat navigation: label => href. Anchors resolve against the landing page. */
    $citizenLinks = [
        'Submit a Request' => route('public.request.create'),
        'Track a Document' => route('citizen.track'),
        'Citizen Portal' => route('citizen.dashboard'),
        'How It Works' => $home.'#how-it-works',
        'Frequently Asked Questions' => $home.'#faq',
    ];

    $systemLinks = [
        'Features' => $home.'#features',
        'Security & Data Privacy' => $home.'#security',
        'Home' => $home,
        'Staff Sign In' => route('login'),
    ];
@endphp

<footer class="mt-auto bg-[#004004] text-white">
    <div class="mx-auto w-full max-w-[1512px] px-6 py-[56px] sm:px-10 lg:py-[64px]">

        <div class="grid gap-x-10 gap-y-[44px] md:grid-cols-2 lg:grid-cols-[1.7fr_1fr_1fr_1.15fr]">

            {{-- Brand: what this is and who it belongs to, for anyone who
                 landed deep in the site from a QR code or an emailed link. --}}
            <div class="max-w-[420px]">
                <div class="flex items-center gap-[13px]">
                    {{-- The white mark, not the landing page's green one: the
                         green icon all but disappears on this plate. --}}
                    <img src="{{ asset('images/staff/logo-white.png') }}" alt=""
                         class="h-[46px] w-[46px] shrink-0 object-contain">
                    <span class="text-[26px] leading-none">
                        <span class="font-display font-black text-white">SPeED</span>
                        <span class="font-sans font-bold text-[#8dff3c]">TraQR</span>
                    </span>
                </div>

                <p class="mt-[18px] text-[15px] leading-relaxed text-white/75">
                    The document tracking system of the City Government of San Pedro, Laguna.
                    Every request carries a QR-coded tracking number from the counter where it
                    is filed to the desk that releases it.
                </p>

                <p class="mt-[16px] text-[14px] font-semibold text-[#8dff3c]">
                    San Pedro City Hall, Laguna
                </p>
            </div>

            {{-- Doormat navigation, capped at five so the column stays scannable. --}}
            <div>
                <h2 class="text-[13px] font-black uppercase tracking-[0.18em] text-[#8dff3c]">For Citizens</h2>
                <ul class="mt-[18px] space-y-[11px]">
                    @foreach ($citizenLinks as $label => $href)
                        <li>
                            <a href="{{ $href }}" class="footer-link">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-[13px] font-black uppercase tracking-[0.18em] text-[#8dff3c]">The System</h2>
                <ul class="mt-[18px] space-y-[11px]">
                    @foreach ($systemLinks as $label => $href)
                        <li>
                            <a href="{{ $href }}" class="footer-link">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Contact. The email is a real mailbox, so it is a mailto rather
                 than plain text — on a phone that is the whole interaction. --}}
            <div>
                <h2 class="text-[13px] font-black uppercase tracking-[0.18em] text-[#8dff3c]">Get in Touch</h2>

                <ul class="mt-[18px] space-y-[14px] text-[15px] text-white/75">
                    <li class="flex items-start gap-[10px]">
                        <svg class="mt-[3px] h-[17px] w-[17px] shrink-0 text-[#8dff3c]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 13l9-5.5M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>
                        </svg>
                        <a href="mailto:speedtraqr@gmail.com" class="footer-link break-all">speedtraqr@gmail.com</a>
                    </li>

                    <li class="flex items-start gap-[10px]">
                        <svg class="mt-[3px] h-[17px] w-[17px] shrink-0 text-[#8dff3c]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/>
                        </svg>
                        <span>
                            Monday to Friday, 8:00 AM – 5:00 PM
                            <span class="mt-[3px] block text-[13px] text-white/55">Excluding public holidays</span>
                        </span>
                    </li>

                    <li class="flex items-start gap-[10px]">
                        <svg class="mt-[3px] h-[17px] w-[17px] shrink-0 text-[#8dff3c]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="4" y="4" width="6" height="6" rx="1"/>
                            <rect x="14" y="4" width="6" height="6" rx="1"/>
                            <rect x="4" y="14" width="6" height="6" rx="1"/>
                            <path stroke-linecap="round" d="M14 14h2m4 0h-2m0 0v2m0 4h-6v-2"/>
                        </svg>
                        <span>
                            Keep your tracking number
                            <span class="mt-[3px] block text-[13px] text-white/55">It is the only way to claim a document.</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- Legal bar: the copyright line, and the two statutes the system's
             behaviour is actually built on — the SLA clock and what the public
             tracking page is allowed to show. --}}
        <div class="mt-[44px] flex flex-col gap-[14px] border-t border-white/15 pt-[24px] text-[13.5px] text-white/60 lg:flex-row lg:items-center lg:justify-between">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'SPeED TraQR') }} — Document Tracking System. All rights reserved.</p>
            <p class="lg:text-right">
                Processing times follow RA 11032 (Ease of Doing Business Act).
                Personal data is handled under RA 10173 (Data Privacy Act of 2012).
            </p>
        </div>
    </div>
</footer>
