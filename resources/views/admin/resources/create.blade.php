<x-app-layout title="Add Item">
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Resources', 'url' => route('inventory.index')],
                ['label' => 'Add item'],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Add Item</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>New Item</h2></div>
            <form method="POST" action="{{ route('admin.resources.store') }}" class="pb space-y-5 p-6">
                @csrf
                @include('admin.resources.partials.form', ['resource' => null])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('inventory.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Add Item</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
