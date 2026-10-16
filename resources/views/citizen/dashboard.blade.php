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

    {{-- Option cards. Each one reads icon → what it is → the action → the detail,
         so the button is reachable without finishing the paragraph. --}}
    <div class="mt-[77px] flex flex-wrap justify-center gap-[40px]">

        @foreach ([
            [
                'href' => route('citizen.track'),
                'icon' => 'icon-track.svg',
                'title' => 'Track a Document',
                'action' => 'Track Now',
                'body' => 'Enter your tracking ID or scan a QR code to check the status and location of your document.',
            ],
            [
                'href' => route('public.request.create'),
                'icon' => 'icon-submit.svg',
                'title' => 'Submit a Request',
                'action' => 'Submit Now',
                'body' => 'Create and submit a new request online, get a tracking number and QR code by email.',
            ],
        ] as $card)
            <a href="{{ $card['href'] }}"
               class="group flex h-[304px] w-full max-w-[272px] flex-col items-center rounded-[16px] border-[0.5px] border-[rgba(0,64,4,0.5)] bg-[rgba(141,255,60,0.2)] px-[28px] pt-[35px] text-center backdrop-blur-[8px] transition hover:-translate-y-1 hover:bg-[rgba(141,255,60,0.32)] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c]">

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
        @endforeach

    </div>
</x-citizen-layout>
