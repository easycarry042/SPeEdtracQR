<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Track Document</h1>
    </x-slot>

    <div class="mx-auto max-w-lg py-6">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-8 shadow-lg shadow-gray-200/60 ring-1 ring-gray-100">
            <p class="text-center text-gray-600">Enter tracking number to check status</p>
            <form method="GET" action="{{ route('track.index') }}" class="mt-6 space-y-4">
                <input name="tracking_number" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-lg shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="SPD-YYYYMMDD-XXXXXX">
                <button type="submit" class="w-full rounded-xl bg-emerald-800 py-3 text-lg font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Search
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
