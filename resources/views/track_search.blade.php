<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Document — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    <header class="border-b border-emerald-200/60 bg-[#f1f2f1]/90 backdrop-blur-md" style="box-shadow:0 1px 0 0 rgba(16,101,52,0.08)">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-8 w-8 rounded-xl">
                <span class="text-base font-extrabold tracking-tight text-emerald-950">
                    SPeED <span class="font-bold text-emerald-700">TraQR</span>
                </span>
            </a>
            @auth
                <a href="{{ url('/dashboard') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">Dashboard →</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-xl px-6 py-16">
        <div class="text-center">
            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-emerald-950">Track your document</h1>
            <p class="mt-2 text-gray-500">Enter the tracking number from your receipt.</p>
        </div>

        <form action="{{ route('track.search') }}" method="GET" class="mt-8">
            <div class="flex overflow-hidden rounded-2xl border border-emerald-300 bg-white shadow-lg shadow-emerald-900/10 focus-within:ring-2 focus-within:ring-emerald-500/40">
                <div class="flex items-center pl-5 text-emerald-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    name="tracking_number"
                    value="{{ request('tracking_number') }}"
                    placeholder="SPD-YYYYMMDD-XXXXX"
                    class="flex-1 bg-transparent py-4 pl-3 pr-2 font-mono text-sm tracking-widest text-gray-800 placeholder:font-sans placeholder:tracking-normal placeholder:text-gray-400 focus:outline-none uppercase"
                    autofocus
                >
                <button type="submit" class="m-1.5 rounded-xl bg-emerald-700 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800 active:scale-95">
                    Track
                </button>
            </div>

            @if(request('tracking_number') && !isset($document))
                <div class="mt-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                    </svg>
                    No document found for <span class="font-mono">{{ request('tracking_number') }}</span>. Please check the number and try again.
                </div>
            @endif
        </form>

        <p class="mt-6 text-center text-xs text-gray-400">
            Tracking number format: <span class="font-mono">SPD-YYYYMMDD-NNNNN</span>
        </p>
    </main>

</body>
</html>
