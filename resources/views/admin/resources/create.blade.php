<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Resources', 'url' => route('admin.resources.index')],
                ['label' => 'Add resource'],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Add Resource</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>New Resource</h2></div>
            <form method="POST" action="{{ route('admin.resources.store') }}" class="pb space-y-5 p-6">
                @csrf
                @include('admin.resources.partials.form', ['resource' => null])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.resources.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Create Resource</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
