{{-- The command center's internal side: requests one office files with
     another, read by their own lifecycle — which office holds each one, for
     how long against that office's turnaround, and what came back for
     revision. Expects $internalKpis, $internalWaiting, $waitingByOffice,
     $officeTurnaround, $byRequestingOffice, $throughput, $tputMax, $filters. --}}
@php
    $k = $internalKpis;
    $filedDelta = $k['filed_prev'] > 0
        ? (($k['filed'] >= $k['filed_prev'] ? '▲ ' : '▼ ').abs((int) round(($k['filed'] - $k['filed_prev']) / $k['filed_prev'] * 100)).'%')
        : ($k['filed'] > 0 ? '▲ +'.$k['filed'] : null);
    $turnaroundNote = $k['avg_sample'] > 0 ? 'From '.number_format($k['avg_sample']).' completed' : 'Filing to done';
    $overdueOpen = $internalWaiting->where('overdue', true)->count();
    $attentionRows = $internalWaiting->take(12);
    $byOfficeMax = max(1, $waitingByOffice->max('count') ?? 0);
@endphp

<p class="cc-tiles-caption" data-tile-caption>
    Internal requests filed in the last {{ $filters['range'] }} days, by where they are now
    @if ($k['denied'] > 0)
        · {{ number_format($k['denied']) }} denied
    @endif
</p>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
    <x-staff-stat-tile label="Requests filed" :value="number_format($k['filed'])"
                       tone="deep" icon="doc" :note="$filedDelta" />
    <x-staff-stat-tile label="Moving through offices" :value="number_format($k['moving'])"
                       tone="mid" icon="clock" />
    {{-- Back with the office that filed it, waiting on their fix. --}}
    <x-staff-stat-tile label="Returned for revision" :value="number_format($k['returned'])"
                       tone="bright" icon="hourglass" />
    <x-staff-stat-tile label="Completed" :value="number_format($k['completed'])"
                       tone="brightest" icon="check" />
    <x-staff-stat-tile label="Avg. Turnaround" :value="\App\Support\Duration::days($k['avg_turnaround'])"
                       tone="deep" icon="stopwatch" :note="$turnaroundNote" />
    <x-staff-stat-tile label="Overdue at a step" :value="number_format($k['overdue'])"
                       :tone="$k['overdue'] > 0 ? 'amber' : 'bright'" icon="overdue"
                       note="Open now, past the office's turnaround" />
</div>

<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    {{-- The bottleneck view: one row per office holding open requests. --}}
    <section class="cc-panel">
        <div class="cc-panel-head">
            <h2 class="cc-panel-title">Where they're waiting</h2>
            <p class="cc-panel-sub">Open requests by the office holding them now, any filing date</p>
        </div>
        <div class="cc-panel-body">
            @if ($waitingByOffice->isEmpty())
                <div class="sp-empty">No internal request is open right now.</div>
            @else
                <div class="cc-bars">
                    @foreach ($waitingByOffice as $office)
                        <div class="cc-bar-row {{ $office['overdue'] > 0 ? 'is-late' : '' }}">
                            <span class="cc-bar-label">
                                {{ $office['label'] }}
                                <span class="block text-[11px] text-ink-soft">
                                    longest {{ $office['longest'] }}{{ $office['limit'] ? ' · turnaround '.$office['limit'] : '' }}
                                </span>
                            </span>
                            <div class="cc-bar-track">
                                <div class="cc-bar-fill" style="width:{{ round($office['count'] / $byOfficeMax * 100) }}%" role="img"
                                     aria-label="{{ $office['count'] }} open, {{ $office['overdue'] }} overdue"></div>
                            </div>
                            <span class="cc-bar-value">
                                {{ $office['count'] }}
                                @if ($office['overdue'] > 0)
                                    <span class="cc-tag cc-tag-late">{{ $office['overdue'] }} late</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="cc-panel">
        <div class="cc-panel-head">
            <h2 class="cc-panel-title">Filed by office</h2>
            <p class="cc-panel-sub">Who filed internal requests in this period</p>
        </div>
        <div class="cc-panel-body">
            @if ($byRequestingOffice->isEmpty())
                <div class="sp-empty">No office filed an internal request in this period.</div>
            @else
                <div class="cc-bars">
                    @foreach ($byRequestingOffice as $office)
                        <div class="cc-bar-row">
                            <span class="cc-bar-label">{{ $office['label'] }}</span>
                            <div class="cc-bar-track">
                                <div class="cc-bar-fill" style="width:{{ $office['pct'] }}%" role="img"
                                     aria-label="{{ $office['value'] }} filed"></div>
                            </div>
                            <span class="cc-bar-value">{{ number_format($office['value']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>

{{-- Every open request, longest wait first. Overdue ones are past the
     turnaround of the office holding them; returned ones wait on the office
     that filed them, which has no deadline. --}}
<div class="cc-panel">
    <div class="cc-panel-head">
        <h2 class="cc-panel-title">Needs Attention</h2>
        <p class="cc-panel-sub">
            {{ number_format($internalWaiting->count()) }} open · {{ number_format($overdueOpen) }} overdue at a step{{ $internalWaiting->count() > $attentionRows->count() ? ' · showing the '.$attentionRows->count().' longest waits' : '' }}
        </p>
    </div>
    @if ($internalWaiting->isEmpty())
        <div class="cc-panel-body"><div class="sp-empty">No internal request is open right now.</div></div>
    @else
        <div class="table-wrap cc-panel-table">
            <table class="reg">
                <thead>
                    <tr><th>Tracking</th><th>Request</th><th>Filed by</th><th>Now with</th><th>Waiting</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($attentionRows as $row)
                        <tr>
                            <td><span class="code">{{ $row['document']->tracking_number }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit($row['document']->purpose ?: $row['document']->document_type, 48) }}</td>
                            <td class="muted">{{ $row['document']->requestingDepartment?->code ?? '—' }}</td>
                            <td>{{ $row['at'] }}</td>
                            <td class="whitespace-nowrap">
                                <span class="mono">{{ $row['waited'] }}</span>
                                @if ($row['returned'])
                                    <span class="cc-tag cc-tag-back">Returned</span>
                                @elseif ($row['overdue'])
                                    <span class="cc-tag cc-tag-late">Overdue</span>
                                @endif
                            </td>
                            <td><a class="cr-btn cr-btn-sm" href="{{ $row['url'] }}">Open →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="an-grid">
    <div class="cc-panel an-span-6">
        <div class="cc-panel-head"><h2 class="cc-panel-title">Requests Filed vs. Completed</h2><p class="cc-panel-sub">internal requests filed vs. finished, each week</p></div>
        <div class="cc-panel-body">
            @php
                $tputCreated = collect($throughput)->sum('created');
                $tputCompleted = collect($throughput)->sum('completed');
            @endphp
            @if ($tputCreated + $tputCompleted === 0)
                <div class="sp-empty">No internal requests filed or finished in this window.</div>
            @else
                <p class="chart-summary"><b>{{ $tputCreated }}</b> filed · <b>{{ $tputCompleted }}</b> completed in this period</p>
                <div class="vbars vbars-axis" style="height:170px;">
                    @foreach ($throughput as $w)
                        <div class="vbar">
                            <div class="grp">
                                <div class="gcol" title="{{ $w['created'] }} filed">
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
                <div class="legend" style="margin-top:10px;"><span><i style="background:#bfe0cb"></i>Filed</span><span><i style="background:#167a3a"></i>Completed</span></div>
            @endif
        </div>
    </div>

    {{-- How long each office holds a request before acting on it, against
         its own turnaround (set per office on the Departments page). --}}
    <div class="cc-panel an-span-6">
        <div class="cc-panel-head">
            <h2 class="cc-panel-title">How long each office takes</h2>
            <p class="cc-panel-sub">Average time from a request reaching an office to its decision · decisions in this period</p>
        </div>
        @if ($officeTurnaround->isEmpty())
            <div class="cc-panel-body"><div class="sp-empty">No office acted on an internal request in this period.</div></div>
        @else
            <div class="table-wrap cc-panel-table">
                <table class="reg">
                    <thead><tr><th>Office</th><th>Decisions</th><th>Average time</th><th>Turnaround</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($officeTurnaround as $office)
                            <tr>
                                <td>{{ $office['label'] }}</td>
                                <td class="mono">{{ $office['hops'] }}</td>
                                <td class="mono">{{ $office['avg'] }}</td>
                                <td class="muted mono">{{ $office['limit'] }}</td>
                                <td>
                                    <span class="cc-tag {{ $office['over'] ? 'cc-tag-late' : 'cc-tag-ok' }}">{{ $office['over'] ? 'Slower than turnaround' : 'Within turnaround' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
