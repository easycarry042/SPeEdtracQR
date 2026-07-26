<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('success') }}</div></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">Endorsement chains prefilled when a supervisor files an internal request.</p>
            <a href="{{ route('admin.route-templates.create') }}" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Template
            </a>
        </div>

        <div class="panel">
            <div class="table-wrap">
                <table class="reg">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Steps</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td>
                                    <p class="nm text-ink">{{ $template->name }}</p>
                                    @if($template->description)
                                        <p class="mt-0.5 max-w-md truncate text-[11px] text-ink-soft">{{ $template->description }}</p>
                                    @endif
                                </td>
                                <td class="muted">{{ $template->steps_count }}</td>
                                <td>
                                    <span class="pill {{ $template->is_active ? 'p-green' : 'p-muted' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.route-templates.edit', $template) }}" class="cr-btn cr-btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.route-templates.toggle-active', $template) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="cr-btn cr-btn-sm">{{ $template->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.route-templates.destroy', $template) }}"
                                              onsubmit="return confirm('Delete this template? Existing requests keep their chains.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-[13px] text-ink-soft">
                                    No route templates yet. Seed the defaults with <code class="code">php artisan db:seed --class=RouteTemplateSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
