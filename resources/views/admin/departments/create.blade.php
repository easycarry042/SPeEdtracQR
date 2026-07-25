<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <x-breadcrumbs :items="[
                ['label' => 'Departments', 'url' => route('admin.departments.index')],
                ['label' => 'Add department'],
            ]" />
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Add Department</h1>
        </div>
    </x-slot>

    <div class="page-shell page-shell-narrow">
        <section class="panel">
            <div class="ph"><h2>New Department</h2></div>
            <form method="POST" action="{{ route('admin.departments.store') }}" class="pb space-y-5 p-6">
                @csrf
                @include('admin.departments.partials.form-fields', ['department' => null])

                <div class="flex items-center justify-end gap-3 border-t border-hairline pt-4">
                    <a href="{{ route('admin.departments.index') }}" class="cr-btn">Cancel</a>
                    <button type="submit" class="cr-btn cr-btn-primary">Create Department</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
