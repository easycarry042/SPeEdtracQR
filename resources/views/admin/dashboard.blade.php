<x-app-layout>
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
        // Throughput grouped bars
        $tputMax = max(1, collect($throughput)->max('created') ?? 0, collect($throughput)->max('completed') ?? 0);

        // Status distribution donut (stage palette: gray→brass→gold→green→deep→amber)
        $stageColors = ['#cdd9d1', '#c79a3e', '#e0b15a', '#46a268', '#167a3a', '#b45309'];
        $sd = $donut($statusDistribution->values()->map(fn ($r, $i) => [$r['label'], $r['value'], $stageColors[$i % 6]])->all());

        // Bottlenecks donut
        $bt = $donut([
            ['On-time', $bottlenecks['on_time'], '#167a3a'],
            ['At-risk', $bottlenecks['at_risk'], '#b45309'],
            ['Breached', $bottlenecks['breached'], '#b91c1c'],
        ]);

        // Processing time by stage — tallest bar is the bottleneck (red)
        $stageMaxHours = max(0.01, collect($timeByStage)->max('hours') ?? 0);

        // Document type breakdown
        $typeMax = max(1, collect($typeBreakdown)->max('value') ?? 0);

        // Department comparison
        $deptMax = max(1, collect($byDepartment)->max('volume') ?? 0);

        // Staff workload
        $wlMax = max(1, $staffWorkload->max(fn ($r) => $r['completed'] + $r['active']) ?? 0);

        /* ---------- heatmap grid (weeks × days) ---------- */
        $hCounts = $heatmap['counts'];
        $hMax = max(1, $heatmap['max']);
        $hCursor = \Illuminate\Support\Carbon::parse($heatmap['since'])->startOfWeek(\Carbon\Carbon::SUNDAY);
        $hEnd = now()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $hWeeks = [];
        while ($hCursor <= $hEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $hCursor->toDateString();
                $c = $hCounts[$key] ?? 0;
                $week[] = [
                    'date' => $key,
                    'count' => $c,
                    'level' => $c == 0 ? 0 : min(4, (int) ceil($c / $hMax * 4)),
                    'future' => $hCursor->isFuture(),
                ];
                $hCursor->addDay();
            }
            $hWeeks[] = $week;
        }
    @endphp

    <div class="mx-auto max-w-7xl space-y-4">

        {{-- ── Region A: header + filters ──────────────────────────────────── --}}
        <div class="an-header">
            <div>
                <h1 style="font-size:18px;font-weight:700;color:var(--green-deep);margin:0;">City of San Pedro · Analytics</h1>
                <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px;display:flex;align-items:center;gap:6px;">
                    <span class="live-dot"></span> live · updated {{ $updatedAt->diffForHumans() }}
                </div>
            </div>
            <form method="GET" action="{{ route('admin.dashboard') }}" class="toolbar">
                <div class="field">
                    <label>Range</label>
                    <select name="range" onchange="this.form.submit()">
                        <option value="7" @selected($filters['range'] === 7)>Last 7 days</option>
                        <option value="30" @selected($filters['range'] === 30)>Last 30 days</option>
                        <option value="90" @selected($filters['range'] === 90)>Last 90 days</option>
                    </select>
                </div>
                <div class="field">
                    <label>Dept</label>
                    <select name="department_id" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((string) $filters['department_id'] === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Type</label>
                    <select name="document_type" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($documentTypes as $t)
                            <option value="{{ $t }}" @selected($filters['document_type'] === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Apply</button>
                @if ($filters['active'])
                    <a href="{{ route('admin.dashboard') }}" class="cr-btn cr-btn-sm">Reset</a>
                @endif
            </form>
        </div>

        {{-- ── Region B: KPI vitals ────────────────────────────────────────── --}}
        <div class="an-kpis">
            @php
                $kpiDefs = [
                    ['key' => 'total', 'label' => 'Total Documents', 'fmt' => fn ($v) => number_format($v), 'spark' => 'green'],
                    ['key' => 'completed', 'label' => 'Completed', 'fmt' => fn ($v) => number_format($v), 'spark' => 'green'],
                    ['key' => 'active', 'label' => 'Active / In-Progress', 'fmt' => fn ($v) => number_format($v), 'spark' => 'brass'],
                    ['key' => 'at_risk', 'label' => 'At-Risk / Breached', 'fmt' => fn ($v) => number_format($v)],
                    ['key' => 'avg_processing', 'label' => 'Avg. Processing', 'fmt' => fn ($v) => $v !== null ? $v.'d' : '—'],
                    ['key' => 'on_time', 'label' => 'On-Time Rate', 'fmt' => fn ($v) => $v !== null ? $v.'%' : '—'],
                ];
            @endphp
            @foreach ($kpiDefs as $def)
                @php $k = $kpis[$def['key']]; @endphp
                <div class="tile">
                    <div class="kpi-head">
                        <span class="k">{{ $def['label'] }}</span>
                        @if (! empty($k['delta']))
                            <span class="delta {{ $k['delta']['good'] ? 'good' : 'bad' }}">{{ $fmtDelta($k['delta']) }}</span>
                        @endif
                    </div>
                    <div class="v mono">{{ $def['fmt']($k['value']) }}</div>

                    @if (! empty($def['spark']) && ! empty($k['spark']) && array_sum($k['spark']) > 0)
                        <svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                            <polyline points="{{ $spark($k['spark']) }}" fill="none"
                                      stroke="{{ $def['spark'] === 'brass' ? '#c79a3e' : '#2a9d4f' }}" stroke-width="1.6"/>
                        </svg>
                    @elseif ($def['key'] === 'at_risk')
                        <span class="pill {{ $k['pill'] === 'watch' ? 'p-amber' : 'p-green' }}" style="margin-top:8px;">{{ $k['pill'] === 'watch' ? 'WATCH' : 'CLEAR' }}</span>
                    @elseif ($def['key'] === 'on_time')
                        <span class="pill p-{{ $k['pill'] === 'healthy' ? 'green' : ($k['pill'] === 'breach' ? 'red' : ($k['pill'] === 'muted' ? 'muted' : 'amber')) }}" style="margin-top:8px;">{{ ['healthy' => 'HEALTHY', 'watch' => 'WATCH', 'breach' => 'AT RISK', 'muted' => 'NO DATA'][$k['pill']] }}</span>
                    @else
                        <div class="bar {{ $def['key'] === 'avg_processing' ? 'brass' : '' }}" style="margin-top:9px;"></div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ── Region C: analytics grid ────────────────────────────────────── --}}
        <div class="an-grid">

            {{-- Throughput (2/3) --}}
            <div class="panel an-span-4">
                <div class="ph"><h2>Is the pipeline keeping up?</h2><span class="sub">created vs completed · per week</span></div>
                <div class="pb">
                    @if (collect($throughput)->sum(fn ($w) => $w['created'] + $w['completed']) === 0)
                        <div class="sp-empty">No throughput in this window yet.</div>
                    @else
                        <div class="vbars" style="height:170px;">
                            @foreach ($throughput as $w)
                                <div class="vbar">
                                    <div class="grp">
                                        <div class="col pale" style="height:{{ round($w['created'] / $tputMax * 100) }}%" title="{{ $w['created'] }} created"></div>
                                        <div class="col" style="height:{{ round($w['completed'] / $tputMax * 100) }}%" title="{{ $w['completed'] }} completed"></div>
                                    </div>
                                    <div class="cap">{{ $w['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="legend" style="margin-top:10px;"><span><i style="background:#bfe0cb"></i>Created</span><span><i style="background:#167a3a"></i>Completed</span></div>
                    @endif
                </div>
            </div>

            {{-- Status distribution donut (1/3) --}}
            <div class="panel an-span-2">
                <div class="ph"><h2>Where do documents sit?</h2><span class="sub">{{ $sd['total'] }} total</span></div>
                <div class="pb">
                    @include('admin.partials.donut', ['data' => $sd, 'caption' => 'documents'])
                </div>
            </div>

            {{-- Staff workload (1/3) --}}
            <div class="panel an-span-2">
                <div class="ph"><h2>Who is overloaded?</h2><span class="sub">active + completed</span></div>
                <div class="pb" style="padding-top:6px;">
                    @forelse ($staffWorkload as $s)
                        <div class="barrow">
                            <div class="nm">{{ $s['name'] }} @if ($s['overloaded'])<span title="Overloaded" style="color:var(--red)">⚑</span>@endif</div>
                            <div class="track">
                                <div style="display:flex;height:100%;width:{{ round(($s['completed'] + $s['active']) / $wlMax * 100) }}%;">
                                    <span style="display:block;height:100%;background:var(--green);flex:{{ $s['completed'] }}"></span>
                                    <span style="display:block;height:100%;background:var(--brass);flex:{{ $s['active'] }}"></span>
                                </div>
                            </div>
                            <div class="val">{{ $s['completed'] + $s['active'] }}</div>
                        </div>
                    @empty
                        <div class="sp-empty">No assignments yet.</div>
                    @endforelse
                    @if ($staffWorkload->isNotEmpty())
                        <div class="legend" style="margin-top:10px;"><span><i style="background:#167a3a"></i>Completed</span><span><i style="background:#c79a3e"></i>Active</span></div>
                    @endif
                </div>
            </div>

            {{-- Bottlenecks donut (1/3) --}}
            <div class="panel an-span-2">
                <div class="ph"><h2>What share is at-risk?</h2><span class="sub">{{ $bottlenecks['total'] }} active</span></div>
                <div class="pb">
                    @include('admin.partials.donut', ['data' => $bt, 'caption' => 'active'])
                    @if ($bottlenecks['worst_stage'])
                        <div class="callout">Worst stage: <b>{{ $bottlenecks['worst_stage'] }}</b></div>
                    @endif
                </div>
            </div>

            {{-- Processing time by stage (1/3) --}}
            <div class="panel an-span-2">
                <div class="ph"><h2>Which stage is slowest?</h2><span class="sub">avg hours in stage</span></div>
                <div class="pb">
                    @if (collect($timeByStage)->sum('hours') == 0)
                        <div class="sp-empty">Not enough stage history yet.</div>
                    @else
                        <div class="vbars" style="height:150px;">
                            @foreach ($timeByStage as $stg)
                                @php $isMax = $stg['hours'] >= $stageMaxHours && $stg['hours'] > 0; @endphp
                                <div class="vbar">
                                    <div class="num">{{ $stg['hours'] ? $stg['hours'].'h' : '' }}</div>
                                    <div class="col {{ $isMax ? 'red' : 'brass' }}" style="height:{{ round($stg['hours'] / $stageMaxHours * 100) }}%"></div>
                                    <div class="cap">{{ \Illuminate\Support\Str::of($stg['label'])->limit(10, '') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Document type breakdown (1/2) --}}
            <div class="panel an-span-3">
                <div class="ph"><h2>What volume by type?</h2><span class="sub">created in window</span></div>
                <div class="pb">
                    @if ($typeBreakdown->isEmpty())
                        <div class="sp-empty">No documents in this window yet.</div>
                    @else
                        <div class="vbars" style="height:140px;">
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

            {{-- By department (1/2) --}}
            <div class="panel an-span-3">
                <div class="ph"><h2>How do departments compare?</h2><span class="sub">volume · SLA rate</span></div>
                <div class="pb" style="padding-top:6px;">
                    @forelse ($byDepartment as $d)
                        <div class="barrow">
                            <div class="nm">{{ $d['name'] }}</div>
                            <div class="track"><div class="fill" style="width:{{ round($d['volume'] / $deptMax * 100) }}%"></div></div>
                            <div class="val">{{ $d['volume'] }}</div>
                            <span class="pill p-{{ $d['on_time'] === null ? 'muted' : ($d['on_time'] >= 80 ? 'green' : 'amber') }}" style="width:54px;justify-content:center;">{{ $d['on_time'] !== null ? $d['on_time'].'%' : '—' }}</span>
                        </div>
                    @empty
                        <div class="sp-empty">No departments to compare.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Region D: supporting data ───────────────────────────────────── --}}
        <div class="an-grid">

            {{-- At-risk table (2/3) --}}
            <div class="panel an-span-4">
                <div class="ph"><h2>Exactly what needs attention</h2><span class="sub">{{ $atRisk->count() }} overdue · click a row to act</span></div>
                @if ($atRisk->isEmpty())
                    <div class="sp-empty">Nothing overdue — the pipeline is on time.</div>
                @else
                    <table class="reg">
                        <thead>
                            <tr><th>Tracking</th><th>Type</th><th>Stage</th><th>Assignee</th><th>Over SLA</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($atRisk as $row)
                                <tr onclick="window.location='{{ $row['url'] }}'" style="cursor:pointer">
                                    <td><span class="code">{{ $row['document']->tracking_number }}</span></td>
                                    <td>{{ $row['document']->document_type }}</td>
                                    <td><x-status-badge :status="$row['stage']->value" /></td>
                                    <td class="muted">{{ $row['assignee'] ?? 'Unassigned' }}</td>
                                    <td class="mono" style="color:var(--red);font-weight:600;">+{{ $row['hours_over'] }}h</td>
                                    <td><a href="{{ $row['url'] }}" class="cr-btn cr-btn-sm" onclick="event.stopPropagation()">Open →</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Ranked widgets (1/3) --}}
            <div class="an-span-2" style="display:flex;flex-direction:column;gap:14px;">
                <div class="panel">
                    <div class="ph"><h2>Fastest staff</h2><span class="sub">avg time</span></div>
                    <div class="pb" style="padding-top:6px;">
                        @forelse ($fastestStaff as $i => $s)
                            <div class="legrow">
                                <span class="avatar" style="width:22px;height:22px;font-size:10px;">{{ $i + 1 }}</span>
                                <span class="ln">{{ $s['name'] }}</span>
                                <span class="lv">{{ $s['avg_days'] }}d</span>
                                <span class="lp">{{ $s['count'] }}</span>
                            </div>
                        @empty
                            <div class="sp-empty">No completions yet.</div>
                        @endforelse
                    </div>
                </div>
                <div class="panel">
                    <div class="ph"><h2>Busiest departments</h2><span class="sub">volume</span></div>
                    <div class="pb" style="padding-top:6px;">
                        @forelse ($busiestDepartments as $d)
                            <div class="barrow">
                                <div class="nm">{{ $d['name'] }}</div>
                                <div class="track"><div class="fill" style="width:{{ round($d['volume'] / $deptMax * 100) }}%"></div></div>
                                <div class="val">{{ $d['volume'] }}</div>
                            </div>
                        @empty
                            <div class="sp-empty">No activity yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- System throughput heatmap (full width) --}}
        <div class="panel">
            <div class="ph"><h2>System throughput</h2><span class="sub">{{ $heatmap['total'] }} processed · last 26 weeks</span></div>
            <div class="pb">
                <div class="heat">
                    @foreach ($hWeeks as $week)
                        <div class="heat-col">
                            @foreach ($week as $day)
                                <span class="heat-cell heat-l{{ $day['level'] }} {{ $day['future'] ? 'heat-future' : '' }}"
                                      title="{{ $day['count'] }} on {{ $day['date'] }}"></span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="heat-legend"><span>Less</span><span class="heat-cell heat-l0"></span><span class="heat-cell heat-l1"></span><span class="heat-cell heat-l2"></span><span class="heat-cell heat-l3"></span><span class="heat-cell heat-l4"></span><span>More</span></div>
            </div>
        </div>

    </div>
</x-app-layout>
