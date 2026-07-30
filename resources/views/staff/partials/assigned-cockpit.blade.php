{{--
    The assigned-work cockpit: every request assigned to the signed-in staff
    member, with the Review modal that drives triage → advance → complete. This
    used to be a separate "Requests" tab; it now lives in My Profile so staff
    have one place for their identity and their work.
--}}
<div x-data="reviewPanel(@js([
        'requests' => $requestPayload,
        'mode' => 'staff',
        'openBase' => url('/documents'),
        'completeBase' => url('/documents'),
        'resourceBase' => url('/resources'),
        'flow' => $flow,
     ]))">

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-soft">
            <b class="text-green-deep">{{ $assignedCount }}</b> active {{ \Illuminate\Support\Str::plural('request', $assignedCount) }} ·
            <b class="text-green-deep">{{ $completedCount }}</b> completed
        </p>
        <a href="{{ route('history') }}" class="cr-btn cr-btn-sm">View history</a>
    </div>

    <div class="panel">
        <div class="overflow-x-auto">
            {{-- w-full fills the panel on desktop; min-width forces the
                 overflow-x-auto wrapper to scroll instead of cramping the
                 columns on a narrow phone. --}}
            <table class="reg w-full min-w-[900px]">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File name</th>
                        <th>Tracking ID</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(req, i) in requests" :key="req.id">
                        <tr>
                            <td class="idx mono" x-text="i + 1"></td>
                            <td class="nm" x-text="req.citizen_name || ('File ' + req.tracking_number.slice(-5))"></td>
                            <td><span class="code" x-text="req.tracking_number"></span></td>
                            {{-- Department shows its code (the THED ID) inline. --}}
                            <td class="muted">
                                <span x-text="req.department || 'Not yet routed'"></span>
                                <span class="mono ml-1 text-xs opacity-70" x-show="req.thed_id" x-text="'· ' + req.thed_id"></span>
                            </td>
                            <td class="mono muted" x-text="req.submitted_at"></td>
                            <td class="muted" x-text="req.document_type"></td>
                            <td class="muted" x-text="req.schedule_label || '—'"></td>
                            <td>
                                <span class="pill" :class="pillClass(req)"
                                      x-text="req.needs_triage ? 'New assignment' : req.status_label"></span>
                            </td>
                            <td style="text-align:right">
                                <button type="button" @click="open(req)" class="cr-btn cr-btn-sm" :class="req.needs_triage ? 'cr-btn-primary' : ''">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z"/></svg>
                                    <span x-text="req.needs_triage ? 'Review assignment' : 'Review'"></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="requests.length === 0">
                        <td colspan="9" style="text-align:center;padding:40px 14px;">
                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-ink">No assigned requests</p>
                                <p class="mt-1 text-sm text-ink-soft">Requests your supervisor assigns to you will appear here.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if($unclaimed->isNotEmpty())
        <div class="panel sp-list mt-4" style="padding:12px;">
            <div style="font-size:13px;font-weight:700;color:var(--green-deep);margin-bottom:8px;">Unclaimed / Available</div>
            @foreach($unclaimed as $doc)
                <div class="sp-row">
                    <div><div class="ty">{{ $doc->document_type }}</div><span class="code">{{ $doc->tracking_number }}</span></div>
                    @can('accept documents')
                        <form method="POST" action="{{ route('documents.accept', $doc) }}">
                            @csrf @method('PATCH')
                            <button class="cr-btn cr-btn-sm cr-btn-primary">Accept</button>
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>
    @endif

    @include('partials.review-modal', ['mode' => 'staff', 'scheduleResources' => $scheduleResources])
</div>
