<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-8">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Total Request" :value="$totalRequests" icon="list" />
            <x-stat-card label="Pending Request" :value="$pendingRequest" icon="hourglass" />
            <x-stat-card label="Completed" :value="$completed" icon="check" />
            <div class="group relative overflow-hidden rounded-2xl border {{ $atRiskCount > 0 ? 'border-amber-300 bg-amber-50' : 'border-gray-200/90 bg-white' }} p-6 shadow-md transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-tight {{ $atRiskCount > 0 ? 'text-amber-800' : 'text-emerald-900' }}">At Risk</p>
                        <p class="mt-3 text-4xl font-bold tracking-tight {{ $atRiskCount > 0 ? 'text-amber-700' : 'text-gray-900' }}">{{ $atRiskCount }}</p>
                    </div>
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $atRiskCount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-emerald-800' }}">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        @if($atRiskDocuments->isNotEmpty())
        <section class="space-y-4">
            <div class="flex items-center gap-3">
                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <h2 class="text-2xl font-bold text-amber-800 sm:text-3xl">At-Risk Documents</h2>
                <span class="rounded-full bg-amber-100 px-3 py-0.5 text-sm font-semibold text-amber-800">{{ $atRiskCount }} document{{ $atRiskCount !== 1 ? 's' : '' }} need attention</span>
            </div>

            <div class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-md">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-amber-100">
                        <thead>
                            <tr class="bg-amber-50">
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-800">Tracking ID</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-800">Type</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-800">Citizen</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-800">Department</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-amber-800 min-w-[160px]">SLA Usage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-50 bg-white">
                            @foreach($atRiskDocuments as $doc)
                                @php
                                    $slaHours   = $doc->currentDepartment->sla_hours ?? 0;
                                    $elapsed    = $doc->sla_elapsed_hours ?? 0;
                                    $pct        = $slaHours > 0 ? min(round(($elapsed / $slaHours) * 100), 100) : 0;
                                    $overdue    = $doc->sla_overdue ?? false;
                                    $barColor   = $overdue ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
                                    $remaining  = $slaHours > 0 ? round($slaHours - $elapsed) : 0;
                                    $overBy     = $slaHours > 0 ? round($elapsed - $slaHours) : 0;
                                @endphp
                                <tr class="{{ $overdue ? 'bg-red-50/60' : '' }}">
                                    <td class="px-4 py-3 text-sm font-mono font-semibold text-emerald-800">
                                        <a href="{{ url('/track/' . $doc->tracking_number) }}" class="hover:underline">{{ $doc->tracking_number }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->document_type }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->citizen_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->currentDepartment->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 flex-shrink-0 overflow-hidden rounded-full bg-gray-200">
                                                <div class="h-2 rounded-full {{ $barColor }} transition-all duration-500" style="width:{{ $pct }}%"></div>
                                            </div>
                                            @if($overdue)
                                                <span class="text-xs font-semibold text-red-600 whitespace-nowrap">+{{ $overBy }}h over</span>
                                            @else
                                                <span class="text-xs font-semibold text-amber-700 whitespace-nowrap">~{{ $remaining }}h left</span>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 text-xs text-gray-400">{{ $slaHours }}h SLA · {{ $pct }}% used</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Recent Activity</h2>
                <a href="{{ route('history') }}" class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-gray-50 hover:text-emerald-950">
                    Show all
                    <span aria-hidden="true">›</span>
                </a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="activityTable">
                        <thead>
                            <tr class="bg-gray-100/90">
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">#</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">File Name</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tracking ID</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Date</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Category</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($recentActivity as $activity)
                                <x-document-row
                                    class="activity-row even:bg-gray-50/50"
                                    :index="$loop->iteration"
                                    :date="$activity->created_at->format('M j, Y')"
                                    :tracking="$activity->tracking_number"
                                    :fileName="$activity->citizen_name ?: 'File '.substr($activity->tracking_number, -5)"
                                    :category="$activity->document_type"
                                    :status="$activity->status"
                                />
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center">
                                        <div class="mx-auto flex max-w-sm flex-col items-center">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-gray-700">No documents yet</p>
                                            <p class="mt-1 text-sm text-gray-500">Create the first submission to start tracking it through the office.</p>
                                            @can('create documents')
                                            <button type="button" onclick="openCreateDocumentModal()" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 active:scale-95">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                                New Submission
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
