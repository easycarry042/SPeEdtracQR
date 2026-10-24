@php
    use App\Support\ActivityNarrative;

    $kind = $entry['kind'] ?? 'manual';
@endphp

@if($kind === 'system_group')
    {{-- One document's run of updates, drawn as a timeline: a dot per event on
         a shared rail, the time, then what happened and why. The dot's colour
         is the whole point — a column of gray is a normal day, and the red one
         is the entry worth opening. --}}
    <article class="gs-surface" x-data="{ open: false }">
        <div class="gs-surface-head">
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;min-width:0;">
                <h3 class="gs-surface-title">{{ $entry['document']['document_type'] ?? 'Document' }}</h3>
                @if($entry['document'])
                    <span class="gs-id" translate="no">{{ $entry['document']['tracking_number'] }}</span>
                @endif
            </div>
            @if($entry['latest_status'])<x-status-badge :status="$entry['latest_status']" />@endif
        </div>

        <div class="gs-surface-body">
            @php
                $items = collect($entry['items']);
                // Four is enough to show the shape of the day; the rest stay one
                // click away rather than burying the next document under them.
                $head = $items->take(4);
                $rest = $items->slice(4);
            @endphp

            <ol class="gs-tl">
                @foreach($head as $item)
                    @include('staff.partials.timeline-row', ['row' => ActivityNarrative::for($item['body']), 'at' => $item['at']])
                @endforeach

                @foreach($rest as $item)
                    @include('staff.partials.timeline-row', ['row' => ActivityNarrative::for($item['body']), 'at' => $item['at'], 'folded' => true])
                @endforeach
            </ol>

            @if($rest->isNotEmpty())
                <button type="button" class="gs-tl-more" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
                    <span x-show="! open">Show {{ $rest->count() }} earlier update{{ $rest->count() === 1 ? '' : 's' }}…</span>
                    <span x-show="open" x-cloak>Show fewer</span>
                </button>
            @endif

            @if($entry['document'])
                <a href="{{ $entry['document']['url'] }}" class="gs-docchip">
                    <x-document-origin :source="$entry['document']['source']" :by="$entry['document']['created_by_name']" :at="$entry['document']['created_at']" />
                </a>
            @endif
        </div>
    </article>
@else
    @php
        $isManual = $kind === 'manual';

        // A completion is the one entry everybody scans for, so it is stated
        // rather than inferred from a status chip.
        $row = $isManual
            ? ['tone' => 'note', 'headline' => ucfirst($entry['type']), 'detail' => $entry['body']]
            : ['tone' => 'green', 'headline' => 'Completed '.($entry['document']['document_type'] ?? 'a document'), 'detail' => null];
    @endphp

    <article class="gs-surface">
        <div class="gs-surface-body">
            <ol class="gs-tl">
                @include('staff.partials.timeline-row', ['row' => $row, 'at' => $entry['at'], 'author' => $entry['author']])
            </ol>

            @if($entry['document'])
                <a href="{{ $entry['document']['url'] }}" class="gs-docchip">
                    <span class="gs-id" translate="no">{{ $entry['document']['tracking_number'] }}</span>
                    <span>{{ $entry['document']['document_type'] }}</span>
                    <x-status-badge :status="$entry['document']['status']" />
                </a>
            @endif
        </div>
    </article>
@endif
