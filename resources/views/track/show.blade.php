@php
    $statusClass = match($document->status) {
        'completed' => 'bg-green-200 text-green-800',
        'pending' => 'bg-emerald-200 text-emerald-800',
        'returned' => 'bg-rose-200 text-rose-800',
        default => 'bg-yellow-200 text-yellow-800',
    };
@endphp
<x-app-layout>
    @guest
        {{-- Guests don't get the app navbar (and its page title), so keep a heading for the public tracking page --}}
        <x-slot name="header">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Track Document</h1>
        </x-slot>
    @endguest

    @php
        $supervisorView = $supervisorView ?? false;
        $staffView = $staffView ?? false;
        $tabbed = $supervisorView || $staffView;
    @endphp
    <div class="mx-auto grid w-full max-w-7xl grid-cols-1 gap-8 lg:grid-cols-2"
         @if($tabbed) x-data="{ tab: @js($activeTab ?? 'inprogress') }" @endif>
        {{-- Fixed-height panel; the list scrolls inside it --}}
            <div class="flex flex-col rounded-xl border border-[#e6ece8] bg-white p-3 lg:h-[calc(100vh-9rem)]">
                @if($tabbed)
                    @php
                        // (left tab key, label, count) — supervisor: Pending/In Progress,
                        // staff: In Progress/Completed.
                        $leftTab = $supervisorView
                            ? ['key' => 'pending', 'label' => 'Pending', 'count' => $pending->count()]
                            : ['key' => 'inprogress', 'label' => 'In Progress', 'count' => $myActive->count()];
                        $rightTab = $supervisorView
                            ? ['key' => 'inprogress', 'label' => 'In Progress', 'count' => $inProgress->count()]
                            : ['key' => 'completed', 'label' => 'Completed', 'count' => $myCompleted->count()];
                        $leftList = $supervisorView ? $pending : $myActive;
                        $rightList = $supervisorView ? $inProgress : $myCompleted;
                    @endphp

                    {{-- Tabs --}}
                    <div class="mb-3 flex gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1">
                        @foreach([$leftTab, $rightTab] as $t)
                            <button type="button" @click="tab = '{{ $t['key'] }}'"
                                    :class="tab === '{{ $t['key'] }}' ? 'bg-emerald-700 text-white shadow-sm' : 'text-gray-600 hover:bg-white'"
                                    class="flex-1 rounded-md px-3 py-1.5 text-sm font-semibold transition">
                                {{ $t['label'] }} <span class="ml-1 rounded-full bg-black/10 px-1.5 text-xs">{{ $t['count'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- Two lists; clicking an item opens it in the detail box on the right --}}
                    @foreach([['key' => $leftTab['key'], 'list' => $leftList, 'empty' => $supervisorView ? 'No pending requests.' : 'Nothing in progress.'], ['key' => $rightTab['key'], 'list' => $rightList, 'empty' => $supervisorView ? 'No requests in progress.' : 'No completed requests yet.']] as $section)
                        <div x-show="tab === '{{ $section['key'] }}'" @if(! $loop->first) x-cloak @endif class="max-h-[520px] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 lg:max-h-none">
                            @forelse($section['list'] as $item)
                                <a href="{{ route('track.show', ['trackingNumber' => $item->tracking_number, 'tab' => $section['key']]) }}"
                                   class="flex items-center justify-between rounded-lg border p-3 {{ $item->tracking_number === $document->tracking_number ? 'border-[#0f4d28] bg-[#eaf4ee]' : 'border-[#e6ece8] bg-white hover:bg-[#f3f8f5]' }}">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#cfe6d8] text-[#0f4d28]">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-[14px] font-semibold text-[#16211b]">{{ $item->document_type }}</p>
                                            <p class="truncate text-[13px] text-[#51625a]">{{ $item->citizen_name ?: $item->tracking_number }}</p>
                                        </div>
                                    </div>
                                    <div class="ml-2 shrink-0 text-right">
                                        <p class="text-[13px] text-[#51625a]">{{ $item->created_at->format('m/d/y') }}</p>
                                        <span class="text-xl text-[#51625a]">›</span>
                                    </div>
                                </a>
                            @empty
                                <p class="px-2 py-8 text-center text-sm text-gray-400">{{ $section['empty'] }}</p>
                            @endforelse
                        </div>
                    @endforeach
                @else
                    <div class="max-h-[520px] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 lg:max-h-none">
                        @foreach($documents as $item)
                            <a href="{{ route('track.show', $item->tracking_number) }}" class="flex items-center justify-between rounded-lg border p-3 {{ $item->tracking_number === $document->tracking_number ? 'border-[#0f4d28] bg-[#eaf4ee]' : 'border-[#e6ece8] bg-white hover:bg-[#f3f8f5]' }}">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#cfe6d8] text-[#0f4d28]">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-[14px] font-semibold text-[#16211b]">{{ $item->document_type }}</p>
                                        <p class="text-[13px] text-[#51625a]">{{ $item->status }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-[13px] text-[#51625a]">{{ $item->created_at->format('m/d/y') }}</p>
                                    <span class="text-xl text-[#51625a]">›</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

        {{-- Fixed-height panel; the document details scroll inside it --}}
        <div class="rounded-xl border border-[#e6ece8] bg-white p-6 lg:h-[calc(100vh-9rem)] lg:overflow-y-auto">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#cfe6d8] text-[#0f4d28]">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-[#16211b]">{{ $document->document_type }}</p>
                        <p class="text-[13px] text-[#51625a]">{{ $document->citizen_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-[#51625a]">Tracking ID:</p>
                    <p class="text-xl font-extrabold text-[#0f4d28] font-mono">{{ $document->tracking_number }}</p>
                </div>
            </div>

            @php
                $isPendingReview = $supervisorView && $document->statusEnum() === \App\Enums\DocumentStatus::Pending;
                $isStaffAssigned = $staffView && (int) $document->assigned_to === (int) auth()->id();
                $isStaffApprovable = $isStaffAssigned && $document->status === 'approved';
            @endphp

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-status-badge :status="$document->status" />
                <a href="{{ route('documents.edit', $document) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    Edit details
                </a>
            </div>

            {{-- ── Pending review + assign (supervisor) — inline, no modal ───────── --}}
            @if($isPendingReview)
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/50 p-5"
                     x-data="{
                        staffId: '',
                        submitting: false,
                        denyOpen: false,
                        denyReason: '',
                        approve() {
                            if (!this.staffId || this.submitting) return;
                            this.submitting = true;
                            const fd = new FormData();
                            fd.append('assigned_to', this.staffId);
                            fetch('{{ route('documents.assign-approve', $document) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: fd,
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.index') }}'; }
                                else { this.submitting = false; alert('Could not approve. Please try again.'); }
                            }).catch(() => { this.submitting = false; alert('Network error. Please try again.'); });
                        },
                        confirmDeny() {
                            if (this.submitting) return;
                            this.submitting = true;
                            const fd = new FormData();
                            if (this.denyReason.trim()) fd.append('reason', this.denyReason.trim());
                            fetch('{{ route('documents.deny', $document) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: fd,
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.index') }}'; }
                                else { this.submitting = false; alert('Could not deny. Please try again.'); }
                            }).catch(() => { this.submitting = false; alert('Network error. Please try again.'); });
                        }
                     }">
                    <p class="text-sm font-bold text-emerald-900">Review &amp; assign</p>

                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3 border-b border-emerald-100 pb-1"><dt class="text-emerald-800/70">Email</dt><dd class="text-right font-medium text-emerald-950">{{ $document->citizen_email ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-emerald-100 pb-1"><dt class="text-emerald-800/70">Contact</dt><dd class="text-right font-medium text-emerald-950">{{ $document->citizen_contact ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-emerald-100 pb-1"><dt class="text-emerald-800/70">Submitted</dt><dd class="text-right font-medium text-emerald-950">{{ $document->created_at?->format('M j, Y g:i A') }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-emerald-100 pb-1"><dt class="text-emerald-800/70">Purpose</dt><dd class="text-right font-medium text-emerald-950">{{ $document->purpose ?: '—' }}</dd></div>
                    </dl>

                    @if($document->description)
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-emerald-800/70">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-emerald-950">{{ $document->description }}</p>
                    @endif

                    <label class="mt-4 block text-sm font-semibold text-emerald-900">Assign to Staff <span class="text-red-500">*</span></label>
                    <select x-model="staffId" class="mt-1 w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        <option value="">Select a staff member…</option>
                        @foreach($assignableStaff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <button type="button" @click="denyOpen = true" :disabled="submitting"
                                class="rounded-xl border border-red-200 bg-red-50 px-5 py-2.5 text-sm font-semibold text-red-700 transition enabled:hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50">
                            Deny
                        </button>
                        <button type="button" @click="approve()" :disabled="!staffId || submitting"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>

                    {{-- Deny reason modal --}}
                    <div x-show="denyOpen" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                         @keydown.escape.window="denyOpen = false" @click.self="denyOpen = false">
                        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl" x-show="denyOpen" x-transition>
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-base font-bold text-gray-900">Deny request</h3>
                                <p class="mt-0.5 text-sm text-gray-500">This rejects the request. You can add an optional reason for the record.</p>
                            </div>
                            <div class="px-6 py-5">
                                <label class="block text-sm font-semibold text-gray-700">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea x-model="denyReason" rows="4" maxlength="1000" placeholder="e.g. Incomplete requirements, duplicate request…"
                                          class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm shadow-sm focus:border-red-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-500/20"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                                <button type="button" @click="denyOpen = false" :disabled="submitting"
                                        class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
                                    Cancel
                                </button>
                                <button type="button" @click="confirmDeny()" :disabled="submitting"
                                        class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!submitting">Deny request</span>
                                    <span x-show="submitting">Denying…</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Final approve (assigned staff) — only at Approved stage ─────── --}}
            @if($isStaffApprovable)
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50/50 p-5"
                     x-data="{
                        submitting: false,
                        complete() {
                            if (this.submitting) return;
                            this.submitting = true;
                            fetch('{{ route('documents.review.complete', $document) }}', {
                                method: 'PATCH',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.index') }}'; }
                                else { this.submitting = false; r.json().then(d => alert(d.message || 'Could not complete. Advance to Approved first.')); }
                            }).catch(() => { this.submitting = false; alert('Network error. Please try again.'); });
                        }
                     }">
                    <p class="text-sm font-bold text-emerald-900">Ready to complete</p>
                    <p class="mt-1 text-sm text-emerald-800/80">This request is at <strong>Approved</strong>. Approving marks it <strong>Completed</strong> and moves it to your History.</p>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="complete()" :disabled="submitting"
                                class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition enabled:hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>
                </div>
            @endif

            @if(session('status'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif

            @if($document->attachments->isNotEmpty())
                <div class="mt-5">
                    <p class="text-[14px] font-bold text-[#16211b]">Attached Files</p>
                    <x-document-images :document="$document" :limit="12" size="lg" class="mt-2" :manage="true" />
                    <p class="mt-1 text-[12px] text-[#7c8b83]">Hover a file and tap × to remove one placed by mistake.</p>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                    Print QR sticker
                </a>
                <a href="{{ route('scan.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-100">
                    Open scanner
                </a>
            </div>

            {{-- Predictive insights (self-hosted analytics) --}}
            @if(!empty($anomaly))
                <div class="mt-6 rounded-xl border {{ $anomaly['severity'] === 'high' ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-amber-300 bg-amber-50 text-amber-900' }} p-4">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14.14A2 2 0 0 0 3.83 21h16.34a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-bold">Anomaly: moving unusually slowly</p>
                            <p class="mt-0.5 text-sm">
                                Sitting here <strong>{{ $anomaly['elapsed_hours'] }}h</strong>{{ $anomaly['expected_hours'] ? ' — similar documents typically take ~'.$anomaly['expected_hours'].'h' : '' }}.
                                That is <strong>{{ $anomaly['over_by_hours'] }}h</strong> over the normal range.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6">
                <x-eta-estimate :prediction="$prediction ?? null" :document="$document" />
            </div>

            <div class="mt-8">
                <div class="mb-3 text-[14px] font-bold text-[#16211b]">Status Progress</div>
                @if($document->assigned_to)
                    <p class="mb-2 text-[13px] text-[#51625a]">
                        Assigned to <span class="font-semibold text-[#0f4d28]">{{ $document->assignedTo->name ?? '—' }}</span>
                    </p>
                @endif
                <x-routing-stepper :document="$document" :controls="true" />
            </div>

            {{-- Origin / Creation — how this document entered the office --}}
            <div class="mt-8">
                <h3 class="text-2xl font-extrabold text-[#16211b]">Origin</h3>
                <div class="mt-3 rounded-xl border border-[#eaf4ee] bg-[#fafcfb] p-4 text-[14px] text-[#334b40]">
                    <div class="mb-3">
                        <x-document-origin :source="$document->source" :by="$document->creator?->name" :at="$document->created_at" />
                    </div>

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-[#51625a]">Submitted by</dt>
                            <dd class="font-medium">{{ $document->citizen_name ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-[#51625a]">Email</dt>
                            <dd class="font-medium break-all">{{ $document->citizen_email ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-[#51625a]">Contact</dt>
                            <dd class="font-medium">{{ $document->citizen_contact ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-[#51625a]">Created</dt>
                            <dd class="font-medium">{{ $document->created_at?->format('M d, Y \a\t h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-[#51625a]">{{ $document->source === 'online' ? 'Entered' : 'Encoded by' }}</dt>
                            <dd class="font-medium">{{ $document->source === 'online' ? 'Online submission (citizen self-service)' : ($document->creator?->name ?? 'Staff') }}</dd>
                        </div>
                        @if($document->purpose)
                            <div class="flex gap-2">
                                <dt class="w-28 shrink-0 text-[#51625a]">Purpose</dt>
                                <dd class="font-medium">{{ $document->purpose }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($document->description)
                        <p class="mt-3 border-t border-[#eaf4ee] pt-3"><span class="text-[#51625a]">Details:</span> {{ $document->description }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-8">
                <h3 class="text-2xl font-extrabold text-[#16211b]">Logs</h3>
                <div class="mt-3 space-y-2">
                    @foreach($timeline as $log)
                        <div class="flex items-center justify-between border-b border-[#eaf4ee] py-2">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-green-600"></span>
                                <span class="text-[14px] text-[#51625a]">{{ $log['event'] }}</span>
                            </div>
                            <span class="text-[13px] font-bold text-[#0f4d28]">{{ $log['timestamp'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Collaboration feed (staff) ─────────────────────────────────── --}}
            @php
                $u = auth()->user();
                $canPost = $u && ($u->can('manage system') || $u->can('assign documents') || (
                    $u->can('advance documents') && $document->assigned_to !== null
                    && (int) $document->assigned_to === (int) $u->id
                ));
            @endphp
            <div class="mt-8" id="collab" data-document-id="{{ $document->id }}">
                <h3 class="text-2xl font-extrabold text-[#16211b]">Collaboration</h3>
                <p class="mt-1 text-[13px] text-[#51625a]">Post updates and notes. <strong>Internal</strong> notes are staff-only; <strong>Visible to citizen</strong> posts appear on the public tracking page and email the citizen.</p>

                @if($canPost)
                    <form method="POST" action="{{ route('documents.comments.store', $document) }}" class="mt-4 rounded-xl border border-[#e6ece8] bg-white p-4">
                        @csrf
                        <textarea name="body" rows="3" required maxlength="5000" placeholder="Write an update…"
                                  class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"></textarea>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-4 text-sm">
                                <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="internal" checked> Internal note</label>
                                <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="public"> Visible to citizen</label>
                            </div>
                            <button type="submit" class="rounded-lg bg-[#0f4d28] px-4 py-2 text-sm font-bold text-white hover:bg-[#0b3a1e]">Post</button>
                        </div>
                    </form>
                @endif

                <div id="collabFeed" class="mt-4 space-y-3">
                    @forelse($document->comments as $comment)
                        @include('track.partials.comment', ['comment' => $comment, 'canPost' => $canPost, 'document' => $document])
                    @empty
                        <p id="collabEmpty" class="text-sm italic text-gray-400">No posts yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- Opening an assigned, in-progress request (clicking it) moves it to
         "In Review", mirroring the old Review action. Reloads once so the badge
         and stepper reflect the new stage. --}}
    @if(($isStaffAssigned ?? false) && $document->status === 'in_progress')
        <script>
            fetch('{{ route('documents.review.open', $document) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(r => r.ok ? r.json() : null).then(d => {
                if (d && d.status === 'in_review') { window.location.reload(); }
            }).catch(() => {});
        </script>
    @endif

    <script>
        (function () {
            const root = document.getElementById('collab');
            if (!root || !window.Echo) return;
            const id = root.dataset.documentId;
            const feed = document.getElementById('collabFeed');
            const badge = { internal: 'background:#eef2ef;color:#5b6b62;', public: 'background:#eef5f0;color:#0f5c2e;', system: 'background:#fbf3e0;color:#8a6d1f;' };

            window.Echo.private('documents.' + id + '.staff').listen('.comment', (e) => {
                document.getElementById('collabEmpty')?.remove();
                if (document.getElementById('comment-' + e.id) || document.getElementById('reply-' + e.id)) return;
                const label = e.author_type === 'public' ? 'public' : (e.author_type === 'system' ? 'system' : (e.visibility || 'internal'));
                const tag = e.author_type === 'system' ? 'system' : (e.visibility || 'internal');
                const html = '<div class="rounded-xl border border-[#e6ece8] bg-white p-3" id="comment-' + e.id + '">' +
                    '<div class="flex items-center justify-between"><span class="text-[13px] font-bold text-[#16211b]">' + e.author +
                    ' <span class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-semibold" style="' + (badge[tag] || badge.internal) + '">' + tag + '</span></span>' +
                    '<span class="text-[12px] text-[#51625a]">' + (e.timestamp || '') + '</span></div>' +
                    '<p class="mt-1 whitespace-pre-wrap text-[14px] text-[#33433b]"></p></div>';
                const wrap = document.createElement('div');
                wrap.innerHTML = html;
                wrap.querySelector('p').textContent = e.body;
                feed.prepend(wrap.firstChild);
            });
        })();
    </script>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const base = @json(url('/documents'));
            document.querySelectorAll('.js-track-complete').forEach(btn => {
                btn.addEventListener('click', async function () {
                    if (!confirm('Mark this document as completed?')) return;
                    btn.disabled = true;
                    const res = await fetch(base + '/' + encodeURIComponent(this.dataset.tracking) + '/complete', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    if (res.ok) location.reload();
                    else { alert('Could not complete document.'); btn.disabled = false; }
                });
            });
        })();
    </script>
</x-app-layout>
