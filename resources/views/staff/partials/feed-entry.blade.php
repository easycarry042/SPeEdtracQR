@php $kind = $entry['kind'] ?? 'manual'; @endphp

@if($kind === 'system_group')
    {{-- Collapsed system chunk: one card per ticket, expands to the full timeline. --}}
    <div class="sp-card auto" x-data="{ open: false }">
        <button type="button" class="sp-group-head" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'">
            <span class="sp-sysico" title="System updates">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
            </span>
            <span class="sp-group-title">
                {{ $entry['document']['document_type'] ?? 'Document' }}
                @if($entry['document'])<span class="code">{{ $entry['document']['tracking_number'] }}</span>@endif
                <span class="sp-group-count">{{ $entry['count'] }} update{{ $entry['count'] === 1 ? '' : 's' }}</span>
            </span>
            @if($entry['latest_status'])<x-status-badge :status="$entry['latest_status']" />@endif
            <span class="sp-group-time">{{ optional($entry['at'])->diffForHumans() }}</span>
            <svg class="sp-group-chevron" x-bind:style="open ? 'transform:rotate(180deg)' : ''" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </button>

        <div x-show="open" x-cloak class="sp-group-items">
            @foreach($entry['items'] as $item)
                <div class="sp-group-item">
                    <span>{{ $item['body'] }}</span>
                    <span class="sp-group-time">{{ optional($item['at'])->format('M d, Y h:i A') }}</span>
                </div>
            @endforeach
        </div>

        @if($entry['document'])
            <a href="{{ $entry['document']['url'] }}" class="sp-docchip">
                <x-document-origin :source="$entry['document']['source']" :by="$entry['document']['created_by_name']" :at="$entry['document']['created_at']" />
            </a>
        @endif
    </div>
@else
    @php
        $isManual = $kind === 'manual';
        $isCompletion = $kind === 'completion';
        $typeLabel = $isManual ? ucfirst($entry['type']) : 'Completed';
    @endphp
    <div @class(['sp-card', 'manual' => $isManual, 'completion' => $isCompletion])>
        <div class="sp-card-head">
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="sp-avatar sp-avatar-sm">{{ collect(explode(' ', $entry['author']))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</span>
                <span style="font-size:13px;font-weight:700;color:var(--green-deep);">{{ $entry['author'] }}</span>
                <span class="sp-typetag {{ $isManual ? 'm-'.$entry['type'] : 'm-completed' }}">{{ $typeLabel }}</span>
            </div>
            <span style="font-size:12px;color:var(--ink-soft);">{{ optional($entry['at'])->diffForHumans() }}</span>
        </div>

        @if($isManual)
            <p class="sp-card-body">{{ $entry['body'] }}</p>
        @elseif($isCompletion)
            <p class="sp-card-body">Completed {{ $entry['document']['document_type'] ?? 'a document' }}.</p>
        @endif

        @if($entry['document'])
            <a href="{{ $entry['document']['url'] }}" class="sp-docchip">
                <span class="code">{{ $entry['document']['tracking_number'] }}</span>
                <span style="color:var(--ink-soft);font-size:12px;">{{ $entry['document']['document_type'] }}</span>
                <x-status-badge :status="$entry['document']['status']" />
                <x-document-origin :source="$entry['document']['source']" :by="$entry['document']['created_by_name']" :at="$entry['document']['created_at']" />
            </a>
        @endif
    </div>
@endif
