<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Citizen Portal' }} — {{ config('app.name', 'SPeED TraQR') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
    @include('layouts.partials.accessibility-widget')
</head>
<body class="relative min-h-screen bg-white antialiased text-ink">
<<<<<<< HEAD
=======
    {{-- First tab stop: jumps the portal header. --}}
    <a href="#main" class="skip-link">Skip to content</a>

    <div class="pointer-events-none fixed inset-0 -z-10 bg-cover bg-center opacity-15"
         style="background-image: url('{{ asset('images/landing/doodle-pattern.jpg') }}');"></div>
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91

    @include('layouts.partials.portal-header')

    {{-- No footer: the doodle runs to the bottom of the page. --}}
<<<<<<< HEAD
    <main class="mx-auto w-full max-w-[1209.5px] px-4 py-10 sm:px-6">
=======
    <main id="main" class="mx-auto w-full max-w-[1512px] px-4 py-10 sm:px-6">
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
        {{ $slot }}
    </main>

    {{-- Speedy, parked bottom-right. The assistant only ever answers about ONE
         request, so the bubble can't open a chat on its own — it asks which
         request first, then hands off to that document's page where the real
         assistant lives. It used to be a bare link to the lookup, which did
         visibly nothing when you were already on the lookup. --}}
    @unless($attributes->has('hide-speedy'))
        <div class="fixed bottom-6 right-6 z-40" x-data="{ open: false }" @keydown.escape.window="open = false">
            <div x-show="open" x-cloak x-transition.origin.bottom.right
                 class="mb-3 w-[calc(100vw-3rem)] max-w-[264px] rounded-[16px] border border-[rgba(0,64,4,0.5)] bg-white p-5 shadow-[0_14.5px_32px_-16px_rgba(0,64,4,0.55)]"
                 role="dialog" aria-label="Ask Speedy">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-display text-[16px] font-black text-[#017209]">Hi, I'm Speedy</p>
                        <p class="mt-1 text-[14px] leading-snug text-ink-soft">
                            I answer questions about one request at a time. Which one is it?
                        </p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Close"
                            class="-mr-1 -mt-1 rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="GET" action="{{ route('citizen.track') }}" class="mt-4 flex flex-col gap-3">
                    <label for="speedyTracking" class="sr-only">Tracking number</label>
                    <input id="speedyTracking" name="tracking" required autocomplete="off"
                           placeholder="SPD-XXXXXXXX-XXXXXX"
                           class="h-[38.5px] w-full rounded-[9.5px] border border-transparent bg-[rgba(1,114,26,0.18)] px-4 font-mono text-[14px] uppercase tracking-wider text-[#004004] placeholder:font-sans placeholder:tracking-normal placeholder:text-[rgba(0,64,4,0.5)] focus:border-[#01721a] focus:outline-none focus:ring-4 focus:ring-[#8dff3c]/40">
                    <button type="submit"
                            class="h-[38.5px] w-full rounded-[9.5px] bg-[#01721a] text-[14px] font-bold text-white transition hover:bg-[#004004]">
                        Open my request
                    </button>
                </form>

                <p class="mt-3 text-[13px] text-ink-soft">
                    Don't have it to hand?
                    <a href="{{ route('citizen.track') }}" class="font-bold text-[#01721a] underline underline-offset-2">Scan your QR code</a>
                </p>
            </div>

            <button type="button" @click="open = ! open" :aria-expanded="open"
                    class="group ml-auto flex h-[69px] w-[69px] items-center justify-center rounded-full bg-[#135226] shadow-[0_8px_24px_-8px_rgba(0,64,4,0.7)] transition hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c] sm:h-[88px] sm:w-[88px]"
                    aria-label="Ask Speedy about your request">
                <img src="{{ asset('images/portal/speedy.png') }}" alt=""
                     class="h-[48px] w-[59px] object-contain sm:h-[61.5px] sm:w-[76px]">
            </button>
        </div>
    @endunless

    {{-- The shared pop-up error report (window.ErrorAlert). --}}
    <x-error-alert />

    @include('layouts.partials.bfcache-guard')
</body>
</html>
