<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Request Types', 'url' => route('admin.request-types.index')],
                ['label' => 'Add request type'],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Add Request Type</h1>
        </div>
    </x-slot>

    <div class="page-shell">
        <section class="panel">
            <div class="ph"><h2>New Request Type</h2></div>
            <form method="POST" action="{{ route('admin.request-types.store') }}" class="pb space-y-5 p-6">
                @csrf
                @include('admin.request-types.partials.form', ['requestType' => null])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.request-types.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Create Request Type</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
