<x-app-layout>
    {{-- Shown only when no documents are currently in progress; otherwise
         /track redirects straight to the latest in-progress document. --}}
    <div class="mx-auto max-w-lg py-6">
        <div class="rounded-2xl border border-gray-200/90 bg-white p-8 text-center shadow-lg shadow-gray-200/60 ring-1 ring-gray-100">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
            </div>
            <p class="mt-3 font-semibold text-gray-700">No documents in progress</p>
            <p class="mt-1 text-sm text-gray-500">Documents currently being processed will show up here automatically.</p>

            <form method="GET" action="{{ route('track.index') }}" class="mt-6 space-y-3 border-t border-gray-100 pt-6">
                <p class="text-sm text-gray-600">Or look up any document by tracking number</p>
                <input name="tracking_number" class="w-full rounded-xl border border-gray-200 px-4 py-3 shadow-sm transition focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" placeholder="SPD-YYYYMMDD-XXXXXX">
                <button type="submit" class="w-full rounded-xl bg-emerald-800 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Search
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
