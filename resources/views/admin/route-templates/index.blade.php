<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Endorsement chains prefilled when a supervisor files an internal request.</p>
            <a href="{{ route('admin.route-templates.create') }}"
               class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                + Add Template
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Template</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Steps</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($templates as $template)
                            <tr class="hover:bg-gray-50/60 transition {{ $template->is_active ? '' : 'opacity-60' }}">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-gray-800">{{ $template->name }}</p>
                                    @if($template->description)
                                        <p class="mt-0.5 max-w-md truncate text-xs text-gray-500">{{ $template->description }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $template->steps_count }}</td>
                                <td class="px-4 py-3">
                                    @if($template->is_active)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">
                                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.route-templates.edit', $template) }}"
                                           class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.route-templates.toggle-active', $template) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition
                                                        {{ $template->is_active
                                                            ? 'border-amber-200 bg-amber-50 text-amber-600 hover:bg-amber-100'
                                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.route-templates.destroy', $template) }}"
                                              onsubmit="return confirm('Delete this template? Existing requests keep their chains.');">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">
                                    No route templates yet. Seed the RA 12009 procurement route with <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs">php artisan db:seed --class=RouteTemplateSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
