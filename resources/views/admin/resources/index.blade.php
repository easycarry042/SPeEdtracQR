<x-app-layout>
    <div class="page-shell page-shell-loose">

        @if(session('success'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('success') }}</div></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] text-ink-soft">Bookable municipal resources. Reservations are managed on the <a href="{{ route('bookings.index') }}" class="text-green underline">Bookings</a> calendar.</p>
            <a href="{{ route('admin.resources.create') }}" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Resource
            </a>
        </div>

        <div class="panel">
            <div class="table-wrap">
                <table class="reg">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Bookings</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($resources as $resource)
                            <tr>
                                <td>
                                    <p class="nm text-ink">{{ $resource->name }}</p>
                                    @if($resource->description)
                                        <p class="mt-0.5 max-w-md truncate text-[11px] text-ink-soft">{{ $resource->description }}</p>
                                    @endif
                                </td>
                                <td class="muted">{{ $resource->bookings_count }}</td>
                                <td>
                                    <span class="pill {{ $resource->is_active ? 'p-green' : 'p-muted' }}">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.resources.edit', $resource) }}" class="cr-btn cr-btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.resources.toggle-active', $resource) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="cr-btn cr-btn-sm">{{ $resource->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}"
                                              onsubmit="return confirm('Delete this resource? Its bookings will be removed too.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-10 text-center text-[13px] text-ink-soft">
                                    No resources yet. Seed the defaults with <code class="code">php artisan db:seed --class=ResourceSeeder</code> or add one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
