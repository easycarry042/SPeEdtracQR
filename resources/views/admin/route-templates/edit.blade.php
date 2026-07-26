<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Route Templates', 'url' => route('admin.route-templates.index')],
                ['label' => 'Edit ' . $routeTemplate->name],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Edit {{ $routeTemplate->name }}</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>Template Details</h2></div>
            <form method="POST" action="{{ route('admin.route-templates.update', $routeTemplate) }}" class="pb space-y-5 p-6">
                @csrf @method('PUT')
                @include('admin.route-templates.partials.form')

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.route-templates.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Save Changes</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
