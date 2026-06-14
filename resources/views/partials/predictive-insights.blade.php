{{-- ── Predictive Insights (self-hosted analytics) ─────────────────────────────
     Expects: $bottlenecks (Collection), $anomalies (Collection of Documents with
     ->anomaly array). Shared by the staff and admin dashboards. --}}
@if(($bottlenecks ?? collect())->isNotEmpty() || ($anomalies ?? collect())->isNotEmpty())
<section class="space-y-4">
    <div class="flex items-center gap-3">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L23 12l-6.714 2.143L14 21l-2.286-6.857L5 12l6.714-2.143L14 3z"/>
            </svg>
        </span>
        <div>
            <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Predictive Insights</h2>
            <p class="text-sm text-gray-500">Forecast from your own historical scan data — no external service.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        {{-- Bottleneck forecast --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-bold text-gray-800">Department bottleneck forecast</h3>
                <p class="text-xs text-gray-400">Median processing time vs. SLA, with recent trend.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($bottlenecks as $b)
                    @php
                        $lvl = $b['level'];
                        $levelMeta = match($lvl) {
                            'critical' => ['dot' => 'bg-rose-500',   'chip' => 'bg-rose-100 text-rose-700',      'label' => 'Critical'],
                            'warning'  => ['dot' => 'bg-amber-500',  'chip' => 'bg-amber-100 text-amber-700',    'label' => 'Watch'],
                            'ok'       => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-100 text-emerald-700', 'label' => 'Healthy'],
                            default    => ['dot' => 'bg-gray-300',   'chip' => 'bg-gray-100 text-gray-500',      'label' => 'No data'],
                        };
                        $pct = $b['ratio'] !== null ? min(100, (int) round($b['ratio'] * 100)) : 0;
                        $barColor = $lvl === 'critical' ? 'bg-rose-500' : ($lvl === 'warning' ? 'bg-amber-500' : 'bg-emerald-500');
                    @endphp
                    <div class="px-5 py-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $levelMeta['dot'] }}"></span>
                                <span class="text-sm font-semibold text-gray-800">{{ $b['department']->name }}</span>
                                @if($b['trend'] === 'up')
                                    <span class="text-rose-500" title="Trending slower">▲</span>
                                @elseif($b['trend'] === 'down')
                                    <span class="text-emerald-500" title="Trending faster">▼</span>
                                @endif
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $levelMeta['chip'] }}">{{ $levelMeta['label'] }}</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full {{ $barColor }} transition-all duration-500" style="width:{{ $pct }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">
                            @if($b['median_hours'] !== null)
                                typically {{ $b['median_hours'] }}h · {{ $b['sla_hours'] }}h SLA ({{ $pct }}%)
                            @else
                                no history yet · {{ $b['sla_hours'] }}h SLA
                            @endif
                            @if($b['current_load'] > 0) · {{ $b['current_load'] }} in queue @endif
                        </p>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">Not enough history to forecast yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Anomaly detection --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-base font-bold text-gray-800">Documents moving abnormally slowly</h3>
                <p class="text-xs text-gray-400">Flagged against the normal range for their type &amp; stage.</p>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($anomalies as $doc)
                    @php $a = $doc->anomaly; @endphp
                    <a href="{{ route('track.show', $doc->tracking_number) }}" class="flex items-center justify-between gap-3 px-5 py-3.5 transition hover:bg-gray-50">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-semibold text-gray-800">{{ $doc->tracking_number }}</p>
                            <p class="truncate text-xs text-gray-400">{{ $doc->document_type }} · {{ $doc->currentDepartment->name ?? 'Unknown' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $a['severity'] === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                +{{ $a['over_by_hours'] }}h over
                            </span>
                            <p class="mt-0.5 text-xs text-gray-400">{{ $a['elapsed_hours'] }}h in @if($a['expected_hours'])· ~{{ $a['expected_hours'] }}h normal @endif</p>
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">No anomalies — everything is moving normally.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endif
