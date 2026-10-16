<x-citizen-layout>
    <x-slot name="title">Citizen Portal</x-slot>

    {{-- Hero / Welcome --}}
    <div class="pt-[45px] text-center">
        {{-- Two-citizen mark: this portal is the public's door, not staff's.
             Drawn as two overlapping figures, as in the design. --}}
        <div class="flex items-end justify-center">
            <img src="{{ asset('images/portal/user-female.svg') }}" alt="" class="h-[63px] w-[68px] -mr-[21px]">
            <img src="{{ asset('images/portal/user-male.svg') }}" alt="" class="h-[63px] w-[70px]">
        </div>

        <h1 class="mt-[18px] font-display text-[32px] font-black text-[#004004] sm:text-[40px]">
            Welcome to the Citizen Portal
        </h1>
        <p class="mt-[6px] text-[26px] font-semibold text-[#429e00] sm:text-[35px]">
            How can we help you today?
        </p>
    </div>

    {{-- Option cards. Submit comes first and Track second because that is the real
         order of the journey: you cannot track a document you have not filed yet.
         Each card reads step → icon → what it is → the action → the detail,
         so the button is reachable without finishing the paragraph. --}}
    <div class="mt-[80px] flex flex-wrap items-stretch justify-center gap-[50px]">

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
            <div class="flex w-full max-w-[340px] flex-col items-center">
                <p class="mb-[14px] font-display text-[17px] font-black uppercase tracking-[0.12em] text-[#004004]">
                    {{ $card['step'] }}
                </p>

                <a href="{{ $card['href'] }}"
                   class="group flex h-[380px] w-full flex-col items-center rounded-[20px] border-[0.5px] border-[rgba(0,64,4,0.5)] bg-[rgba(141,255,60,0.2)] px-[35px] pt-[44px] text-center backdrop-blur-[10px] transition hover:-translate-y-1 hover:bg-[rgba(141,255,60,0.32)] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c]">

                    <img src="{{ asset('images/portal/'.$card['icon']) }}" alt="" class="h-[90px] w-[90px]">

                    <h2 class="mt-[24px] font-display text-[25px] font-black text-[#017209]">
                        {{ $card['title'] }}
                    </h2>

                    <span class="mt-[30px] inline-flex h-[41px] w-[131px] items-center justify-center gap-[6px] rounded-[20px] bg-[#01721a] text-[15px] font-bold text-white transition group-hover:bg-[#004004]">
                        {{ $card['action'] }}
                        <img src="{{ asset('images/portal/icon-chevron.svg') }}" alt=""
                             class="h-[15px] w-[15px] brightness-0 invert">
                    </span>

                    <p class="mt-[22px] text-[15px] leading-snug text-black">
                        {{ $card['body'] }}
                    </p>
                </a>
            </div>
        @endforeach

    </div>
</x-citizen-layout>
