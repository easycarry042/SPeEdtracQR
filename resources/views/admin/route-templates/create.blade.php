<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Route Templates', 'url' => route('admin.route-templates.index')],
                ['label' => 'Add template'],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Add Template</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>New Route Template</h2></div>
            <form method="POST" action="{{ route('admin.route-templates.store') }}" class="pb space-y-5 p-6">
                @csrf
                @include('admin.route-templates.partials.form', ['routeTemplate' => null])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.route-templates.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Create Template</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
