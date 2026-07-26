<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Document Created</h1>
    </x-slot>

    <div class="page-shell page-shell-wide">
        @include('documents.partials.created-card')
    </div>
</x-app-layout>
