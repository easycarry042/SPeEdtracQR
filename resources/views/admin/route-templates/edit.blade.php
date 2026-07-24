<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.route-templates.index') }}" class="text-[13px] font-medium text-green hover:underline">Route Templates</a>
            <span class="text-ink-soft">/</span>
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
