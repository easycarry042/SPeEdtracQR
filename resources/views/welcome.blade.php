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
                <a href="{{ route('public.request.create') }}" class="rounded-xl bg-emerald-700 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 active:scale-95">
                    Submit a Request
                </a>
                @auth
                    <a href="{{ route('home') }}" class="rounded-xl border border-emerald-300 bg-white px-5 py-2 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50">
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
    @php
        // Drop a real municipality photo at public/images/municipality-hero.jpg
        // (or .png) and it replaces the placeholder automatically — no code change.
        $heroImage = collect(['municipality-hero.jpg', 'municipality-hero.png', 'municipality-hero.webp'])
            ->map(fn ($f) => file_exists(public_path("images/{$f}")) ? asset("images/{$f}") : null)
            ->first(fn ($url) => $url !== null);
    @endphp
    <section class="mx-auto max-w-6xl px-6 pb-16 pt-16 sm:pt-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            {{-- Left: message + primary actions --}}
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-semibold tracking-wider text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Government Document Tracking
                </span>

                <h1 class="mt-6 text-4xl font-extrabold leading-tight tracking-tight text-emerald-950 sm:text-5xl">
                    Your documents,<br>
                    <span class="text-emerald-600">tracked in real time.</span>
                </h1>
                <p class="mt-5 max-w-xl text-lg text-gray-600">
                    Submit a request, get a QR-coded tracking number, and follow it through
                    every department of the municipality — anytime, from any device.
                </p>

                {{-- Two primary buttons --}}
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('citizen.dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 active:scale-95">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Citizen Portal
                    </a>
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white px-6 py-3.5 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50 active:scale-95">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14M9 4H7a2 2 0 00-2 2v12a2 2 0 002 2h2"/>
                        </svg>
                        Staff Login
                    </a>
                </div>

                {{-- Track search — resolves inline (result/error card below the bar).
                     Without JS the form falls back to a normal GET that lands on
                     the public tracking page. --}}
                <div x-data="trackLookup" class="mt-8 max-w-md">
                    <form action="{{ route('track.search') }}" method="GET" @submit.prevent="run()">
                        <div class="flex overflow-hidden rounded-2xl border border-emerald-300 bg-white shadow-sm focus-within:ring-2 focus-within:ring-emerald-500/40">
                            <div class="flex items-center pl-5 text-emerald-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input
                                type="text"
                                name="tracking_number"
                                x-model="q"
                                @input="error = null"
                                placeholder="e.g. SPD-20260728-K7M9Q2"
                                autocomplete="off"
                                class="flex-1 bg-transparent py-3.5 pl-3 pr-2 font-mono text-sm uppercase tracking-widest text-gray-800 placeholder:font-sans placeholder:tracking-normal placeholder:normal-case placeholder:text-gray-400 focus:outline-none"
                            >
                            <button type="submit" x-bind:disabled="loading"
                                    class="m-1.5 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800 active:scale-95 disabled:opacity-60">
                                <span x-show="!loading">Track</span>
                                <span x-show="loading" x-cloak>…</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500" x-show="!result && !error">Enter your tracking number to see its status here.</p>
                    </form>

                    {{-- Error (invalid format / not found) --}}
                    <div x-show="error" x-cloak x-transition class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-left">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <div>
                                <p class="text-sm font-bold text-red-800" x-text="error?.title"></p>
                                <p class="mt-0.5 text-sm text-red-700" x-text="error?.message"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Result card --}}
                    <div x-show="result" x-cloak x-transition class="mt-4 rounded-2xl border border-emerald-200 bg-white p-5 text-left shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-mono text-sm font-bold text-emerald-900" x-text="result?.tracking_number"></p>
                            <span class="rounded-full bg-emerald-100 px-3 py-0.5 text-xs font-semibold text-emerald-800" x-text="result?.status_label"></span>
                        </div>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Request</dt><dd class="text-right font-medium text-gray-800" x-text="result?.document_type"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Department</dt><dd class="text-right font-medium text-gray-800" x-text="result?.department || 'Not yet routed'"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Submitted</dt><dd class="text-right font-medium text-gray-800" x-text="result?.submitted_at"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Last update</dt><dd class="text-right font-medium text-gray-800" x-text="result?.updated_at"></dd></div>
                        </dl>
                        <a :href="result?.url" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            View full details
                        </a>
                    </div>
                </div>
            </div>

            {{-- Right: municipality image (or labeled placeholder) --}}
            <div class="relative">
                @if($heroImage)
                    <img src="{{ $heroImage }}" alt="Municipality"
                         class="float aspect-[4/3] w-full rounded-3xl object-cover shadow-xl shadow-emerald-900/15 ring-1 ring-emerald-200">
                @else
                    <div class="float flex aspect-[4/3] w-full flex-col items-center justify-center rounded-3xl bg-gradient-to-br from-emerald-100 via-emerald-50 to-teal-100 text-center shadow-xl shadow-emerald-900/10 ring-1 ring-emerald-200">
                        <svg class="h-20 w-20 text-emerald-600/70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M4 21V10l8-6 8 6v11M9 21v-6h6v6"/>
                        </svg>
                        <p class="mt-4 text-sm font-semibold text-emerald-800">Municipality photo</p>
                        <p class="mt-1 px-8 text-xs text-emerald-700/70">Add <code class="font-mono">public/images/municipality-hero.jpg</code> to replace this.</p>
                    </div>
                @endif
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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('trackLookup', () => ({
                q: '',
                loading: false,
                result: null,
                error: null,
                lookupUrl: '{{ route('track.lookup') }}',

                run() {
                    const value = this.q.trim().toUpperCase();
                    this.result = null;
                    this.error = null;

                    // Client-side format check for instant feedback (server re-validates).
                    if (! /^(SPD|INT)-\d{8}-[0-9A-Z]{6}$/.test(value)) {
                        this.error = {
                            title: 'Invalid tracking number.',
                            message: 'Please enter a valid tracking number using the correct format (e.g. SPD-20260728-K7M9Q2).',
                        };
                        return;
                    }

                    this.loading = true;
                    fetch(this.lookupUrl + '?tracking_number=' + encodeURIComponent(value), {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(async (r) => {
                            const data = await r.json();
                            if (data.status === 'found') {
                                this.result = data.data;
                            } else {
                                this.error = { title: data.title, message: data.message };
                            }
                        })
                        .catch(() => {
                            this.error = { title: 'Something went wrong.', message: 'Please try again in a moment.' };
                        })
                        .finally(() => { this.loading = false; });
                },
            }));
        });
    </script>

    @include('layouts.partials.bfcache-guard')

</body>
</html>
