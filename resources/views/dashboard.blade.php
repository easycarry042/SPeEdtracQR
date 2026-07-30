<x-app-layout>
    @if($dept)
        {{-- Department context rides the title row rather than a full-width band:
             it is orientation, not content. --}}
        <x-slot name="pageActions">
            <span class="hidden min-w-0 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 sm:inline-flex">
                <span class="truncate text-sm font-bold text-emerald-950">
                    {{ $dept->name }} <span class="font-medium text-emerald-700">({{ $dept->code }})</span>
                </span>
            </span>
            @if($completionRate !== null)
                <span class="hidden shrink-0 items-center gap-1.5 text-sm md:inline-flex">
                    <span class="text-xs font-semibold uppercase tracking-wide text-ink-soft">Completion</span>
                    <span class="font-bold text-emerald-950">{{ $completionRate }}%</span>
                </span>
            @endif
        </x-slot>
    @endif

    <div class="page-shell page-shell-loose">

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Total Request" :value="$totalRequests" icon="list" />
            <x-stat-card label="Pending Request" :value="$pendingRequest" icon="hourglass" />
            <x-stat-card label="Completed" :value="$completed" icon="check" />
            <x-stat-card label="At Risk" :value="$atRiskCount" :accent="$atRiskCount > 0 ? 'amber' : 'green'" />
        </div>

        @if(($internalAwaiting ?? 0) > 0)
            <a href="{{ route('requests.index') }}"
               class="flex items-center justify-between gap-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-6 py-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0-4-4m4 4-4 4M16 17H4m0 0 4 4m-4-4 4-4"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-emerald-900">
                            {{ $internalAwaiting }} internal request{{ $internalAwaiting !== 1 ? 's' : '' }} awaiting your office
                        </p>
                        <p class="text-xs text-emerald-800/70">Approve, return, or deny them on the Internal Requests page.</p>
                    </div>
                </div>
                <span class="text-sm font-semibold text-emerald-700">Open inbox →</span>
            </a>
        @endif

        @if($slip)
            <x-routing-slip :slip="$slip" />
        @endif

        @if($atRiskDocuments->isNotEmpty())
        <section class="space-y-4" x-data="{ showAll: false }">
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
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-amber-800">Tracking ID</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-amber-800">Type</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-amber-800">Citizen</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-amber-800">Stage</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-amber-800 min-w-[160px]">SLA Usage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-50 bg-white">
                            @foreach($atRiskDocuments as $doc)
                                @php
                                    $rowIndex   = $loop->index;
                                    $slaHours   = $doc->statusEnum()->slaHours() ?? 0;
                                    $elapsed    = $doc->sla_elapsed_hours ?? 0;
                                    $pct        = $slaHours > 0 ? min(round(($elapsed / $slaHours) * 100), 100) : 0;
                                    $overdue    = $doc->sla_overdue ?? false;
                                    $barColor   = $overdue ? 'bg-red-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500');
                                    $remaining  = $slaHours > 0 ? round($slaHours - $elapsed) : 0;
                                    $overBy     = $slaHours > 0 ? round($elapsed - $slaHours) : 0;
                                @endphp
                                <tr class="{{ $overdue ? 'bg-red-50/60' : '' }}" @if($rowIndex >= 5) x-show="showAll" x-cloak @endif>
                                    <td class="px-4 py-3 text-sm font-mono font-semibold text-emerald-800">
                                        <a href="{{ url('/track/' . $doc->tracking_number) }}" class="hover:underline">{{ $doc->tracking_number }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->document_type }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->citizen_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $doc->statusEnum()->label() }}</td>
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
                @if($atRiskDocuments->count() > 5)
                    <div class="border-t border-amber-100 bg-amber-50/50 px-4 py-3 text-center">
                        <button type="button" @click="showAll = !showAll"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                            <span x-show="!showAll">Show all {{ $atRiskDocuments->count() }} documents</span>
                            <span x-show="showAll" x-cloak>Show top 5 only</span>
                            <svg class="h-4 w-4 transition-transform" :class="showAll ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </section>
        @endif

        {{-- Staff workload & assignment distribution — the department head's
             primary lens: who is handling how much. --}}
        @if($staffWorkload->isNotEmpty())
        @php $maxActive = max(1, (int) $staffWorkload->max('active_count')); @endphp
        <section class="space-y-4">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Staff workload</h2>
                <span class="rounded-full bg-emerald-100 px-3 py-0.5 text-sm font-semibold text-emerald-800">Assignment distribution</span>
            </div>

            <div class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-100/90">
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Staff member</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Active</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Completed</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600 min-w-[160px]">Load</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($staffWorkload as $member)
                                <tr class="even:bg-gray-50/50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                        <a href="{{ route('staff.profile', $member) }}" class="hover:underline">{{ $member->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-emerald-800">{{ $member->active_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $member->completed_count }}</td>
                                    <td class="px-4 py-3">
                                        <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full {{ $member->active_count > 0 ? 'bg-emerald-600' : 'bg-gray-300' }}" style="width:{{ (int) round(($member->active_count / $maxActive) * 100) }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if($staffWorkload->every(fn ($m) => $m->active_count === 0 && $m->completed_count === 0))
                                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No assignments yet — assign pending requests below.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif

        <section class="space-y-4" x-data="reviewPanel(@js([
            'requests' => $pendingPayload,
            'mode' => 'supervisor',
            'staff' => $assignableStaff,
            'assignBase' => url('/documents'),
            'denyBase' => url('/documents'),
        ]))">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Requests</h2>
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Search lives with the table it filters, not in the header. --}}
                    <label class="sr-only" for="requestSearch">Search requests</label>
                    <input type="search" id="requestSearch" x-model="tableQuery" data-kbd-search
                           placeholder="Search name, tracking #, or type…"
                           class="h-ctl w-56 rounded-xl border border-gray-200 bg-white px-4 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                    <a href="{{ route('history') }}" class="inline-flex h-ctl items-center gap-1 rounded-xl border border-gray-200 bg-white px-4 text-sm font-semibold text-emerald-800 shadow-sm transition hover:bg-gray-50 hover:text-emerald-950">
                        Show all
                        <span aria-hidden="true">›</span>
                    </a>
                </div>
            </div>

            <div class="panel">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="activityTable">
                        <thead>
                            <tr class="bg-gray-100/90">
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">#</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">File Name</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Tracking ID</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Department</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Date</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Category</th>
                                <th scope="col" class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Status</th>
                                <th scope="col" class="px-4 py-3.5 text-right text-xs font-semibold tracking-wider text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template x-for="(req, i) in visibleRequests()" :key="req.id">
                                <tr class="even:bg-gray-50/50">
                                    <td class="px-4 py-3 font-mono text-sm text-gray-500" x-text="i + 1"></td>
                                    <td class="px-4 py-3 text-sm text-gray-800" x-text="req.citizen_name || ('File ' + req.tracking_number.slice(-5))"></td>
                                    <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-emerald-800" x-text="req.tracking_number"></span></td>
                                    {{-- Department carries its own code (the THED ID) — one
                                         column instead of two saying the same thing. --}}
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span x-text="req.department || 'Not yet routed'"></span>
                                        <span class="ml-1 font-mono text-xs text-gray-400" x-show="req.thed_id" x-text="'· ' + req.thed_id"></span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-sm text-gray-500" x-text="req.submitted_at"></td>
                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="req.document_type"></td>
                                    <td class="px-4 py-3"><span class="pill p-muted">Pending</span></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="open(req)"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z"/></svg>
                                            Review
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="visibleRequests().length === 0">
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-gray-700" x-text="tableQuery.trim() ? 'No matching requests' : 'No pending requests'"></p>
                                        <p class="mt-1 text-sm text-gray-500" x-text="tableQuery.trim() ? 'Nothing matches your search — clear it to see the full queue.' : 'New citizen submissions awaiting approval will appear here.'"></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @include('partials.review-modal', ['mode' => 'supervisor'])
        </section>
    </div>
</x-app-layout>
