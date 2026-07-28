<x-app-layout>
    <div class="mx-auto max-w-5xl">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h1 style="font-size:18px;font-weight:700;color:var(--green-deep);margin:0;">Staff directory</h1>
            <form method="GET" action="{{ route('staff.index') }}" class="toolbar">
                @if($role !== '')
                    <input type="hidden" name="role" value="{{ $role }}">
                @endif
                <div class="field">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Name or email" aria-label="Search staff">
                </div>
                <div class="field">
                    <label for="dirSort">Sort</label>
                    <select id="dirSort" name="sort" onchange="this.form.submit()">
                        <option value="name" @selected($sort === 'name')>Name</option>
                        <option value="load" @selected($sort === 'load')>Open load</option>
                        <option value="completed" @selected($sort === 'completed')>Completions</option>
                    </select>
                </div>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Search</button>
                @if($q !== '' || $role !== '' || $sort !== 'name')
                    <a href="{{ route('staff.index') }}" class="cr-btn cr-btn-sm">Reset</a>
                @endif
            </form>
        </div>

        {{-- Role filter chips + result count --}}
        @php
            $chipParams = fn (?string $r) => array_filter([
                'role' => $r,
                'q' => $q !== '' ? $q : null,
                'sort' => $sort !== 'name' ? $sort : null,
            ]);
        @endphp
        <div class="dir-chips" aria-label="Filter by role">
            <a href="{{ route('staff.index', $chipParams(null)) }}" class="dir-chip {{ $role === '' ? 'on' : '' }}">All</a>
            @foreach($roles as $r)
                <a href="{{ route('staff.index', $chipParams($r)) }}" class="dir-chip {{ $role === $r ? 'on' : '' }}">{{ \Illuminate\Support\Str::headline($r) }}</a>
            @endforeach
            <span class="dir-count">{{ $staff->count() }} {{ \Illuminate\Support\Str::plural('person', $staff->count()) }}</span>
        </div>

        <div class="dir-grid">
            @forelse($staff as $person)
                <div class="panel dir-card {{ $person['overdue'] > 0 ? 'has-ovd' : '' }}">
                    {{-- Whole card opens the profile; quick actions sit above the cover link. --}}
                    <a href="{{ $person['url'] }}" class="dir-cover" aria-label="View {{ $person['name'] }}'s profile"></a>

                    <div class="dir-top">
                        <span class="avatar dir-avatar">{{ $person['initials'] ?: '?' }}</span>
                        <div class="dir-meta">
                            <div class="dir-name">{{ $person['name'] }}</div>
                            <div class="dir-sub">
                                @if($person['role'])
                                    <span class="sp-role">{{ \Illuminate\Support\Str::headline($person['role']) }}</span>
                                @endif
                                <span class="dir-active">{{ $person['last_active'] ? 'Active '.$person['last_active'] : 'No activity yet' }}</span>
                            </div>
                        </div>
                        @if($person['overdue'] > 0)
                            <span class="pill p-red dir-ovd">{{ $person['overdue'] }} overdue</span>
                        @endif
                    </div>

                    <div class="dir-stats2">
                        <div class="dir-stat">
                            <span class="lbl">Open</span>
                            <b>{{ $person['assigned'] }}</b>
                            <span class="dir-bar" aria-hidden="true"><i style="width:{{ (int) round($person['assigned'] / $loadMax * 100) }}%"></i></span>
                        </div>
                        <div class="dir-stat">
                            <span class="lbl">Completed</span>
                            <b>{{ $person['completed'] }}</b>
                            @if($person['month'] > 0)
                                <span class="mo">+{{ $person['month'] }} this month</span>
                            @endif
                        </div>
                    </div>

                    @can('assign documents')
                        <a href="{{ route('admin.assignments.index', ['assignee' => $person['id']]) }}" class="dir-action">
                            View assignments
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        </a>
                    @endcan
                </div>
            @empty
                <div class="panel sp-empty" style="grid-column:1/-1;">
                    <p>No staff found{{ $q !== '' ? ' for "'.$q.'"' : '' }}{{ $role !== '' ? ' with the role "'.\Illuminate\Support\Str::headline($role).'"' : '' }}.</p>
                    @if($q !== '' || $role !== '')
                        <p style="margin-top:4px;"><a href="{{ route('staff.index') }}" class="cr-link">Clear filters</a></p>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
