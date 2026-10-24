{{-- The super admin's catalog controls for one item, inside its expanded row
     on the Resources page (Admin\ResourceController). Expects $resource. --}}
<div class="mt-4 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
    <span class="inv-field-label !mb-0 mr-1">Catalog</span>
    <a href="{{ route('admin.resources.edit', $resource) }}" class="cr-btn cr-btn-sm">Edit details</a>
    <form method="POST" action="{{ route('admin.resources.toggle-active', $resource) }}">
        @csrf @method('PATCH')
        <button type="submit" class="cr-btn cr-btn-sm">{{ $resource->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
    <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}"
          onsubmit="return confirm(@js('Delete '.$resource->name.'? Its booking history is removed too. Deactivating keeps the history.'))">
        @csrf @method('DELETE')
        <button type="submit" class="cr-btn cr-btn-sm cr-btn-danger">Delete</button>
    </form>
    <span class="text-[12px] text-ink-soft">{{ $resource->is_active ? 'Deactivating stops new requests for it; its history stays.' : 'Inactive — citizens cannot ask for it.' }}</span>
</div>
