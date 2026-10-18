<x-app-layout title="Internal Requests" fixed-height>
    {{-- No page action here. The shared header already carries a labelled
         "File Request" button on every page; a second, icon-only plus sitting
         beside the title was the same action twice, and the bare one gave no
         clue which. One affordance, with words on it. --}}

    @include('requests.partials.inbox')
</x-app-layout>
