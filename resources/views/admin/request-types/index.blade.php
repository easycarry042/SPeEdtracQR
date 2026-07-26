<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('success') }}</div></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">Public request types and the supporting requirements citizens must present for each.</p>
            <a href="{{ route('admin.request-types.create') }}" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Request Type
            </a>
        </div>

        <div class="panel">
            <div class="table-wrap">
                <table class="reg">
                    <thead>
                        <tr>
                            <th>Request Type</th>
                            <th>Kind</th>
                            <th>Requirements</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requestTypes as $type)
                            <tr>
                                <td>
                                    <p class="nm text-ink">{{ $type->name }}</p>
                                    @if($type->description)
                                        <p class="mt-0.5 max-w-md truncate text-[11px] text-ink-soft">{{ $type->description }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="pill {{ $type->kind === 'booking' ? 'p-amber' : 'p-muted' }}">{{ ucfirst($type->kind) }}</span>
                                </td>
                                <td class="muted">{{ $type->requirements_count }}</td>
                                <td>
                                    <span class="pill {{ $type->is_active ? 'p-green' : 'p-muted' }}">{{ $type->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.request-types.edit', $type) }}" class="cr-btn cr-btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.request-types.toggle-active', $type) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="cr-btn cr-btn-sm">{{ $type->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.request-types.destroy', $type) }}"
                                              onsubmit="return confirm('Delete this request type? Existing requests keep their snapshotted requirements.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-[13px] text-ink-soft">
                                    No request types yet. Seed the defaults with <code class="code">php artisan db:seed --class=RequestTypeSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
