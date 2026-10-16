<x-citizen-layout>
    <x-slot name="title">Citizen Portal</x-slot>

    {{-- Hero / Welcome --}}
    <div class="pt-[36px] text-center">
        {{-- Two-citizen mark: this portal is the public's door, not staff's.
             Drawn as two overlapping figures, as in the design. --}}
        <div class="flex items-end justify-center">
            <img src="{{ asset('images/portal/user-female.svg') }}" alt="" class="h-[50.5px] w-[54.5px] -mr-[17px]">
            <img src="{{ asset('images/portal/user-male.svg') }}" alt="" class="h-[50.5px] w-[56px]">
        </div>

        <h1 class="mt-[14.5px] font-display text-[26px] font-black text-[#004004] sm:text-[32px]">
            Welcome to the Citizen Portal
        </h1>
        <p class="mt-[6px] text-[21px] font-semibold text-[#429e00] sm:text-[28px]">
            How can we help you today?
        </p>
    </div>

    {{-- Option cards. Submit comes first and Track second because that is the real
         order of the journey: you cannot track a document you have not filed yet.
         Each card reads step → icon → what it is → the action → the detail,
         so the button is reachable without finishing the paragraph. --}}
    <div class="mt-[36px] flex flex-col items-center justify-center gap-[40px] lg:flex-row lg:items-center">

        @foreach ([
            [
                'step' => 'Step 1',
                'href' => route('public.request.create'),
                'icon' => 'icon-submit.svg',
                'title' => 'Submit a Request',
                'action' => 'Submit Now',
                'body' => 'Create and submit a new request online, get a tracking number and QR code by email.',
            ],
            [
                'step' => 'Step 2',
                'href' => route('citizen.track'),
                'icon' => 'icon-track.svg',
                'title' => 'Track a Document',
                'action' => 'Track Now',
                'body' => 'Enter your tracking ID or scan a QR code to check the status and location of your document.',
            ],
        ] as $card)
            {{-- The step label sits outside the card so it reads as a signpost over the
                 pair, and is not mistaken for part of the card's own content. --}}
            <div class="flex w-full max-w-[272px] flex-col items-center">
                {{-- Solid pill rather than plain text: the label has to hold its own
                     beside a card this size, and the lime dot ties it to the card's
                     own call to action. --}}
                <span class="mb-[21px] inline-flex items-center gap-[9px] rounded-full bg-[#01721a] px-[18px] py-[8px] shadow-[0_5px_13px_rgba(0,64,4,0.22)]">
                    <span class="h-[7px] w-[7px] shrink-0 rounded-full bg-[#8dff3c]" aria-hidden="true"></span>
                    <span class="font-display text-[11.5px] font-black uppercase tracking-[0.22em] text-white">
                        {{ $card['step'] }}
                    </span>
                </span>

                <a href="{{ $card['href'] }}"
                   class="group flex h-[304px] w-full flex-col items-center rounded-[16px] border-[0.5px] border-[rgba(0,64,4,0.5)] bg-[rgba(141,255,60,0.2)] px-[28px] pt-[35px] text-center backdrop-blur-[8px] transition hover:-translate-y-1 hover:bg-[rgba(141,255,60,0.32)] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c]">

                    <img src="{{ asset('images/portal/'.$card['icon']) }}" alt="" class="h-[72px] w-[72px]">

                    <h2 class="mt-[19px] font-display text-[20px] font-black text-[#017209]">
                        {{ $card['title'] }}
                    </h2>

                    <span class="mt-[24px] inline-flex h-[33px] w-[105px] items-center justify-center gap-[6px] rounded-[16px] bg-[#01721a] text-[14px] font-bold text-white transition group-hover:bg-[#004004]">
                        {{ $card['action'] }}
                        <img src="{{ asset('images/portal/icon-chevron.svg') }}" alt=""
                             class="h-[12px] w-[12px] brightness-0 invert">
                    </span>

                    <p class="mt-[17.5px] text-[14px] leading-snug text-black">
                        {{ $card['body'] }}
                    </p>
                </a>
            </div>

            @if (! $loop->last)
                {{-- The connector carries the eye from step 1 to step 2. Decorative,
                     so it is hidden from assistive tech — the numbered pills already
                     state the order in text. It turns to point down once the cards
                     stack, and the vertical margin then pays back the height the
                     rotation borrows from the gap. --}}
                <svg data-step-connector viewBox="0 0 92 28" fill="none" aria-hidden="true"
                     class="my-[16px] h-[22px] w-[74px] shrink-0 rotate-90 lg:my-0 lg:mt-[51px] lg:rotate-0">
                    {{-- Lengthening dashes into a solid head, so the pair reads as one
                         path rather than two unrelated choices. --}}
                    <path d="M3 14h7M20 14h12M42 14h14" stroke="#429e00" stroke-width="4" stroke-linecap="round"/>
                    <path d="M64 4 L89 14 L64 24 Z" fill="#01721a"/>
                </svg>
            @endif
        @endforeach

    </div>
</x-citizen-layout>
