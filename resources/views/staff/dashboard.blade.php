<x-app-layout>
    <div class="page-shell page-shell-loose"
         x-data="reviewPanel(@js([
            'requests' => $requestPayload,
            'mode' => 'staff',
            'openBase' => url('/documents'),
            'completeBase' => url('/documents'),
         ]))">

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <x-stat-card label="Assigned to me" :value="$assignedCount" icon="list" />
            <x-stat-card label="Completed" :value="$completedCount" icon="check" />
            <a href="{{ route('history') }}" class="group flex items-center justify-between rounded-2xl border border-gray-200/90 bg-white p-6 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg">
                <div>
                    <p class="text-sm font-semibold text-emerald-900">History</p>
                    <p class="mt-1 text-sm text-gray-500">View completed work</p>
                </div>
                <span class="text-emerald-700" aria-hidden="true">›</span>
            </a>
        </div>

        <section class="mt-6 space-y-4">
            <h2 class="text-2xl font-bold text-emerald-950 sm:text-3xl">Requests</h2>

            <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-100/90">
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">#</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">File Name</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Tracking ID</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Date</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Category</th>
                                <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-600">Status</th>
                                <th class="px-4 py-3.5 text-right text-xs font-semibold tracking-wider text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template x-for="(req, i) in requests" :key="req.id">
                                <tr class="even:bg-gray-50/50">
                                    <td class="px-4 py-3 font-mono text-sm text-gray-500" x-text="i + 1"></td>
                                    <td class="px-4 py-3 text-sm text-gray-800" x-text="req.citizen_name || ('File ' + req.tracking_number.slice(-5))"></td>
                                    <td class="px-4 py-3"><span class="font-mono text-sm font-semibold text-emerald-800" x-text="req.tracking_number"></span></td>
                                    <td class="px-4 py-3 font-mono text-sm text-gray-500" x-text="req.submitted_at"></td>
                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="req.document_type"></td>
                                    <td class="px-4 py-3">
                                        <span class="pill"
                                              :class="req.status === 'approved' ? 'p-green' : 'p-amber'"
                                              x-text="req.status_label"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="open(req)"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.46 12C3.73 7.94 7.52 5 12 5s8.27 2.94 9.54 7c-1.27 4.06-5.06 7-9.54 7s-8.27-2.94-9.54-7z"/></svg>
                                            Review
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="requests.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                        </div>
                                        <p class="mt-3 text-sm font-semibold text-gray-700">No assigned requests</p>
                                        <p class="mt-1 text-sm text-gray-500">Requests your supervisor assigns to you will appear here.</p>
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
