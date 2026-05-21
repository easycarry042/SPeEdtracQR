<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPeED TraQR — Document Tracking System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        .float { animation: float 4s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    {{-- Header --}}
    <header class="sticky top-0 z-50 border-b border-emerald-200/60 bg-[#f1f2f1]/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-9 w-9 rounded-xl">
                <span class="text-lg font-extrabold tracking-tight text-emerald-950">
                    SPeED <span class="font-bold text-emerald-700">TraQR</span>
                </span>
            </div>
            <nav class="flex items-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-xl bg-emerald-700 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 active:scale-95">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl border border-emerald-300 bg-white px-5 py-2 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50">
                        Staff Sign In
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-6xl px-6 pb-16 pt-20 text-center">
        <div class="mx-auto max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Government Document Tracking
            </span>

            <h1 class="mt-6 text-5xl font-extrabold leading-tight tracking-tight text-emerald-950 sm:text-6xl">
                Track your document<br>
                <span class="text-emerald-600">in real time.</span>
            </h1>
            <p class="mt-5 text-lg text-gray-600">
                Enter your tracking number to see exactly where your document is,
                which department it's at, and when it was last moved.
            </p>
        </div>

        {{-- Track search box --}}
        <div class="mx-auto mt-10 max-w-xl">
            <form action="{{ route('track.search') }}" method="GET">
                <div class="flex overflow-hidden rounded-2xl border border-emerald-300 bg-white shadow-lg shadow-emerald-900/10 focus-within:ring-2 focus-within:ring-emerald-500/40">
                    <div class="flex items-center pl-5 text-emerald-500">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="tracking_number"
                        placeholder="e.g. SPD-20260521-00001"
                        class="flex-1 bg-transparent py-4 pl-3 pr-2 font-mono text-sm tracking-widest text-gray-800 placeholder:font-sans placeholder:tracking-normal placeholder:text-gray-400 focus:outline-none uppercase"
                        autofocus
                    >
                    <button type="submit" class="m-1.5 rounded-xl bg-emerald-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800 active:scale-95">
                        Track
                    </button>
                </div>
            </form>
            <p class="mt-3 text-xs text-gray-500">Your tracking number was printed on the receipt given at submission.</p>
        </div>

        {{-- Floating QR illustration --}}
        <div class="mt-14 flex justify-center">
            <div class="float inline-flex h-28 w-28 items-center justify-center rounded-3xl bg-white shadow-xl shadow-emerald-900/15 ring-1 ring-emerald-200">
                <svg class="h-16 w-16 text-emerald-700" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 3h7v7H3V3zm1 1v5h5V4H4zm1 1h3v3H5V5zm7-2h7v7h-7V3zm1 1v5h5V4h-5zm1 1h3v3h-3V5zM3 13h7v7H3v-7zm1 1v5h5v-5H4zm1 1h3v3H5v-3zm8 0h2v2h-2v-2zm2 2h2v2h-2v-2zm-2 2h2v2h-2v-2zm2 2h2v2h-2v-2zm-4-6h2v2h-2v-2zm0 4h2v2h-2v-2zm4-4h2v2h-2v-2z"/>
                </svg>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-white py-20">
        <div class="mx-auto max-w-6xl px-6">
            <h2 class="text-center text-3xl font-bold text-emerald-950">How it works</h2>
            <p class="mx-auto mt-3 max-w-xl text-center text-gray-500">Every document gets a unique QR-coded tracking number. Staff scan it at each department handoff — you see every move.</p>

            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-200/80 bg-[#f8faf8] p-7 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Submit</h3>
                    <p class="mt-2 text-sm text-gray-500">Bring your document to the receiving counter. Staff registers it and prints a QR-coded receipt with your tracking number.</p>
                </div>

                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-7 text-center ring-1 ring-emerald-200">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2M3 17v2a2 2 0 002 2h2m10 0h2a2 2 0 002-2v-2M9 12h.01M12 12h.01M15 12h.01"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Scan at each step</h3>
                    <p class="mt-2 text-sm text-gray-500">Each time your document moves between departments, staff scan the QR code. Every handoff is timestamped and recorded.</p>
                </div>

                <div class="rounded-2xl border border-gray-200/80 bg-[#f8faf8] p-7 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="mt-5 font-bold text-emerald-950">Track anytime</h3>
                    <p class="mt-2 text-sm text-gray-500">Use your tracking number here anytime to see the current department, status, and the full movement history.</p>
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

</body>
</html>
