{{-- An item's recent changes (InventoryController::history): counts edited,
     loans approved, handed over and returned. Expects $entries. --}}
@if($entries === [])
    <p class="text-[12px] text-ink-soft">Nothing recorded yet.</p>
@else
    <ol class="inv-history">
        @foreach($entries as $entry)
            <li>
                <p class="text-[12.5px] text-ink">{{ $entry['line'] }}</p>
                <p class="text-[11.5px] text-ink-soft">{{ $entry['when'] }}@if($entry['who']) · {{ $entry['who'] }}@endif</p>
            </li>
        @endforeach
    </ol>
@endif
