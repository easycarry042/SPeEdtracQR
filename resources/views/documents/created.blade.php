<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Document Created</h1>
    </x-slot>

    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        @include('documents.partials.created-card')
    </div>
</x-app-layout>
