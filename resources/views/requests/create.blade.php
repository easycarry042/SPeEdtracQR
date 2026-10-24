<x-app-layout title="File Internal Request">
    {{-- The filing form on its own page — the same form the Internal page opens
         in a modal (requests/partials/file-form). Kept for direct links and as
         the no-JavaScript route from the "+" button. --}}
    <div class="page-shell">
        <div class="dm-card mx-auto w-full max-w-[940px]">
            @include('requests.partials.file-form')
        </div>
    </div>
</x-app-layout>
