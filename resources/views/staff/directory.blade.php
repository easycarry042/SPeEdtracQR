<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 style="font-size:18px;font-weight:700;color:var(--green-deep);margin:0;">Staff directory</h1>
            <form method="GET" action="{{ route('staff.index') }}" class="toolbar">
                <div class="field">
                    <label>Find</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Name or email" autofocus>
                </div>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Search</button>
                @if($q !== '')
                    <a href="{{ route('staff.index') }}" class="cr-btn cr-btn-sm">Reset</a>
                @endif
            </form>
        </div>

        <div class="dir-grid">
            @forelse($staff as $person)
                <a href="{{ $person['url'] }}" class="panel dir-card">
                    <span class="avatar dir-avatar">{{ $person['initials'] ?: '?' }}</span>
                    <div class="dir-meta">
                        <div class="dir-name">{{ $person['name'] }}</div>
                        @if($person['role'])
                            <span class="sp-role">{{ \Illuminate\Support\Str::headline($person['role']) }}</span>
                        @endif
                    </div>
                    <div class="dir-stats">
                        <div><b>{{ $person['assigned'] }}</b><span>Assigned</span></div>
                        <div><b>{{ $person['completed'] }}</b><span>Completed</span></div>
                    </div>
                </a>
            @empty
                <div class="panel sp-empty" style="grid-column:1/-1;">
                    <p>No staff found{{ $q !== '' ? ' for "'.$q.'"' : '' }}.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
