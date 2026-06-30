<x-app-layout>
    @php
        $initials = collect(explode(' ', $profileUser->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
        $active = $lastActiveAt && $lastActiveAt->gt(now()->subMinutes(5));

        // Build the 12-month contribution grid (weeks as columns, Sun→Sat rows).
        $counts = $heatmap['counts'];
        $hmax = max(1, $heatmap['max']);
        $cursor = \Illuminate\Support\Carbon::parse($heatmap['since'])->startOfWeek(\Carbon\Carbon::SUNDAY);
        $hend = now()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $weeks = [];
        while ($cursor <= $hend) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $c = $counts[$key] ?? 0;
                $week[] = [
                    'date' => $key,
                    'count' => $c,
                    'level' => $c == 0 ? 0 : min(4, (int) ceil($c / $hmax * 4)),
                    'future' => $cursor->isFuture(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $slaState = $kpis['sla_rate'] === null ? 'muted' : ($kpis['sla_rate'] >= 90 ? 'green' : ($kpis['sla_rate'] >= 75 ? 'amber' : 'red'));
    @endphp

    <div class="sp-grid mx-auto max-w-6xl">

        {{-- ── Region A: identity rail ─────────────────────────────────────── --}}
        <aside class="sp-rail">
            <div class="panel" style="padding:18px;">
                <div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;">
                    <span class="sp-avatar">{{ $initials ?: '?' }}</span>
                    <div>
                        <div style="font-size:17px;font-weight:700;color:var(--green-deep);">{{ $profileUser->name }}</div>
                        @if($roleLabel)
                            <span class="sp-role">{{ \Illuminate\Support\Str::headline($roleLabel) }}</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--ink-soft);">{{ $profileUser->department->name ?? 'No department' }}</div>
                    <div class="sp-status">
                        <span class="dot {{ $active ? 'on' : '' }}"></span>
                        {{ $active ? 'Active now' : ($lastActiveAt ? 'Last active '.$lastActiveAt->diffForHumans() : 'No activity yet') }}
                    </div>
                    @if($isOwn)
                        <a href="{{ route('profile.edit') }}" class="cr-btn cr-btn-sm" style="margin-top:4px;">Edit profile</a>
                    @endif
                </div>

                <div class="sp-divider"></div>

                {{-- KPIs --}}
                <div class="sp-kpis">
                    <div class="sp-kpi"><span>Documents Completed</span><b style="color:var(--green);">{{ number_format($kpis['completed']) }}</b></div>
                    <div class="sp-kpi"><span>Currently Assigned</span><b>{{ $kpis['assigned'] }}</b></div>
                    <div class="sp-kpi"><span>Avg. Processing Time</span><b>{{ $kpis['avg_processing_days'] !== null ? $kpis['avg_processing_days'].'d' : '—' }}</b></div>
                    <div class="sp-kpi"><span>On-time / SLA</span>
                        <span class="pill p-{{ $slaState }}">{{ $kpis['sla_rate'] !== null ? $kpis['sla_rate'].'%' : '—' }}</span>
                    </div>
                </div>

                <div class="sp-divider"></div>

                {{-- Contribution heatmap --}}
                <div>
                    <div style="font-size:11px;font-weight:600;color:var(--ink-soft);margin-bottom:8px;">{{ $heatmap['total'] }} completions in the last year</div>
                    <div class="heat">
                        @foreach($weeks as $week)
                            <div class="heat-col">
                                @foreach($week as $day)
                                    <span class="heat-cell heat-l{{ $day['level'] }} {{ $day['future'] ? 'heat-future' : '' }}"
                                          title="{{ $day['count'] }} on {{ $day['date'] }}"></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="heat-legend"><span>Less</span><span class="heat-cell heat-l0"></span><span class="heat-cell heat-l1"></span><span class="heat-cell heat-l2"></span><span class="heat-cell heat-l3"></span><span class="heat-cell heat-l4"></span><span>More</span></div>
                </div>

                <div class="sp-divider"></div>

                {{-- About --}}
                <div class="sp-about">
                    <div><span>Member since</span><b>{{ $profileUser->created_at->format('M Y') }}</b></div>
                    <div><span>Internal email</span><b>{{ $profileUser->email }}</b></div>
                </div>
            </div>
        </aside>

        {{-- ── Region B: tabbed main column ────────────────────────────────── --}}
        <main class="sp-main">
            @if(session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
            @endif

            <div class="segchips">
                <a href="{{ route('staff.profile', ['user' => $profileUser->id, 'tab' => 'activity']) }}" @class(['on' => $tab === 'activity'])>Activity</a>
                <a href="{{ route('staff.profile', ['user' => $profileUser->id, 'tab' => 'assigned']) }}" @class(['on' => $tab === 'assigned'])>Assigned · {{ $kpis['assigned'] }}</a>
                <a href="{{ route('staff.profile', ['user' => $profileUser->id, 'tab' => 'completions']) }}" @class(['on' => $tab === 'completions'])>Completions</a>
            </div>

            @if($tab === 'activity')
                @if($isOwn)
                    @include('staff.partials.composer', ['ownDocuments' => $ownDocuments])
                @endif

                <div class="sp-feed">
                    @forelse($feed as $entry)
                        @include('staff.partials.feed-entry', ['entry' => $entry])
                    @empty
                        <div class="panel sp-empty">
                            <p style="font-weight:600;color:var(--green-deep);">No activity yet</p>
                            <p>{{ $isOwn ? 'Share an accomplishment or complete a document to start your feed.' : 'This staff member has no activity yet.' }}</p>
                        </div>
                    @endforelse
                </div>

            @elseif($tab === 'assigned')
                <div class="sp-list">
                    @forelse($assigned as $doc)
                        <a href="{{ route('track.show', $doc->tracking_number) }}" @class(['sp-row', 'late' => $doc->overdue])>
                            <div>
                                <div class="ty">{{ $doc->document_type }}</div>
                                <span class="code">{{ $doc->tracking_number }}</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                @if($doc->overdue)<span class="pill p-red">At risk</span>@endif
                                <x-status-badge :status="$doc->status" />
                            </div>
                        </a>
                    @empty
                        <div class="panel sp-empty"><p>Nothing currently assigned.</p></div>
                    @endforelse
                </div>

            @else
                <div class="sp-list">
                    @forelse($completions as $doc)
                        <a href="{{ route('track.show', $doc->tracking_number) }}" class="sp-row">
                            <div>
                                <div class="ty">{{ $doc->document_type }}</div>
                                <span class="code">{{ $doc->tracking_number }}</span>
                            </div>
                            <span style="font-size:12px;color:var(--ink-soft);">{{ optional($doc->completed_at)->format('M d, Y') }}</span>
                        </a>
                    @empty
                        <div class="panel sp-empty"><p>No completed documents yet.</p></div>
                    @endforelse
                </div>
            @endif
        </main>
    </div>
</x-app-layout>
