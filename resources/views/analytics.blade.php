<x-app-layout title="Analytics">
    <div class="page-shell page-shell-loose">
        @php
            /* ---------- donut geometry (document type = shares of a whole) ---------- */
            $palette   = ['#167a3a', '#5cb87f', '#c79a3e', '#0f4d28', '#8fce9f'];
            $types     = collect($byType ?? [])->values();
            $typeTotal = $types->sum('count');
            $slices    = [];
            $acc       = 0;
            foreach ($types as $i => $t) {
                $pct = $typeTotal ? round(($t['count'] / $typeTotal) * 100, 2) : 0;
                $slices[] = [
                    'label'  => $t['label'],
                    'count'  => $t['count'],
                    'pct'    => $pct,
                    'color'  => $palette[$i % count($palette)],
                    'offset' => -1 * $acc,
                ];
                $acc += $pct;
            }

            /* ---------- activity line chart geometry ---------- */
            $acts = collect($activity ?? [])->values();
            $n    = max($acts->count(), 1);
            $maxY = max(1, (int) ($acts->max('submitted') ?? 0), (int) ($acts->max('completed') ?? 0));
            $X0 = 34; $X1 = 610; $Y0 = 172; $YT = 16;
            $px = fn ($i) => $n > 1 ? round($X0 + $i * ($X1 - $X0) / ($n - 1), 1) : $X0;
            $py = fn ($v) => round($Y0 - ($v / $maxY) * ($Y0 - $YT), 1);

            $subPts = []; $comPts = [];
            foreach ($acts as $i => $p) {
                $subPts[] = $px($i) . ',' . $py($p['submitted'] ?? 0);
                $comPts[] = $px($i) . ',' . $py($p['completed'] ?? 0);
            }
            $subLine = implode(' ', $subPts);
            $comLine = implode(' ', $comPts);
            $area    = $acts->count()
                ? 'M ' . $X0 . ',' . $Y0 . ' L ' . $subLine . ' ' . $px($n - 1) . ',' . $Y0 . ' Z'
                : '';

            $gridV     = [0, $maxY / 3, ($maxY * 2) / 3, $maxY];
            $labelIdx  = $n > 1 ? [0, intdiv($n, 3), intdiv($n * 2, 3), $n - 1] : [0];

            $maxAssigned = max(1, (int) (collect($topStaff ?? [])->max('assigned')));
        @endphp

        {{-- The same gradient tiles the dashboards use. This page used to draw
             its own white cards with a coloured underline, which is why it read
             as a different product from every other screen a supervisor opens.
             Spacing comes from .page-shell's gap; a margin here would double it. --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <x-staff-stat-tile label="In Progress" :value="number_format($kpis['in_progress'] ?? 0)"
                               tone="deep" icon="clock" />
            <x-staff-stat-tile label="Completed" :value="number_format($kpis['completed'] ?? 0)"
                               tone="brightest" icon="check" />
            <x-staff-stat-tile label="Submitted This Month" :value="number_format($kpis['submitted_month'] ?? 0)"
                               tone="mid" icon="doc" />
            {{-- Overdue breaks the green ramp on purpose: amber is what this
                 system uses for "watch this", and a count of breaches must not
                 read as one more shade of done. The word "Overdue" carries the
                 meaning too, so it never depends on the colour alone. --}}
            <x-staff-stat-tile label="Overdue Now" :value="number_format($kpis['overdue'] ?? 0)"
                               :tone="($kpis['overdue'] ?? 0) > 0 ? 'amber' : 'bright'" icon="hourglass" />
        </div>

        {{-- Two columns on a wide screen, stacked below it. The fixed 1.4fr/1fr
             grid here had no breakpoint, so on a laptop the chart and its legend
             were squeezed side by side at any width. --}}
        <div class="grid grid-cols-1 gap-[14px] lg:grid-cols-[1.4fr_1fr]">

            {{-- LEFT: activity over time --}}
            <div class="panel">
                <div class="ph">
                    <h2>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"/><path d="M4 19h16"/><path d="m6 14 4-4 3 3 5-6"/></svg>
                        Document activity over time
                    </h2>
                    <span class="sub">last 30 days</span>
                </div>
                <div class="pb">
                    <form method="GET" action="{{ url()->current() }}" class="toolbar" style="margin-bottom:12px;">
                        <x-select-menu name="category" label="Category" panel-width="w-56" class="w-56"
                                       :options="collect($categories ?? [])->mapWithKeys(fn ($c) => [$c => $c])->prepend('All', '')->all()"
                                       :selected="request('category')"/>
                        <x-select-menu name="status" label="Status" panel-width="w-48" class="w-48"
                                       :options="['' => 'All', 'pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed']"
                                       :selected="request('status')"/>
                        {{-- The system's own calendar, not the browser's — the
                             same <x-date-field> the request form uses, so a date
                             is picked the same way everywhere. No min: a report
                             looks backwards. --}}
                        <x-date-field name="from" label="From" :value="request('from')" compact class="w-48" />
                        <x-date-field name="to" label="To" :value="request('to')" compact class="w-48" />
                        <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Apply</button>
                        @if (request()->hasAny(['category', 'status', 'from', 'to']))
                            <a href="{{ url()->current() }}" class="cr-btn cr-btn-sm">Reset</a>
                        @endif
                    </form>

                    <svg viewBox="0 0 620 210" width="100%" style="display:block" role="img" aria-label="Daily new submissions versus completions">
                        <line x1="{{ $X0 }}" y1="{{ $YT }}" x2="{{ $X0 }}" y2="{{ $Y0 }}" stroke="#e6ece8"/>
                        <line x1="{{ $X0 }}" y1="{{ $Y0 }}" x2="{{ $X1 }}" y2="{{ $Y0 }}" stroke="#e6ece8"/>
                        @foreach ($gridV as $gv)
                            @php $gy = $py($gv); @endphp
                            <line x1="{{ $X0 }}" y1="{{ $gy }}" x2="{{ $X1 }}" y2="{{ $gy }}" stroke="#f0f3f1"/>
                            <text x="{{ $X0 - 8 }}" y="{{ $gy + 3 }}" text-anchor="end" fill="#9bb0a6" font-size="9" font-family="monospace">{{ round($gv) }}</text>
                        @endforeach

                        @if ($area)
                            <path d="{{ $area }}" fill="#eef5f0"/>
                            <polyline points="{{ $subLine }}" fill="none" stroke="#2a9d4f" stroke-width="2"/>
                            <polyline points="{{ $comLine }}" fill="none" stroke="#0f4d28" stroke-width="2"/>
                        @endif

                        @foreach ($labelIdx as $li)
                            <text x="{{ $px($li) }}" y="{{ $Y0 + 16 }}" fill="#9bb0a6" font-size="9" font-family="monospace">{{ $acts[$li]['date'] ?? '' }}</text>
                        @endforeach
                    </svg>

                    <div class="legend" style="margin-top:8px;">
                        <span><i style="background:#2a9d4f"></i>New submissions</span>
                        <span><i style="background:#0f4d28"></i>Completed</span>
                    </div>
                </div>
            </div>

            {{-- RIGHT: donut + ranked bars --}}
            <div style="display:flex;flex-direction:column;gap:14px;">

                {{-- By document type = donut (parts of a whole) --}}
                <div class="panel">
                    <div class="ph">
                        <h2>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M12 3v9"/></svg>
                            By document type
                        </h2>
                        <span class="sub">share of {{ $typeTotal }}</span>
                    </div>
                    <div class="pb">
                        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                            <svg viewBox="0 0 140 140" width="138" height="138" style="flex:none" role="img" aria-label="Document type composition">
                                <g transform="rotate(-90 70 70)" fill="none" stroke-width="20">
                                    @foreach ($slices as $s)
                                        <circle cx="70" cy="70" r="52" pathLength="100"
                                                stroke="{{ $s['color'] }}"
                                                stroke-dasharray="{{ $s['pct'] }} {{ 100 - $s['pct'] }}"
                                                stroke-dashoffset="{{ $s['offset'] }}"/>
                                    @endforeach
                                </g>
                                <text x="70" y="68" text-anchor="middle" class="mono" font-size="23" font-weight="600" fill="#16211b">{{ $typeTotal }}</text>
                                <text x="70" y="86" text-anchor="middle" font-size="10" fill="#5b6b62">documents</text>
                            </svg>
                            <div style="flex:1;min-width:168px;">
                                @forelse ($slices as $s)
                                    <div class="legrow">
                                        <span class="sw" style="background:{{ $s['color'] }}"></span>
                                        <span class="ln">{{ $s['label'] }}</span>
                                        <span class="lv">{{ $s['count'] }}</span>
                                        <span class="lp">{{ round($s['pct']) }}%</span>
                                    </div>
                                @empty
                                    <div class="legrow"><span class="ln" style="color:var(--ink-soft)">No documents in this range yet.</span></div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Top assignees = ranked bars --}}
                <div class="panel">
                    <div class="ph">
                        <h2>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"/><path d="M4 19h16"/><rect x="8" y="11" width="3" height="5"/><rect x="13" y="7" width="3" height="9"/></svg>
                            {{ $isOrgWide ? 'Top staff by assigned volume' : 'Assigned activity' }}
                        </h2>
                        <span class="sub">assigned</span>
                    </div>
                    <div class="pb" style="padding-top:6px;">
                        @forelse ($topStaff ?? [] as $d)
                            <div class="barrow">
                                <div class="nm">{{ $d['name'] }}</div>
                                <div class="track"><div class="fill" style="width:{{ round(($d['assigned'] / $maxAssigned) * 100) }}%"></div></div>
                                <div class="val">{{ $d['assigned'] }}</div>
                            </div>
                        @empty
                            <div class="barrow"><div class="nm" style="color:var(--ink-soft)">No assigned work yet.</div></div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
