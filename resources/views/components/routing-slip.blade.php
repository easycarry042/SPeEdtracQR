@props(['slip'])

{{--
    Dashboard "live document journey" routing slip — one document's trip through
    the office, modeled on the physical slip stamped at each desk. Expects the
    flat $slip contract (the same shape the Movements rows use):
      ['type','citizen','code','overdue','status_text','stages','current','public_url']
    Renders with the civic .mvrow card + the shared <x-civic-stepper>.
--}}
@php $overdue = $slip['overdue'] ?? false; @endphp

<div @class(['mvrow', 'late' => $overdue])>
    <div class="eyebrow">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
        Live document journey
    </div>

    <div class="head">
        <div class="who2">
            <span class="docico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>
            </span>
            <div>
                <div class="ty">{{ $slip['type'] }}</div>
                <div class="cz">{{ $slip['citizen'] }}</div>
            </div>
        </div>
        <div class="meta">
            <span class="code">{{ $slip['code'] }}</span>
            <span class="pill {{ $overdue ? 'p-red' : 'p-amber' }}">
                {{ $overdue ? 'Overdue' : 'In transit' }} · {{ $slip['status_text'] }}
            </span>
        </div>
    </div>

    @if (!empty($slip['stages']))
        <x-civic-stepper :stages="$slip['stages']" :current="$slip['current']" />
    @else
        <p style="font-size:12px;color:var(--ink-soft);font-style:italic;margin-top:10px;">No routing path set for this document.</p>
    @endif

    <div class="mvfoot">
        <a href="{{ $slip['public_url'] }}" class="link" target="_blank" rel="noopener">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15l6-6"/><path d="M10 6.5 12 4.5a3.5 3.5 0 0 1 5 5l-2 2"/><path d="M14 17.5 12 19.5a3.5 3.5 0 0 1-5-5l2-2"/></svg>
            Public tracking page
        </a>
    </div>
</div>
