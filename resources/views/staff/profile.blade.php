{{-- The tab title names whose profile this is — a row of pinned profiles has
     to be tellable apart, and "Staff Profile" on all of them is not. --}}
<x-app-layout :title="$profileUser->name">
    @php
        $active = $lastActiveAt && $lastActiveAt->gt(now()->subMinutes(5));
        $role = $roleLabel ? \Illuminate\Support\Str::headline($roleLabel) : null;

        // Build the 12-month contribution grid (weeks as columns, Sun→Sat rows),
        // and note where each month starts so the labels sit over the right
        // column rather than being spread evenly and drifting out of step.
        $counts = $heatmap['counts'];
        $hmax = max(1, $heatmap['max']);
        $cursor = \Illuminate\Support\Carbon::parse($heatmap['since'])->startOfWeek(\Carbon\Carbon::SUNDAY);
        $hend = now()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $weeks = [];
        $monthLabels = [];
        $seenMonth = null;
        while ($cursor <= $hend) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $c = $counts[$key] ?? 0;

                // A month is labelled at the first week its 1st falls inside.
                if ($seenMonth !== $cursor->format('Y-m') && $cursor->day <= 7 && ! $cursor->isFuture()) {
                    $seenMonth = $cursor->format('Y-m');
                    $monthLabels[count($weeks)] = $cursor->format('M');
                }

                $week[] = [
                    'date' => $key,
                    'label' => $cursor->format('M j, Y'),
                    'count' => $c,
                    'level' => $c == 0 ? 0 : min(4, (int) ceil($c / $hmax * 4)),
                    'future' => $cursor->isFuture(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        // The grid is one image to assistive tech: 365 unlabelled cells read
        // one by one is noise, so the whole thing carries a single sentence.
        $heatLabel = $heatmap['total'] === 0
            ? 'Completion activity for the last year: no completions yet.'
            : $heatmap['total'].' completion'.($heatmap['total'] === 1 ? '' : 's').' in the last year'
                .($heatmap['peak_day']
                    ? ', the most on '.\Illuminate\Support\Carbon::parse($heatmap['peak_day'])->format('F j, Y')
                      .' with '.$heatmap['peak_day_count'].'.'
                    : '.');

        // The strip under the grid. Each entry is [label, value, sub] — sub is
        // the quiet second line, omitted when there is nothing to qualify.
        $activityStats = [
            ['Most handled', $heatmap['busiest_type'] ?? '—',
                $heatmap['busiest_type'] ? $heatmap['busiest_type_count'].' completed' : 'No completions yet'],
            ['Last 30 days', $heatmap['last30'], $heatmap['last30'] === 1 ? 'request' : 'requests'],
            ['Peak day',
                $heatmap['peak_day'] ? \Illuminate\Support\Carbon::parse($heatmap['peak_day'])->format('M j') : '—',
                $heatmap['peak_day'] ? $heatmap['peak_day_count'].' in one day' : null],
            ['Peak month',
                $heatmap['peak_month'] ? \Illuminate\Support\Carbon::parse($heatmap['peak_month'].'-01')->format('M Y') : '—',
                $heatmap['peak_month'] ? $heatmap['peak_month_count'].' completed' : null],
            ['Current streak', $heatmap['current_streak'],
                $heatmap['current_streak'] === 1 ? 'day running' : 'days running'],
            ['Longest streak', $heatmap['longest_streak'],
                $heatmap['longest_streak'] === 1 ? 'day' : 'days'],
        ];

        $slaTone = $kpis['sla_rate'] === null ? '' : ($kpis['sla_rate'] >= 90 ? 'gs-badge-green' : ($kpis['sla_rate'] >= 75 ? 'gs-badge-amber' : 'gs-badge-red'));

        $tabs = [
            'activity' => ['label' => 'Activity', 'count' => null],
            'assigned' => ['label' => 'Assigned', 'count' => $kpis['assigned']],
            'completions' => ['label' => 'Completions', 'count' => null],
        ];
    @endphp

    <div class="gs-root">

        {{-- ── Identity header ──────────────────────────────────────────── --}}
        <header class="gs-pagehead">
            <div class="gs-pagehead-id">
                <x-user-avatar :user="$profileUser" class="gs-avatar" />
                <div class="gs-pagehead-text">
                    <h1>{{ $profileUser->name }}</h1>
                    <div class="gs-pagehead-meta">
                        @if($role)<span>{{ $role }}</span><span aria-hidden="true">·</span>@endif
                        <span class="gs-status {{ $active ? 'is-live' : '' }}">
                            {{ $active ? 'Active now' : ($lastActiveAt ? 'Last active '.$lastActiveAt->diffForHumans() : 'No activity yet') }}
                        </span>
                    </div>
                </div>
            </div>

            @if($isOwn)
                <div class="gs-pagehead-actions">
                    <a href="{{ route('profile.edit') }}" class="gs-btn">Edit Profile</a>
                </div>
            @endif
        </header>

        @if(session('status'))
            <p class="gs-notice" role="status" aria-live="polite" style="margin-top:16px;">{{ session('status') }}</p>
        @endif

        <div class="gs-layout">

            {{-- ── Work ─────────────────────────────────────────────────── --}}
            <main class="gs-main">

                @if($isOwn)
                    <section class="gs-surface" aria-labelledby="gs-worklist-h">
                        <div class="gs-surface-head">
                            <div>
                                <h2 class="gs-surface-title" id="gs-worklist-h">My Worklist</h2>
                                <p class="gs-surface-sub">Requests assigned to you and still open.</p>
                            </div>
                            <span class="gs-count">{{ $worklist->count() }}</span>
                        </div>
                        @if($worklist->isNotEmpty())
                            <div class="gs-rows">
                                @foreach($worklist as $doc)
                                    <a href="{{ route('track.show', $doc->tracking_number) }}" class="gs-row">
                                        <span class="gs-row-main">
                                            <span class="gs-row-title">{{ $doc->document_type }}</span>
                                            <span class="gs-row-meta">
                                                <span class="gs-id" translate="no">{{ $doc->tracking_number }}</span>
                                                <x-document-origin :source="$doc->source" :by="$doc->creator?->name" :at="$doc->created_at" />
                                            </span>
                                            @include('staff.partials.hold-note', ['doc' => $doc])
                                        </span>
                                        <span class="gs-row-side"><x-status-badge :status="$doc->status" /></span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="gs-empty">
                                <p class="gs-empty-title">Your worklist is clear</p>
                                <p class="gs-empty-body">Requests assigned to you appear here. Accept one from Unclaimed below to start work on it.</p>
                            </div>
                        @endif
                    </section>

                    @if($unclaimed->isNotEmpty())
                        <section class="gs-surface" aria-labelledby="gs-unclaimed-h">
                            <div class="gs-surface-head">
                                <div>
                                    <h2 class="gs-surface-title" id="gs-unclaimed-h">Unclaimed</h2>
                                    <p class="gs-surface-sub">Waiting for someone in your office to take them on.</p>
                                </div>
                                <span class="gs-count">{{ $unclaimed->count() }}</span>
                            </div>
                            <div class="gs-rows">
                                @foreach($unclaimed as $doc)
                                    <div class="gs-row">
                                        <span class="gs-row-main">
                                            <span class="gs-row-title">{{ $doc->document_type }}</span>
                                            <span class="gs-row-meta">
                                                <span class="gs-id" translate="no">{{ $doc->tracking_number }}</span>
                                            </span>
                                        </span>
                                        @can('accept documents')
                                            <form method="POST" action="{{ route('documents.accept', $doc) }}" class="gs-row-side">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="gs-btn gs-btn-primary">
                                                    Accept<span class="sr-only"> {{ $doc->document_type }} {{ $doc->tracking_number }}</span>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif

                {{-- ── Completion activity ──────────────────────────────────
                     Full width, not squeezed into the rail: 53 weeks across a
                     300px column is unreadable, which is what made the old one
                     look like decoration rather than information. --}}
                <section class="gs-surface gs-activity" aria-labelledby="gs-heat-h">
                    <div class="gs-surface-head">
                        <div>
                            <h2 class="gs-surface-title" id="gs-heat-h">Completion Activity</h2>
                            <p class="gs-surface-sub">
                                <span class="gs-num">{{ number_format($heatmap['total']) }}</span>
                                {{ \Illuminate\Support\Str::plural('request', $heatmap['total']) }} completed in the last year
                            </p>
                        </div>
                    </div>

                    <div class="gs-surface-body">
                        <div class="gs-heat-scroll">
                            <div class="gs-heat-grid">
                                {{-- Month labels, positioned over the week each month opens in. --}}
                                <div class="gs-heat-months" aria-hidden="true">
                                    @foreach($weeks as $w => $week)
                                        <span class="gs-heat-month">{{ $monthLabels[$w] ?? '' }}</span>
                                    @endforeach
                                </div>

                                <div class="gs-heat-body">
                                    <div class="gs-heat-days" aria-hidden="true">
                                        <span>Mon</span><span>Wed</span><span>Fri</span>
                                    </div>

                                    <div class="gs-heat" role="img" aria-label="{{ $heatLabel }}">
                                        @foreach($weeks as $week)
                                            <div class="gs-heat-col" aria-hidden="true">
                                                @foreach($week as $day)
                                                    <span class="gs-heat-cell gs-heat-l{{ $day['level'] }} {{ $day['future'] ? 'gs-heat-future' : '' }}"
                                                          title="{{ $day['count'] }} {{ \Illuminate\Support\Str::plural('completion', $day['count']) }} on {{ $day['label'] }}"></span>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="gs-heat-legend" aria-hidden="true">
                            <span>Less</span>
                            <span class="gs-heat-cell gs-heat-l0"></span>
                            <span class="gs-heat-cell gs-heat-l1"></span>
                            <span class="gs-heat-cell gs-heat-l2"></span>
                            <span class="gs-heat-cell gs-heat-l3"></span>
                            <span class="gs-heat-cell gs-heat-l4"></span>
                            <span>More</span>
                        </p>
                    </div>

                    {{-- What the grid cannot say on its own. --}}
                    <dl class="gs-activity-stats">
                        @foreach($activityStats as [$label, $value, $sub])
                            <div class="gs-activity-stat">
                                <dt>{{ $label }}</dt>
                                <dd class="gs-num">{{ $value }}</dd>
                                @if($sub)<p>{{ $sub }}</p>@endif
                            </div>
                        @endforeach
                    </dl>
                </section>

                {{-- Each tab is its own URL, so it survives refresh and sharing. --}}
                <nav class="gs-tabs" aria-label="Profile sections">
                    @foreach($tabs as $key => $meta)
                        <a href="{{ route('staff.profile', ['user' => $profileUser->id, 'tab' => $key]) }}"
                           class="gs-tab"
                           @if($tab === $key) aria-current="page" @endif>
                            {{ $meta['label'] }}
                            @if($meta['count'] !== null)<span class="gs-count">{{ $meta['count'] }}</span>@endif
                        </a>
                    @endforeach
                </nav>

                @if($tab === 'activity')
                    @if($isOwn)
                        @include('staff.partials.composer', ['ownDocuments' => $ownDocuments])
                    @endif

                    <div class="gs-feed">
                        @forelse($feed as $entry)
                            @include('staff.partials.feed-entry', ['entry' => $entry])
                        @empty
                            <div class="gs-surface">
                                <div class="gs-empty">
                                    <p class="gs-empty-title">No activity yet</p>
                                    <p class="gs-empty-body">
                                        {{ $isOwn
                                            ? 'Completing a request adds it here automatically. You can also post a note or milestone with the box above.'
                                            : 'Once this staff member completes a request or posts a note, it will show up here.' }}
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>

                @elseif($tab === 'assigned')
                    <section class="gs-surface">
                        @if($assigned->isNotEmpty())
                            <div class="gs-rows">
                                @foreach($assigned as $doc)
                                    <a href="{{ route('track.show', $doc->tracking_number) }}" @class(['gs-row', 'is-late' => $doc->overdue])>
                                        <span class="gs-row-main">
                                            <span class="gs-row-title">{{ $doc->document_type }}</span>
                                            <span class="gs-row-meta">
                                                <span class="gs-id" translate="no">{{ $doc->tracking_number }}</span>
                                                <x-document-origin :source="$doc->source" :by="$doc->creator?->name" :at="$doc->created_at" />
                                            </span>
                                            @include('staff.partials.hold-note', ['doc' => $doc])
                                        </span>
                                        <span class="gs-row-side">
                                            @if($doc->overdue)<span class="gs-badge gs-badge-red">At Risk</span>@endif
                                            <x-status-badge :status="$doc->status" />
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="gs-empty">
                                <p class="gs-empty-title">Nothing assigned</p>
                                <p class="gs-empty-body">{{ $isOwn ? 'Requests you accept or that a supervisor assigns to you will be listed here.' : 'This staff member has no open requests right now.' }}</p>
                            </div>
                        @endif
                    </section>

                @else
                    <section class="gs-surface">
                        @if($completions->isNotEmpty())
                            <div class="gs-rows">
                                @foreach($completions as $doc)
                                    <a href="{{ route('track.show', $doc->tracking_number) }}" class="gs-row">
                                        <span class="gs-row-main">
                                            <span class="gs-row-title">{{ $doc->document_type }}</span>
                                            <span class="gs-row-meta">
                                                <span class="gs-id" translate="no">{{ $doc->tracking_number }}</span>
                                                <x-document-origin :source="$doc->source" :by="$doc->creator?->name" :at="$doc->created_at" />
                                            </span>
                                        </span>
                                        <span class="gs-row-side">
                                            @if($doc->completed_at)
                                                <time class="gs-num" datetime="{{ $doc->completed_at->toIso8601String() }}" style="font-size:13px;color:var(--gs-mute);">
                                                    {{ $doc->completed_at->format('M j, Y') }}
                                                </time>
                                            @endif
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="gs-empty">
                                <p class="gs-empty-title">No completed requests yet</p>
                                <p class="gs-empty-body">{{ $isOwn ? 'Requests you finish are recorded here, and count toward the stats on this page.' : 'This staff member has not completed a request yet.' }}</p>
                            </div>
                        @endif
                    </section>
                @endif
            </main>

            {{-- ── Identity meta ────────────────────────────────────────── --}}
            <aside class="gs-aside">
                <section class="gs-surface" aria-labelledby="gs-stats-h">
                    <div class="gs-surface-head">
                        <h2 class="gs-surface-title" id="gs-stats-h">Stats</h2>
                    </div>
                    <dl class="gs-stats">
                        <div class="gs-stat">
                            <dt>Completed</dt>
                            <dd>{{ number_format($kpis['completed']) }}</dd>
                        </div>
                        <div class="gs-stat">
                            <dt>Currently Assigned</dt>
                            <dd>{{ number_format($kpis['assigned']) }}</dd>
                        </div>
                        <div class="gs-stat">
                            <dt>Avg. Processing</dt>
                            <dd>{{ $kpis['avg_processing_days'] !== null ? $kpis['avg_processing_days'].' days' : '—' }}</dd>
                        </div>
                        <div class="gs-stat">
                            <dt>On Time</dt>
                            <dd>
                                @if($kpis['sla_rate'] !== null)
                                    <span class="gs-badge {{ $slaTone }} gs-num">{{ $kpis['sla_rate'] }}% on time</span>
                                @else
                                    <span class="gs-badge">Not enough data</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </section>


                <section class="gs-surface" aria-labelledby="gs-about-h">
                    <div class="gs-surface-head">
                        <h2 class="gs-surface-title" id="gs-about-h">Details</h2>
                    </div>
                    <dl class="gs-stats">
                        <div class="gs-stat">
                            <dt>Member since</dt>
                            <dd><time datetime="{{ $profileUser->created_at->toIso8601String() }}">{{ $profileUser->created_at->format('M Y') }}</time></dd>
                        </div>
                        <div class="gs-stat" style="flex-direction:column;align-items:flex-start;gap:2px;">
                            <dt>Internal email</dt>
                            <dd style="font-size:13px;font-weight:400;overflow-wrap:anywhere;">
                                <a href="mailto:{{ $profileUser->email }}" class="gs-link">{{ $profileUser->email }}</a>
                            </dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
