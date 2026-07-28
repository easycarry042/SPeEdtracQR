<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — SPeED TraQR</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f1f2f1] px-6 antialiased text-gray-900">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
            @yield('icon')
        </div>

        <p class="text-sm font-bold uppercase tracking-widest text-emerald-600">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-emerald-950 sm:text-3xl">@yield('heading')</h1>
        <p class="mt-3 text-[15px] leading-relaxed text-gray-600">@yield('message')</p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            @auth
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                    Go to my dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                    Sign in
                </a>
            @endauth
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50">
                Back to home
            </a>
        </div>
    </div>
</body>
</html>
