<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Resources', 'url' => route('admin.resources.index')],
                ['label' => 'Edit ' . $resource->name],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Edit {{ $resource->name }}</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>Edit Resource</h2></div>
            <form method="POST" action="{{ route('admin.resources.update', $resource) }}" class="pb space-y-5 p-6">
                @csrf @method('PUT')
                @include('admin.resources.partials.form', ['resource' => $resource])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.resources.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
