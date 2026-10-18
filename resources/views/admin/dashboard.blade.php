<x-app-layout title="Command Center">
    @php
        /* ---------- shared helpers ---------- */
        // Sparkline: array<int|float> -> polyline points in a 0..100 x 0..30 box.
        $spark = function (array $vals): ?string {
            $n = count($vals);
            if ($n === 0) {
                return null;
            }
            $max = max(1, max($vals));
            $pts = [];
            foreach ($vals as $i => $v) {
                $x = $n > 1 ? round($i * 100 / ($n - 1), 2) : 0;
                $y = round(28 - ($v / $max) * 26, 2);
                $pts[] = "{$x},{$y}";
            }
            return implode(' ', $pts);
        };

        // A donut slice list from [label,value,color] tuples (pathLength=100 circles).
        $donut = function (array $segments): array {
            $total = array_sum(array_map(fn ($s) => $s[1], $segments));
            $slices = [];
            $acc = 0;
            foreach ($segments as [$label, $value, $color]) {
                $pct = $total ? round($value / $total * 100, 2) : 0;
                $slices[] = compact('label', 'value', 'color', 'pct') + ['offset' => -1 * $acc];
                $acc += $pct;
            }
            return ['total' => $total, 'slices' => $slices];
        };

        $fmtDelta = function (?array $d): string {
            if (! $d) {
                return '';
            }
            $arrow = $d['dir'] === 'up' ? '▲' : '▼';
            $mag = $d['pct'] !== null ? $d['pct'].'%' : (($d['abs'] >= 0 ? '+' : '').$d['abs']);
            return $arrow.' '.$mag;
        };

        /* ---------- region C chart data ---------- */
        // Throughput grouped bars (both sides have one)
        $tputMax = max(1, collect($throughput)->max('created') ?? 0, collect($throughput)->max('completed') ?? 0);

        $isInternal = $filters['view'] === 'internal';
    @endphp

    <div class="page-shell">

        {{-- ── Region A: header + filters ──────────────────────────────────── --}}
        <div class="an-header">
            <div>
                <h1 style="font-size:18px;font-weight:700;color:var(--green-deep);margin:0;">City of San Pedro · Command center</h1>
                <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px;display:flex;align-items:center;gap:6px;">
                    <span class="live-dot"></span> live · updated {{ $updatedAt->diffForHumans() }}
                </div>
            </div>
            <form method="GET" action="{{ route('admin.dashboard') }}" class="toolbar">
                <input type="hidden" name="view" value="{{ $filters['view'] }}">
                <x-select-menu name="range" label="Range" submit-on-change panel-width="w-48" class="w-48"
                               :options="[7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days']"
                               :selected="$filters['range']"/>
                {{-- Every internal request shares one type, so the slice is
                     citizen-only. --}}
                @unless ($isInternal)
                    <x-select-menu name="document_type" label="Type" submit-on-change panel-width="w-56" class="w-56"
                                   :options="collect($documentTypes)->mapWithKeys(fn ($t) => [$t => $t])->prepend('All', '')->all()"
                                   :selected="$filters['document_type']"/>
                @endunless
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Apply</button>
                @if ($filters['active'])
                    <a href="{{ route('admin.dashboard', ['view' => $filters['view']]) }}" class="cr-btn cr-btn-sm">Reset</a>
                @endif
                <a href="{{ route('admin.dashboard.export', array_filter(['view' => $filters['view'], 'range' => $filters['range'], 'document_type' => $filters['document_type']])) }}"
                   class="cr-btn cr-btn-sm" title="Download these figures as a CSV (opens in Excel)">
                    Export CSV
                </a>
            </form>
        </div>

        {{-- "Notified … about SPD-…" after a notification goes out. --}}
        @if (session('status'))
            <div class="dm-callout dm-callout-green" role="status">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        {{-- The two sides of the command center. Citizen requests and
             office-to-office requests used to be counted together, so INT-
             requests sat in the citizen overdue list judged by citizen stage
             clocks. Now each side is read on its own. --}}
        <nav class="st-tabs self-start" aria-label="Which requests">
            <a href="{{ route('admin.dashboard', ['view' => 'citizen', 'range' => $filters['range']]) }}"
               class="{{ $isInternal ? '' : 'on' }}" @unless ($isInternal) aria-current="page" @endunless>Citizen requests</a>
            <a href="{{ route('admin.dashboard', ['view' => 'internal', 'range' => $filters['range']]) }}"
               class="{{ $isInternal ? 'on' : '' }}" @if ($isInternal) aria-current="page" @endif>Internal requests</a>
        </nav>

        @if ($isInternal)
            @include('admin.partials.internal-dashboard')
        @else
        @php
            // Requests by stage, for the panel beside the office bars. One
            // colour per stage (Pending, In Progress, In Review, Approved,
            // Completed, Returned, On Hold, Denied).
            $stageColors = ['#cdd9d1', '#c79a3e', '#e0b15a', '#46a268', '#167a3a', '#b45309', '#d8920f', '#c98a8a'];
            $sd = $donut($statusDistribution->values()->map(fn ($r, $i) => [$r['label'], $r['value'], $stageColors[$i % count($stageColors)]])->all());

            // Processing time by stage — tallest bar is the bottleneck (red)
            $stageMaxHours = max(0.01, collect($timeByStage)->max('hours') ?? 0);

            // Document type breakdown
            $typeMax = max(1, collect($typeBreakdown)->max('value') ?? 0);

            // Staff workload
            $wlMax = max(1, $staffWorkload->max(fn ($r) => $r['completed'] + $r['active']) ?? 0);
        @endphp

        {{-- ── Region B: KPI vitals ─────────────────────────────────────────
             Six counts on the same gradient tiles the staff and supervisor
             dashboards use, so they read as one product. The first four are
             one set: the requests filed in the selected range, by where each
             is now, so they add back to the total. Average processing time and
             Overdue sit beside them in the same dress; they used to be a row
             of small white cards under the panels, a second style of KPI with
             an on-time "health" pill whose 85% / 70% cut-offs no one had set.
             ───────────────────────────────────────────────────────────────── --}}
        @php
            $delta = fn (string $key) => ! empty($kpis[$key]['delta']) ? $fmtDelta($kpis[$key]['delta']) : null;
        @endphp

        <p class="cc-tiles-caption" data-tile-caption>
            Requests filed in the last {{ $filters['range'] }} days, by where they are now
            @if ($kpis['denied']['value'] > 0)
                · {{ number_format($kpis['denied']['value']) }} denied
            @endif
        </p>

        @php
            $avgDelta = $kpis['avg_processing']['delta'] ?? null;
            $avgSample = $kpis['avg_processing']['sample'] ?? 0;
            // How many completions the average stands on, so one fast request
            // is not read as the office's processing time.
            $avgNote = ($avgSample > 0 ? 'From '.number_format($avgSample).' completed' : 'Filing to completion')
                .($avgDelta && $avgDelta['pct'] !== null ? ' · '.$avgDelta['pct'].'% '.($avgDelta['dir'] === 'up' ? 'slower' : 'faster') : '');
            $overdueNote = 'Open now, any filing date'
                .($kpis['at_risk']['value'] > 0 ? ' · +'.number_format($kpis['at_risk']['value']).' at risk' : '');
        @endphp

        {{-- Three across on a wide screen: six tiles in one row left each too
             narrow for its label and drawing. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <x-staff-stat-tile label="Requests filed" :value="number_format($kpis['total']['value'])"
                               tone="deep" icon="doc" :note="$delta('total')" />
            <x-staff-stat-tile label="Pending" :value="number_format($kpis['pending']['value'])"
                               tone="mid" icon="hourglass" />
            {{-- Not "In Progress": that is one stage of five in the status list
                 below, while this counts every started request. --}}
            <x-staff-stat-tile label="Being worked on" :value="number_format($kpis['in_progress']['value'])"
                               tone="bright" icon="clock" />
            <x-staff-stat-tile label="Completed" :value="number_format($kpis['completed']['value'])"
                               tone="brightest" icon="check" :note="$delta('completed')" />
            <x-staff-stat-tile label="Avg. Processing" :value="\App\Support\Duration::days($kpis['avg_processing']['value'])"
                               tone="deep" icon="stopwatch" :note="$avgNote" />
            {{-- Amber, not one more green, when anything is late — as on the
                 analytics page's Overdue Now tile. The word carries the meaning
                 too, so it never rests on colour alone. --}}
            <x-staff-stat-tile label="Overdue" :value="number_format($kpis['overdue']['value'])"
                               :tone="$kpis['overdue']['value'] > 0 ? 'amber' : 'bright'" icon="overdue" :note="$overdueNote" />
        </div>

        {{-- Requests that reached no office. Their request type has no
             handling office, so no supervisor was told and they wait unseen —
             only this page can show them. --}}
        @if ($unrouted->isNotEmpty())
            <section class="cc-panel cc-alert" role="alert">
                <div class="cc-panel-head flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="cc-panel-title">{{ $unrouted->count() }} {{ Str::plural('request', $unrouted->count()) }} reached no office</h2>
                        <p class="cc-panel-sub">Their request type has no handling office, so no supervisor was notified. Give the type an office, then route these by hand.</p>
                    </div>
                    <a href="{{ route('admin.request-types.index') }}" class="cr-btn cr-btn-sm">Open Request Types →</a>
                </div>
                <div class="cc-panel-body flex flex-wrap gap-2">
                    @foreach ($unrouted as $doc)
                        {{-- track.show sends a super admin back to this page, so
                             these chips did nothing; the Requests page opens them. --}}
                        <a href="{{ route('admin.requests.index', ['open' => $doc->tracking_number]) }}" class="cc-chip">
                            <span class="code">{{ $doc->tracking_number }}</span> {{ $doc->document_type }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ── The frame's two reading panels ───────────────────────────────
             Which office is carrying the load, and the tiles' requests by
             stage. Side by side on a wide screen, stacked below it.
             ───────────────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

            <section class="cc-panel">
                <div class="cc-panel-head">
                    <h2 class="cc-panel-title">Requests by Office</h2>
                    <p class="cc-panel-sub">Filed in this period · every office with a citizen service, idle ones included</p>
                </div>
                <div class="cc-panel-body">
                    @if ($departmentVolume->isEmpty())
                        <div class="sp-empty">No office handles a citizen service yet. Give each request type an office on the Request Types page.</div>
                    @else
                        <div class="cc-bars">
                            @foreach ($departmentVolume as $dept)
                                <div class="cc-bar-row {{ $dept['value'] === 0 ? 'is-idle' : ($dept['busiest'] ? 'is-busiest' : '') }}">
                                    <span class="cc-bar-label">{{ $dept['label'] }}</span>
                                    <div class="cc-bar-track">
                                        {{-- The count is the fact; the bar is only how it is read,
                                             so the accessible name carries both. --}}
                                        <div class="cc-bar-fill" style="width:{{ $dept['pct'] }}%"
                                             role="img"
                                             aria-label="{{ $dept['value'] }} document{{ $dept['value'] === 1 ? '' : 's' }} — {{ $dept['pct'] }}% of the busiest office"></div>
                                    </div>
                                    <span class="cc-bar-value">{{ number_format($dept['value']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- The tiles above, broken down by stage. This slot used to hold a
                 three-bucket donut that only repeated the tiles, while the
                 eight-stage breakdown sat further down as a second donut. --}}
            <section class="cc-panel">
                <div class="cc-panel-head">
                    <h2 class="cc-panel-title">Where requests are now</h2>
                    <p class="cc-panel-sub">The {{ number_format($sd['total']) }} request{{ $sd['total'] === 1 ? '' : 's' }} above, by stage</p>
                </div>
                <div class="cc-panel-body">
                    <div class="cc-donut">
                        <svg viewBox="0 0 140 140" width="152" height="152" style="flex:none" role="img"
                             aria-label="Requests by stage">
                            <g transform="rotate(-90 70 70)" fill="none" stroke-width="22">
                                @if ($sd['total'] > 0)
                                    @foreach ($sd['slices'] as $slice)
                                        <circle cx="70" cy="70" r="52" pathLength="100"
                                                stroke="{{ $slice['color'] }}"
                                                stroke-dasharray="{{ $slice['pct'] }} {{ 100 - $slice['pct'] }}"
                                                stroke-dashoffset="{{ $slice['offset'] }}"/>
                                    @endforeach
                                @else
                                    <circle cx="70" cy="70" r="52" stroke="#eef2ef"/>
                                @endif
                            </g>
                            <text x="70" y="68" text-anchor="middle" class="mono" font-size="22" font-weight="600" fill="#16211b">{{ number_format($sd['total']) }}</text>
                            <text x="70" y="86" text-anchor="middle" font-size="10" fill="#5b6b62">requests</text>
                        </svg>
                        <div class="cc-legend">
                            @foreach ($sd['slices'] as $slice)
                                <div class="cc-legend-row {{ $slice['value'] === 0 ? 'is-empty' : '' }}">
                                    <span class="cc-legend-dot" style="background:{{ $slice['color'] }}"></span>
                                    <span>{{ $slice['label'] }}</span>
                                    <span class="cc-legend-value">{{ number_format($slice['value']) }} · {{ round($slice['pct']) }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ── Region D: what needs doing ──────────────────────────────────
             Straight under the vitals: the overdue list is the one thing on
             this page the admin acts on, and it used to sit sixth. --}}
        <div class="an-grid">

            {{-- Overdue table (full width) — click a row to notify its owner.
                 "Fastest staff" sat beside it and was removed: ranking people
                 by speed rewards rushing a request instead of checking it. --}}
            <div class="cc-panel an-span-6">
                @php $attentionRows = $atRisk->take(12); @endphp
                <div class="cc-panel-head">
                    <h2 class="cc-panel-title">Needs Attention</h2>
                    <p class="cc-panel-sub">
                        {{ number_format($atRisk->count()) }} overdue{{ $atRisk->count() > $attentionRows->count() ? ' · showing the '.$attentionRows->count().' most overdue' : '' }} · open a row for its details, or notify whoever owns it
                    </p>
                </div>
                @if ($atRisk->isEmpty())
                    <div class="cc-panel-body"><div class="sp-empty">Nothing overdue — the pipeline is on time.</div></div>
                @else
                    <div class="table-wrap cc-panel-table">
                    <table class="reg">
                        <thead>
                            <tr><th>Tracking</th><th>Type</th><th>Stage</th><th>Assignee</th><th>Over SLA</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($attentionRows as $row)
                                @php
                                    $rowData = [
                                        'tracking' => $row['document']->tracking_number,
                                        'type' => $row['document']->document_type,
                                        'citizen' => $row['document']->citizen_name,
                                        'purpose' => $row['document']->purpose,
                                        'stageLabel' => $row['stage']->label(),
                                        'assignee' => $row['assignee'],
                                        'office' => $row['document']->department?->name,
                                        'over' => $row['over'],
                                        'nudgeUrl' => route('admin.nudge', $row['document']),
                                    ];
                                    $detailsUrl = route('admin.requests.index', ['open' => $row['document']->tracking_number]);
                                @endphp
                                {{-- The row opens the request (on the Requests page);
                                     Notify is its own button, not the whole row. --}}
                                <tr onclick="window.location = @js($detailsUrl)" style="cursor:pointer">
                                    <td><a href="{{ $detailsUrl }}" class="code hover:underline" onclick="event.stopPropagation()">{{ $row['document']->tracking_number }}</a></td>
                                    <td>{{ $row['document']->document_type }}</td>
                                    <td><x-status-badge :status="$row['stage']->value" /></td>
                                    <td class="muted">{{ $row['assignee'] ?? 'Unassigned' }}</td>
                                    <td class="mono" style="color:var(--red);font-weight:600;white-space:nowrap;">+{{ $row['over'] }}</td>
                                    <td><button type="button" class="cr-btn cr-btn-sm" onclick="event.stopPropagation()" @click="$dispatch('admin-notify', @js($rowData))">Notify →</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>

        </div>

        @include('admin.partials.notify-dialog')

        {{-- Stalled holds (full width) — deliberately its own panel: a hold nulls
             the stage SLA, so these documents can never appear in the overdue
             table above. This is the only place they surface on the dashboard,
             so it shows up only when there is one; an empty card on most days
             taught the eye to skip it. --}}
        @if ($stalledHolds->isNotEmpty())
        <div class="cc-panel">
            <div class="cc-panel-head">
                <h2 class="cc-panel-title">Parked and Past Due</h2>
                <p class="cc-panel-sub">{{ $stalledHolds->count() }} stalled hold{{ $stalledHolds->count() === 1 ? '' : 's' }} · SLA paused, so these never reach the overdue list</p>
            </div>
                <div class="table-wrap cc-panel-table">
                    <table class="reg">
                        <thead>
                            <tr><th>Tracking</th><th>Type</th><th>Waiting on</th><th>Assignee</th><th>Held since</th><th>Past due</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($stalledHolds as $row)
                                <tr>
                                    <td><span class="code">{{ $row['document']->tracking_number }}</span></td>
                                    <td>{{ $row['document']->document_type }}</td>
                                    <td class="muted">{{ $row['blocked_by'] ? ucfirst($row['blocked_by']) : '—' }}</td>
                                    <td class="muted">{{ $row['assignee'] ?? 'Unassigned' }}</td>
                                    <td class="muted">{{ $row['held_since']?->format('M d, Y') ?? '—' }}</td>
                                    <td class="mono" style="color:var(--red);font-weight:600;">+{{ $row['days_over'] }}d</td>
                                    <td><a class="cr-btn cr-btn-sm" href="{{ $row['url'] }}">Open →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </div>
        @endif

        {{-- ── Region C: analytics grid ──────────────────────────────────────
             The charts, after the work that needs doing: the page reads
             tiles → what is late → why → what happened. --}}
        <div class="an-grid">

            {{-- Throughput (full width) --}}
            <div class="cc-panel an-span-6">
                <div class="cc-panel-head"><h2 class="cc-panel-title">Requests Filed vs. Completed</h2><p class="cc-panel-sub">new requests vs. finished, each week</p></div>
                <div class="cc-panel-body">
                    @php
                        $tputCreated = collect($throughput)->sum('created');
                        $tputCompleted = collect($throughput)->sum('completed');
                    @endphp
                    @if ($tputCreated + $tputCompleted === 0)
                        <div class="sp-empty">No throughput in this window yet.</div>
                    @else
                        <p class="chart-summary"><b>{{ $tputCreated }}</b> created · <b>{{ $tputCompleted }}</b> completed in this period</p>
                        <div class="vbars vbars-axis" style="height:170px;">
                            @foreach ($throughput as $w)
                                <div class="vbar">
                                    <div class="grp">
                                        <div class="gcol" title="{{ $w['created'] }} created">
                                            <span class="num">{{ $w['created'] ?: '' }}</span>
                                            <div class="col pale" style="height:{{ $w['created'] ? max(3, round($w['created'] / $tputMax * 100)) : 0 }}%"></div>
                                        </div>
                                        <div class="gcol" title="{{ $w['completed'] }} completed">
                                            <span class="num">{{ $w['completed'] ?: '' }}</span>
                                            <div class="col" style="height:{{ $w['completed'] ? max(3, round($w['completed'] / $tputMax * 100)) : 0 }}%"></div>
                                        </div>
                                    </div>
                                    <div class="cap">{{ $w['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="legend" style="margin-top:10px;"><span><i style="background:#bfe0cb"></i>Created</span><span><i style="background:#167a3a"></i>Completed</span></div>
                    @endif
                </div>
            </div>

            {{-- Staff workload (1/2). "Open Requests vs. Deadline" sat between
                 this and the stage chart and was removed: it repeated the
                 Overdue tile and the Needs Attention list above. --}}
            <div class="cc-panel an-span-3">
                <div class="cc-panel-head"><h2 class="cc-panel-title">Staff Workload</h2><p class="cc-panel-sub">active + completed · idle staff included</p></div>
                <div class="cc-panel-body" style="padding-top:6px;">
                    @forelse ($staffWorkload as $s)
                        <div class="barrow">
                            <div class="nm">{{ $s['name'] }} @if ($s['overloaded'])<span title="Overloaded" style="color:var(--red)">⚑</span>@endif</div>
                            <div class="track">
                                <div style="display:flex;height:100%;width:{{ round(($s['completed'] + $s['active']) / $wlMax * 100) }}%;">
                                    <span style="display:block;height:100%;background:var(--green);flex:{{ $s['completed'] }}"></span>
                                    <span style="display:block;height:100%;background:var(--brass);flex:{{ $s['active'] }}"></span>
                                </div>
                            </div>
                            <div class="val">{{ $s['completed'] + $s['active'] ?: 'idle' }}</div>
                        </div>
                    @empty
                        <div class="sp-empty">No staff accounts yet.</div>
                    @endforelse
                    @if ($staffWorkload->isNotEmpty())
                        <div class="legend" style="margin-top:10px;"><span><i style="background:#167a3a"></i>Completed</span><span><i style="background:#c79a3e"></i>Active</span></div>
                    @endif
                </div>
            </div>

            {{-- Processing time by stage (1/2) --}}
            <div class="cc-panel an-span-3">
                <div class="cc-panel-head"><h2 class="cc-panel-title">Average Time per Stage</h2><p class="cc-panel-sub">avg time in stage</p></div>
                <div class="cc-panel-body">
                    @if (collect($timeByStage)->sum('hours') == 0)
                        <div class="sp-empty">Not enough stage history yet.</div>
                    @else
                        <div class="vbars vbars-axis" style="height:150px;">
                            @foreach ($timeByStage as $stg)
                                @php $isMax = $stg['hours'] >= $stageMaxHours && $stg['hours'] > 0; @endphp
                                <div class="vbar">
                                    <div class="num">{{ $stg['hours'] ? \App\Support\Duration::hours($stg['hours']) : '' }}</div>
                                    <div class="col {{ $isMax ? 'red' : 'brass' }}" style="height:{{ round($stg['hours'] / $stageMaxHours * 100) }}%"></div>
                                    <div class="cap">{{ \Illuminate\Support\Str::of($stg['label'])->limit(10, '') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Document type breakdown (full width): rows of 6, 2+2+2, 6. --}}
            <div class="cc-panel an-span-6">
                <div class="cc-panel-head"><h2 class="cc-panel-title">Volume by Request Type</h2><p class="cc-panel-sub">created in window</p></div>
                <div class="cc-panel-body">
                    @if ($typeBreakdown->isEmpty())
                        <div class="sp-empty">No documents in this window yet.</div>
                    @else
                        <div class="vbars vbars-axis" style="height:140px;">
                            @foreach ($typeBreakdown as $t)
                                <div class="vbar">
                                    <div class="num">{{ $t['value'] }}</div>
                                    <div class="col" style="height:{{ round($t['value'] / $typeMax * 100) }}%"></div>
                                    <div class="cap">{{ \Illuminate\Support\Str::of($t['label'])->limit(12, '') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Requirement checklist health — moved here from the retired
                 Services report: of the mandatory requirements on citizen
                 requests, how many were handed in, and how many staff checked. --}}
            <div class="cc-panel an-span-6">
                <div class="cc-panel-head"><h2 class="cc-panel-title">Requirement Checklist</h2><p class="cc-panel-sub">mandatory requirements on citizen requests · all time</p></div>
                <div class="cc-panel-body">
                    @if ($requirementStats['mandatory_total'] === 0)
                        <div class="sp-empty">No mandatory requirements attached to requests yet.</div>
                    @else
                        <div class="cc-bars">
                            @foreach ([
                                ['Handed in by the citizen', $requirementStats['submitted'], $requirementStats['submitted_pct']],
                                ['Checked by staff', $requirementStats['verified'], $requirementStats['verified_pct']],
                            ] as [$label, $count, $pct])
                                <div class="cc-bar-row">
                                    <span class="cc-bar-label">{{ $label }}</span>
                                    <div class="cc-bar-track">
                                        <div class="cc-bar-fill" style="width:{{ $pct }}%" role="img"
                                             aria-label="{{ $count }} of {{ $requirementStats['mandatory_total'] }} ({{ $pct }}%)"></div>
                                    </div>
                                    <span class="cc-bar-value">{{ $count }} / {{ $requirementStats['mandatory_total'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ── Recent activity ──────────────────────────────────────────────
             The audit strip that closes the page. Read off the same activity
             log as the full Audit Log page, so it can never drift from it; the
             heading links there for everything older, sign-ins included.
             ───────────────────────────────────────────────────────────────── --}}
        <section class="cc-panel" x-data="{ showAll: false }">
            <div class="cc-panel-head flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="cc-panel-title">Recent Activity</h2>
                    <p class="cc-panel-sub">Latest {{ $recentActivity->count() }} changes to {{ $isInternal ? 'internal' : 'citizen' }} requests, newest first</p>
                </div>
                <a href="{{ route('admin.audit-log.index') }}" class="cr-btn cr-btn-sm">Full audit log →</a>
            </div>

            <div class="st-scroll" style="margin-top:14px;">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Time Stamp</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentActivity as $entry)
                            <tr @if ($loop->index >= 5) x-show="showAll" x-cloak @endif>
                                <td class="whitespace-nowrap">{{ $entry['at']?->format('M j, g:i A') ?? '—' }}</td>
                                <td class="st-name">{{ $entry['user'] }}</td>
                                <td>{{ $entry['role'] }}</td>
                                <td class="font-medium text-ink">{{ $entry['action'] }}</td>
                                <td>
                                    @if ($entry['subject'])
                                        <span class="mono">{{ $entry['subject'] }}</span>
                                    @endif
                                    @if ($entry['detail'])
                                        {{-- Only a transition earns the emphasis; an auth row's
                                             detail is an IP address, which is reference, not news. --}}
                                        <span class="{{ str_starts_with($entry['detail'], '→') ? 'font-medium text-ink' : 'mono' }}">{{ $entry['detail'] }}</span>
                                    @endif
                                    @unless ($entry['subject'] || $entry['detail'])
                                        —
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr class="st-empty">
                                <td colspan="5">Nothing has happened yet — activity will appear here as staff work.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($recentActivity->count() > 5)
                <div class="st-pager justify-center" style="border-top:1px solid var(--hairline);">
                    <button type="button" @click="showAll = ! showAll" class="st-page-more">
                        <span x-show="! showAll">Show all {{ $recentActivity->count() }} entries</span>
                        <span x-show="showAll" x-cloak>Show the latest 5 only</span>
                    </button>
                </div>
            @endif
        </section>

    </div>
</x-app-layout>
