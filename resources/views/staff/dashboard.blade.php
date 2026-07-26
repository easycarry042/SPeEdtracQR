<x-app-layout>
    <div class="page-shell page-shell-loose"
         x-data="reviewPanel(@js([
            'requests' => $requestPayload,
            'mode' => 'staff',
            'openBase' => url('/documents'),
            'completeBase' => url('/documents'),
            'flow' => $flow,
         ]))">

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-stat-card label="Assigned to me" :value="$assignedCount" icon="list" />
            <x-stat-card label="Completed" :value="$completedCount" icon="check" />
            <a href="{{ route('history') }}" class="group flex items-center justify-between rounded-[10px] border border-hairline bg-paper p-6 transition hover:bg-green-wash">
                <div>
                    <p class="text-sm font-semibold text-green-deep">History</p>
                    <p class="mt-1 text-sm text-ink-soft">View completed work</p>
                </div>
                <span class="text-green" aria-hidden="true">›</span>
            </a>
        </div>

        <section class="mt-6">
            <div class="panel">
                <div class="overflow-x-auto">
                    <table class="reg min-w-full">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>File name</th>
                                <th>Tracking ID</th>
                                <th>Date</th>
                                <th>Category</th>
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
                                    <td class="mono muted" x-text="req.submitted_at"></td>
                                    <td class="muted" x-text="req.document_type"></td>
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
                                <td colspan="7" style="text-align:center;padding:40px 14px;">
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
        </section>

        @include('partials.review-modal', ['mode' => 'staff'])
    </div>
</x-app-layout>
