<x-app-layout>
    @php
        $supervisorView = $supervisorView ?? false;
    @endphp
    {{-- The Pending / In Progress list is kept compact (1/3) so the ticket
         handling panel gets the room (2/3). --}}
    <div class="mx-auto grid w-full max-w-7xl grid-cols-1 gap-8 lg:grid-cols-3"
         x-data="{ tab: @js($activeTab ?? 'inprogress') }">
        {{-- Fixed-height panel; the list scrolls inside it --}}
        <div class="panel flex flex-col p-3 lg:col-span-1 lg:h-[calc(100vh-9rem)]">
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
            <div class="segchips mb-3 w-full">
                @foreach([$leftTab, $rightTab] as $t)
                    <button type="button" @click="tab = '{{ $t['key'] }}'"
                            :class="tab === '{{ $t['key'] }}' ? 'on' : ''"
                            class="flex-1 justify-center">
                        {{ $t['label'] }}
                        <span class="sp-group-count ml-1.5">{{ $t['count'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Two lists; clicking an item opens it in the detail box on the right --}}
            @foreach([['key' => $leftTab['key'], 'list' => $leftList, 'empty' => $supervisorView ? 'No pending requests.' : 'Nothing in progress.'], ['key' => $rightTab['key'], 'list' => $rightList, 'empty' => $supervisorView ? 'No requests in progress.' : 'No completed requests yet.']] as $section)
                <div x-show="tab === '{{ $section['key'] }}'" @if(! $loop->first) x-cloak @endif class="max-h-[520px] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 lg:max-h-none">
                    @forelse($section['list'] as $item)
                        <a href="{{ route('track.show', ['trackingNumber' => $item->tracking_number, 'tab' => $section['key']]) }}"
                           class="flex items-center justify-between rounded-lg border p-3 {{ $item->tracking_number === $document->tracking_number ? 'border-green-deep bg-green-wash' : 'border-hairline bg-paper hover:bg-green-wash/60' }}">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-wash text-green-deep">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-[14px] font-semibold text-ink">{{ $item->document_type }}</p>
                                    <p class="truncate text-[13px] text-ink-soft">{{ $item->citizen_name ?: $item->tracking_number }}</p>
                                </div>
                            </div>
                            <div class="ml-2 shrink-0 text-right">
                                <p class="text-[13px] text-ink-soft">{{ $item->created_at->format('m/d/y') }}</p>
                                <span class="text-xl text-ink-soft">›</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-2 py-8 text-center text-sm text-ink-soft">{{ $section['empty'] }}</p>
                    @endforelse
                </div>
            @endforeach
        </div>

        {{-- Fixed-height panel; the document details scroll inside it --}}
        <div class="panel p-6 lg:col-span-2 lg:h-[calc(100vh-9rem)] lg:overflow-y-auto">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-green-wash text-green-deep">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-ink">{{ $document->document_type }}</p>
                        <p class="text-[13px] text-ink-soft">{{ $document->citizen_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Tracking ID</p>
                    <span class="id-chip mt-1 text-[15px] font-bold text-green-deep">{{ $document->tracking_number }}</span>
                </div>
            </div>

            @php
                $isPendingReview = $supervisorView && $document->statusEnum() === \App\Enums\DocumentStatus::Pending;
                $isStaffAssigned = $staffView && (int) $document->assigned_to === (int) auth()->id();
                $isStaffApprovable = $isStaffAssigned && $document->status === 'approved';
                $canEditDetails = \App\Support\AssignmentScope::userCanEditDocument($document);
            @endphp

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-status-badge :status="$document->status" />
                @if($canEditDetails)
                    <a href="{{ route('documents.edit', $document) }}" class="cr-btn">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                        Edit details
                    </a>
                @endif
            </div>

            {{-- Responsible & contact — always visible: who/where handles this
                 ticket, plus how to reach the requester. --}}
            @unless($document->isInternal())
            <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">THED ID</dt><dd class="text-right font-medium text-ink">{{ $document->department?->code ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Department</dt><dd class="text-right font-medium text-ink">{{ $document->department?->name ?: 'Not yet routed' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Staff assigned</dt><dd class="text-right font-medium text-ink">{{ $document->assignedTo?->name ?: 'Not yet assigned' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Email</dt><dd class="text-right font-medium text-ink break-all">{{ $document->citizen_email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Contact</dt><dd class="text-right font-medium text-ink">{{ $document->citizen_contact ?: '—' }}</dd></div>
            </dl>
            @endunless

            {{-- Physical custody trail (shared with the internal-request panel). --}}
            @if(! $document->statusEnum()->isTerminal())
                @include('partials.custody-scan', ['document' => $document, 'custody' => $custody])
            @endif

            {{-- ── QR-gated release: Completed docs are claimed at the counter ── --}}
            @if($document->status === 'completed')
                @php
                    $canRelease = auth()->user()->can('scan documents') || auth()->user()->can('assign documents') || auth()->user()->can('manage system');
                @endphp
                @if($errors->has('release'))
                    <div class="gate-errors" role="alert"><p>{{ $errors->first('release') }}</p></div>
                @endif
                @if($document->claimed_at)
                    <div class="release-stamp">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                        <span>Released to citizen on <b class="mono">{{ $document->claimed_at->format('M d, Y h:i A') }}</b>{{ $document->releasedBy ? ' by '.$document->releasedBy->name : '' }}</span>
                    </div>
                @elseif($canRelease)
                    <div class="release-panel" x-data="{ open: false }">
                        <div class="rp-head">
                            <p><b>Ready for release.</b> The citizen claims this by presenting their QR code at the counter.</p>
                            <button type="button" class="cr-btn cr-btn-primary cr-btn-sm" @click="open = !open">Release to citizen</button>
                        </div>
                        <div x-show="open" x-cloak class="rp-confirm">
                            <p>Confirm the person at the counter matches the record:</p>
                            <p class="rp-name">{{ $document->citizen_name ?? 'No citizen name on record' }}</p>
                            <form method="POST" action="{{ route('documents.release', $document) }}" class="mt-2 flex flex-wrap gap-2">
                                @csrf @method('PATCH')
                                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Confirm — mark as released</button>
                                <button type="button" class="cr-btn cr-btn-sm" @click="open = false">Cancel</button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Authenticity seal: signed QR anyone can scan to verify. --}}
                @if($sealSvg)
                    <div class="seal-panel">
                        <img src="data:image/svg+xml;base64,{{ $sealSvg }}" alt="Authenticity verification QR code" width="84" height="84">
                        <div>
                            <p class="seal-title">Authenticity seal</p>
                            <p class="seal-sub">Anyone scanning this QR gets an official yes/no that this document is genuine — the link is cryptographically signed.</p>
                            <a href="{{ $verifyUrl }}" target="_blank" class="cr-link">Open verification page →</a>
                        </div>
                    </div>
                @endif
            @endif

            {{-- ── Pending review + assign (supervisor) — inline, no modal ───────── --}}
            @if($isPendingReview)
                <div class="mt-5 rounded-[10px] border border-hairline-strong bg-green-wash/40 p-5"
                     x-data="{
                        staffId: '',
                        submitting: false,
                        islandError: '',
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
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; this.islandError = 'Could not approve. Please try again.'; }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
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
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; this.islandError = 'Could not deny. Please try again.'; }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
                        }
                     }">
                    <p class="text-sm font-bold text-green-deep">Review &amp; assign</p>

                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Submitted</dt><dd class="text-right font-medium text-ink">{{ $document->created_at?->format('M j, Y g:i A') }}</dd></div>
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Purpose</dt><dd class="text-right font-medium text-ink">{{ $document->purpose ?: '—' }}</dd></div>
                        @if($document->quantity)
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Quantity</dt><dd class="text-right font-medium text-ink">{{ number_format($document->quantity) }}</dd></div>
                        @endif
                        @if($document->needed_by)
                        <div class="flex justify-between gap-3 border-b border-hairline pb-1"><dt class="text-ink-soft">Needed by</dt><dd class="text-right font-medium text-ink">{{ $document->needed_by->format('M j, Y') }}</dd></div>
                        @endif
                    </dl>

                    @if($document->description)
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-ink-soft">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $document->description }}</p>
                    @endif

                    <p x-show="islandError" x-cloak x-text="islandError" class="mt-3 rounded-lg border border-status-red-wash bg-status-red-wash px-3 py-2 text-sm font-semibold text-status-red" role="alert"></p>

                    <label class="mt-4 block text-sm font-semibold text-green-deep">Assign to Staff <span class="text-status-red">*</span></label>
                    <select x-model="staffId" class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20">
                        <option value="">Select a staff member…</option>
                        @foreach($assignableStaff as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <button type="button" @click="denyOpen = true" :disabled="submitting"
                                class="cr-btn cr-btn-danger !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            Deny
                        </button>
                        <button type="button" @click="approve()" :disabled="!staffId || submitting"
                                class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>

                    {{-- Deny reason modal --}}
                    <div x-show="denyOpen" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                         @keydown.escape.window="denyOpen = false" @click.self="denyOpen = false">
                        <div class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl" x-show="denyOpen" x-transition>
                            <div class="border-b border-hairline px-6 py-4">
                                <h3 class="text-base font-bold text-ink">Deny request</h3>
                                <p class="mt-0.5 text-sm text-ink-soft">This rejects the request. You can add an optional reason for the record.</p>
                            </div>
                            <div class="px-6 py-5">
                                <label class="block text-sm font-semibold text-ink">Reason <span class="font-normal text-ink-soft">(optional)</span></label>
                                <textarea x-model="denyReason" rows="4" maxlength="1000" placeholder="e.g. Incomplete requirements, duplicate request…"
                                          class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-status-red focus:outline-none focus:ring-2 focus:ring-status-red/20"></textarea>
                            </div>
                            <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                                <button type="button" @click="denyOpen = false" :disabled="submitting"
                                        class="cr-btn !px-5 !py-2.5 !text-sm disabled:opacity-50">
                                    Cancel
                                </button>
                                <button type="button" @click="confirmDeny()" :disabled="submitting"
                                        class="cr-btn cr-btn-danger !border-status-red !bg-status-red !text-white hover:!bg-[#6e1f1f] !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
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
                <div class="mt-5 rounded-[10px] border border-hairline-strong bg-green-wash/40 p-5"
                     x-data="{
                        submitting: false,
                        islandError: '',
                        complete() {
                            if (this.submitting) return;
                            this.submitting = true;
                            fetch('{{ route('documents.review.complete', $document) }}', {
                                method: 'PATCH',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            }).then(r => {
                                if (r.ok) { window.location.href = '{{ route('track.show', $document->tracking_number) }}'; }
                                else { this.submitting = false; r.json().then(d => { this.islandError = d.message || 'Could not complete. Advance to Approved first.'; }); }
                            }).catch(() => { this.submitting = false; this.islandError = 'Network error. Please try again.'; });
                        }
                     }">
                    <p class="text-sm font-bold text-green-deep">Ready to complete</p>
                    <p class="mt-1 text-sm text-ink-soft">This request is at <strong>Approved</strong>. Approving marks it <strong>Completed</strong> and moves it to your History.</p>
                    <p x-show="islandError" x-cloak x-text="islandError" class="mt-3 rounded-lg border border-status-red-wash bg-status-red-wash px-3 py-2 text-sm font-semibold text-status-red" role="alert"></p>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="complete()" :disabled="submitting"
                                class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!submitting">Approve</span>
                            <span x-show="submitting">Approving…</span>
                        </button>
                    </div>
                </div>
            @endif

            @if(session('status'))
                <div class="mt-4 rounded-lg border border-hairline bg-green-wash px-4 py-2 text-sm font-semibold text-green-deep">{{ session('status') }}</div>
            @endif

            @if($document->attachments->isNotEmpty())
                <div class="mt-5">
                    <p class="text-[14px] font-bold text-ink">Attached Files</p>
                    <x-document-images :document="$document" :limit="12" size="lg" class="mt-2" :manage="true" />
                    <p class="mt-1 text-[12px] text-ink-soft">Hover a file and tap × to remove one placed by mistake.</p>
                </div>
            @endif

            @if($document->requirements->isNotEmpty())
                @php $canVerify = auth()->check() && $document->canBeAdvancedBy(auth()->user()); @endphp
                <div class="mt-6">
                    <p class="text-[14px] font-bold text-ink">Requirements</p>
                    <p class="mt-0.5 text-[12px] text-ink-soft">Review each supporting document. Returning or rejecting one emails the citizen your comment; returned items can be re-uploaded from their tracking page.</p>
                    <ul class="mt-2 divide-y divide-hairline overflow-hidden rounded-[10px] border border-hairline">
                        @foreach($document->requirements as $req)
                            @php
                                $badge = match($req->review_status) {
                                    'approved' => 'bg-green-wash text-green-deep',
                                    'needs_revision' => 'bg-status-amber-wash text-status-amber',
                                    'rejected' => 'bg-status-red-wash text-status-red',
                                    default => 'bg-hairline/40 text-ink-soft',
                                };
                            @endphp
                            <li class="px-3 py-2.5 {{ $req->isApproved() ? 'bg-green-wash/40' : '' }}">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[13px] font-medium text-ink">{{ $req->label }}</span>
                                            <span class="text-[10.5px] font-semibold {{ $req->is_mandatory ? 'text-status-red' : 'text-ink-soft' }}">{{ $req->is_mandatory ? 'Required' : 'Optional' }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[10.5px] font-semibold {{ $badge }}">{{ $req->reviewStatusLabel() }}</span>
                                            @if($req->uploaded_file_path)
                                                <a href="{{ route('documents.requirements.file', [$document, $req]) }}" target="_blank" class="text-[12px] font-semibold text-green underline">View upload</a>
                                            @else
                                                <span class="text-[12px] text-ink-soft">— bring/verify original</span>
                                            @endif
                                        </div>
                                        @if($req->review_comment)
                                            <p class="mt-1 text-[12px] text-ink-soft"><span class="font-semibold">Comment:</span> {{ $req->review_comment }}</p>
                                        @endif
                                        @if($req->isVerified())
                                            <p class="mt-0.5 text-[11px] text-green-deep">✓ Original verified{{ $req->verifier ? ' by '.$req->verifier->name : '' }} · {{ $req->verified_at?->format('M d, Y g:i A') }}</p>
                                        @endif
                                    </div>
                                    @if($canVerify)
                                        <form method="POST" action="{{ route('documents.requirements.toggle', [$document, $req]) }}" class="shrink-0">
                                            @csrf
                                            <button type="submit" class="cr-btn cr-btn-sm {{ $req->isVerified() ? '' : 'cr-btn-primary' }}">
                                                {{ $req->isVerified() ? 'Unverify' : 'Verify original' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                @if($canVerify)
                                    <form method="POST" action="{{ route('documents.requirements.review', [$document, $req]) }}" class="mt-2">
                                        @csrf
                                        <label for="review-comment-{{ $req->id }}" class="sr-only">Review comment for {{ $req->label }}</label>
                                        <textarea id="review-comment-{{ $req->id }}" name="review_comment" rows="2" placeholder="Comment (required when returning or rejecting)" class="w-full rounded-[8px] border border-hairline-strong bg-white px-2 py-1.5 text-[12.5px] text-ink focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">{{ $req->review_comment }}</textarea>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button type="submit" name="review_status" value="approved" class="cr-btn cr-btn-sm cr-btn-primary">Approve</button>
                                            <button type="submit" name="review_status" value="needs_revision" class="cr-btn cr-btn-sm">Needs revision</button>
                                            <button type="submit" name="review_status" value="rejected" class="cr-btn cr-btn-sm">Reject</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 flex flex-wrap gap-2">
                @if($document->isInternal() && auth()->check())
                    <a href="{{ route('requests.show', $document) }}" class="cr-btn cr-btn-primary">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z"/></svg>
                        Open internal request chain
                    </a>
                @endif
                <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="cr-btn {{ $document->isInternal() ? '' : 'cr-btn-primary' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                    Print QR sticker
                </a>
                <a href="{{ route('track.index', ['find' => 1]) }}" class="cr-btn">
                    Open scanner
                </a>
            </div>

            {{-- Predictive insights (self-hosted analytics) --}}
            @if(!empty($anomaly))
                <div class="mt-6 rounded-[10px] border {{ $anomaly['severity'] === 'high' ? 'border-status-red-wash bg-status-red-wash text-status-red' : 'border-status-amber-wash bg-status-amber-wash text-status-amber' }} p-4">
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
                <div class="mb-3 text-[14px] font-bold text-ink">Status Progress</div>
                @if($document->assigned_to)
                    <p class="mb-2 text-[13px] text-ink-soft">
                        Assigned to <span class="font-semibold text-green-deep">{{ $document->assignedTo->name ?? '—' }}</span>
                    </p>
                @endif
                <x-routing-stepper :document="$document" :controls="true" />
            </div>

            {{-- Origin / Creation — how this document entered the office --}}
            <div class="panel mt-8">
                <div class="ph"><h2>Origin</h2></div>
                <div class="pb text-[14px] text-ink">
                    <div class="mb-3">
                        <x-document-origin :source="$document->source" :by="$document->creator?->name" :at="$document->created_at" />
                    </div>

                    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-ink-soft">Submitted by</dt>
                            <dd class="font-medium">{{ $document->citizen_name ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-ink-soft">Email</dt>
                            <dd class="font-medium break-all">{{ $document->citizen_email ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-ink-soft">Contact</dt>
                            <dd class="font-medium">{{ $document->citizen_contact ?: '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-ink-soft">Created</dt>
                            <dd class="font-medium">{{ $document->created_at?->format('M d, Y \a\t h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="w-28 shrink-0 text-ink-soft">{{ $document->source === 'online' ? 'Entered' : 'Encoded by' }}</dt>
                            <dd class="font-medium">{{ $document->source === 'online' ? 'Online submission (citizen self-service)' : ($document->creator?->name ?? 'Staff') }}</dd>
                        </div>
                        @if($document->purpose)
                            <div class="flex gap-2">
                                <dt class="w-28 shrink-0 text-ink-soft">Purpose</dt>
                                <dd class="font-medium">{{ $document->purpose }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($document->description)
                        <p class="mt-3 border-t border-hairline pt-3"><span class="text-ink-soft">Details:</span> {{ $document->description }}</p>
                    @endif
                </div>
            </div>

            <div class="panel mt-8">
                <div class="ph"><h2>Logs</h2></div>
                <div class="pb space-y-2">
                    @foreach($timeline as $log)
                        <div class="flex items-center justify-between border-b border-hairline py-2 last:border-b-0">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-green-bright"></span>
                                <span class="text-[14px] text-ink-soft">{{ $log['event'] }}</span>
                            </div>
                            <span class="text-[13px] font-bold text-green-deep">{{ $log['timestamp'] }}</span>
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
            <div class="panel mt-8" id="collab" data-document-id="{{ $document->id }}">
                <div class="ph">
                    <div>
                        <h2>Collaboration</h2>
                        <p class="sub mt-0.5">Post updates and notes. <strong>Internal</strong> notes are staff-only; <strong>Visible to citizen</strong> posts appear on the public tracking page and email the citizen.</p>
                    </div>
                </div>

                <div class="pb">
                    @if($canPost)
                        <form method="POST" action="{{ route('documents.comments.store', $document) }}" class="rounded-lg border border-hairline bg-paper p-4">
                            @csrf
                            <textarea name="body" rows="3" required maxlength="5000" placeholder="Write an update…"
                                      class="w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2 text-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20"></textarea>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-4 text-sm text-ink">
                                    <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="internal" checked class="accent-green"> Internal note</label>
                                    <label class="flex items-center gap-1.5"><input type="radio" name="visibility" value="public" class="accent-green"> Visible to citizen</label>
                                </div>
                                <button type="submit" class="cr-btn cr-btn-primary">Post</button>
                            </div>
                        </form>
                    @endif

                    <div id="collabFeed" class="mt-4 space-y-3">
                        @forelse($document->comments as $comment)
                            @include('track.partials.comment', ['comment' => $comment, 'canPost' => $canPost, 'document' => $document])
                        @empty
                            <p id="collabEmpty" class="text-sm italic text-ink-soft">No posts yet.</p>
                        @endforelse
                    </div>
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
