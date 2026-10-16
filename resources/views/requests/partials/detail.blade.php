{{-- An internal request's detail: the request card and, when this office
     holds it, the decision card. Shown on its own page (requests/show) and in a
     modal on the Internal page (requests/detail-modal, $inModal = true).

     In the modal the close button dispatches `close-internal-request`, and the
     forms carry from=internal so the server brings the user back to the
     Internal page with this request reopened. --}}
@php $inModal = $inModal ?? false; @endphp
    @php
        $stage = $document->statusEnum();
        $hasActionPanel = $canAct && $currentStep;
        // The QR tile (partials/qr-tile) reads these off the record.
        $qrRecord = [
            'tracking_number' => $document->tracking_number,
            'qr_url' => \App\Support\PublicStorage::url($document->qr_code_path),
            'slip_url' => route('documents.sticker', $document),
        ];
    @endphp

    {{-- Same card as the Look Up details modal (.dm-*): status and back in the
         header bar, the request and its QR, then the chain, the scan trail and
         the details. The office's decision sits in a card beside it. --}}
    <div @class(['page-shell page-shell-loose' => ! $inModal, 'flex flex-col gap-4' => $inModal])>

        @if(session('status'))
            <div class="dm-callout dm-callout-green" role="status">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <div @class(['grid grid-cols-1 items-start gap-5', 'lg:grid-cols-[minmax(0,1fr)_420px]' => $hasActionPanel])>

            {{-- ── The request ─────────────────────────────────────────── --}}
            <section class="dm-card dm-page-card @if($inModal) dm-in-modal @endif @if(! $hasActionPanel) mx-auto w-full max-w-[760px] @endif"
                     x-data="{ qrOpen: false, rec: @js($qrRecord) }" @keydown.escape.window="qrOpen = false"
                     aria-label="Internal request {{ $document->tracking_number }}">
                <div class="dm-head">
                    <span class="pill {{ match($document->internalStatusBand()) {
                            'green' => 'p-green',
                            'red' => 'p-red',
                            'returned' => 'p-orange',
                            default => 'p-amber',
                        } }}">{{ $document->internalStatusLabel() }}</span>
                    @if($inModal)
                        <button type="button" @click="$dispatch('close-internal-request')" class="dm-close" aria-label="Close">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    @else
                        <a href="{{ route('requests.index') }}" data-soft-nav-link class="dm-close" aria-label="Back to Internal" title="Back to Internal">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </a>
                    @endif
                </div>

                <div class="dm-body">
                    {{-- Title + tracking number --}}
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="dm-title">{{ $document->purpose }}</p>
                            <p class="dm-subtitle">Internal Request · {{ $document->requestingDepartment?->name }}</p>
                        </div>
                        @include('partials.qr-tile', ['record' => 'rec'])
                    </div>

                    {{-- Where it stands, when this office has nothing to do. --}}
                    @unless($hasActionPanel)
                        <p class="dm-callout dm-callout-muted mt-5">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7.5V12l3 2"/></svg>
                            <span>
                                @if($currentStep)
                                    Awaiting <span class="font-semibold text-ink">{{ $currentStep->department?->name }}</span> — {{ $currentStep->action }}.
                                    @if($currentStep->started_at)
                                        There since {{ $currentStep->started_at->diffForHumans() }}.
                                    @endif
                                @elseif($stage->isTerminal() || $stage === \App\Enums\DocumentStatus::Returned)
                                    This request is {{ strtolower($stage->label()) }}. No office action is pending.
                                @else
                                    No hop is currently open on this request.
                                @endif
                            </span>
                        </p>
                    @endunless

                    <section class="mt-7">
                        <h2 class="dm-section">Endorsement Chain</h2>
                        <div class="mt-3">
                            @include('requests.partials.chain')
                        </div>
                    </section>

                    {{-- Who scanned this folder: every custody hand-off, newest first. --}}
                    <section class="mt-7">
                        <h2 class="dm-section">Scan Trail</h2>
                        <ul class="dm-logs">
                            @forelse($custodyTrail as $event)
                                <li class="dm-log items-start">
                                    <span class="dm-log-dot mt-1.5" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="dm-log-event block">{{ $event->user->name ?? 'Unknown staff' }}</span>
                                        <span class="block text-[length:var(--fs-caption)] text-ink-soft">
                                            {{ $event->user?->department?->name ?? 'No department' }} ·
                                            {{ $event->capture_method === 'manual' ? 'recorded manually' : 'QR scanned' }}
                                            @if($event->capture_method === 'manual' && $event->override_reason)
                                                — {{ $event->override_reason }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="dm-log-time">{{ $event->created_at->format('M d, h:i A') }}</span>
                                </li>
                            @empty
                                <li class="text-[length:var(--fs-small)] text-ink-soft">Nobody has scanned this folder yet.</li>
                            @endforelse
                        </ul>
                    </section>

                    <section class="mt-7">
                        <h2 class="dm-section">More Info</h2>
                        <dl class="dm-info">
                            <div class="dm-info-row"><dt>What is requested</dt><dd>{{ $document->purpose }}</dd></div>
                            <div class="dm-info-row"><dt>Route</dt><dd>{{ $document->document_type }}</dd></div>
                            <div class="dm-info-row"><dt>Filed by</dt><dd>{{ $document->creator?->name ?? '—' }} · {{ $document->requestingDepartment?->name }}</dd></div>
                            <div class="dm-info-row"><dt>Filed on</dt><dd>{{ $document->created_at->format('M j, Y g:i A') }}</dd></div>
                        </dl>
                        @if($document->description)
                            <div class="dm-note">
                                <p class="dm-note-label">Details</p>
                                <p class="whitespace-pre-line">{{ $document->description }}</p>
                            </div>
                        @endif
                    </section>

                    @if($document->attachments->isNotEmpty())
                        <section class="mt-7">
                            <h2 class="dm-section">Files</h2>
                            <ul class="dm-files">
                                @foreach($document->attachments as $attachment)
                                    @php $stamped = str_contains($attachment->file_path, '-qr-stamped.'); @endphp
                                    <li class="dm-file">
                                        <span class="dm-thumb" aria-hidden="true">
                                            <span class="dm-thumb-doc">
                                                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linejoin="round" d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"/><path stroke-linejoin="round" d="M14 3.5V8h4.5"/></svg>
                                            </span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="dm-file-name">{{ $stamped ? 'QR-stamped copy' : 'Uploaded document' }}</p>
                                            <p class="dm-file-meta">{{ strtoupper(pathinfo($attachment->file_path, PATHINFO_EXTENSION)) }}</p>
                                        </div>
                                        <a href="{{ $attachment->authorizedUrl() }}" target="_blank" class="dm-file-open">View</a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            </section>

            {{-- ── This office's decision ──────────────────────────────── --}}
            @if($canAct && $currentStep && ! $hasCustody)
                {{-- QR is load-bearing: the endorsement stays locked until this
                     office scans the folder to prove the paper is in hand. --}}
                <section class="dm-card dm-page-card @if($inModal) dm-in-modal @endif lg:sticky lg:top-24">
                    <div class="dm-head">
                        <div class="min-w-0">
                            <h2 class="dm-section">Scan to take custody</h2>
                            <p class="text-[length:var(--fs-caption)] text-ink-soft">{{ $currentStep->action }}</p>
                        </div>
                    </div>
                    <div class="dm-body space-y-4">
                        <p class="text-[length:var(--fs-small)] text-ink-soft">
                            Your office holds this hop, but you must <b class="text-ink">scan the document's QR Code</b> to
                            confirm the paper is physically here before you can approve, return, or deny it.
                        </p>
                        @include('partials.custody-scan', ['document' => $document])
                    </div>
                </section>
            @elseif($canAct && $currentStep)
                @php $hasSignature = (bool) auth()->user()->signature_path; @endphp
                <section class="dm-card dm-page-card dm-decision @if($inModal) dm-in-modal @endif lg:sticky lg:top-24"
                         x-data="{ mode: 'approve', denyAck: false, hasSignature: {{ $hasSignature ? 'true' : 'false' }} }">
                    <div class="dm-head">
                        <div class="min-w-0">
                            <h2 class="dm-section">Your office holds this request</h2>
                            <p class="text-[length:var(--fs-caption)] text-ink-soft">{{ $currentStep->action }}</p>
                        </div>
                    </div>

                    <div class="dm-body">
                            @if($errors->any())
                                <div class="mb-4 rounded-[8px] border border-status-red-wash bg-status-red-wash px-4 py-3 text-[13px] font-medium text-status-red">
                                    <ul class="list-inside list-disc space-y-0.5">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php $custody = $document->currentCustody(); @endphp
                            @if($custody)
                                <div class="mb-4 flex items-center gap-2 rounded-[8px] border border-green/25 bg-green/5 px-4 py-2.5 text-[13px] text-ink" role="status">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true" class="text-green"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                    <span>Document in hand — held by <b>{{ $custody->user->name ?? 'your office' }}</b>{{ $custody->capture_method === 'manual' ? ' (recorded manually)' : ' (QR scanned)' }}.</span>
                                </div>
                            @endif

                            @unless($hasSignature)
                                <div class="mb-4 rounded-[8px] border border-status-amber-wash bg-status-amber-wash px-4 py-3 text-[13px] text-status-amber" role="alert">
                                    You have no registered e-signature yet, so you cannot approve.
                                    <a href="{{ route('profile.edit') }}" class="font-semibold underline">Register it on your Profile</a> first — you can still return or deny.
                                </div>
                            @endunless
                            <div class="st-tabs dm-decision-tabs w-full" role="tablist" aria-label="Decision">
                                <button type="button" role="tab" :aria-selected="mode === 'approve'" @click="mode = 'approve'"
                                        :class="mode === 'approve' ? 'on' : ''" class="flex-1 justify-center">Approve</button>
                                {{-- Done is the only action that completes a request:
                                     approving just passes the paper along, and the
                                     chain grows as each office scans it. --}}
                                <button type="button" role="tab" :aria-selected="mode === 'done'" @click="mode = 'done'"
                                        :class="mode === 'done' ? 'on' : ''" class="flex-1 justify-center">Done</button>
                                <button type="button" role="tab" :aria-selected="mode === 'return'" @click="mode = 'return'"
                                        :class="mode === 'return' ? 'on' : ''" class="flex-1 justify-center">Return</button>
                                <button type="button" role="tab" :aria-selected="mode === 'deny'" @click="mode = 'deny'; denyAck = false"
                                        :class="mode === 'deny' ? 'on' : ''" class="flex-1 justify-center">Deny</button>
                            </div>

                            <form method="POST"
                                  :action="mode === 'approve' ? '{{ route('requests.steps.approve', $document) }}'
                                          : (mode === 'done' ? '{{ route('requests.steps.complete', $document) }}'
                                          : (mode === 'return' ? '{{ route('requests.steps.return', $document) }}'
                                          : '{{ route('requests.steps.deny', $document) }}'))"
                                  class="mt-5 space-y-4">
                                @csrf
                                @if($inModal)<input type="hidden" name="from" value="internal">@endif

                                <p class="text-[13px] text-ink-soft">
                                    <span x-show="mode === 'approve'">Approving affixes your registered e-signature and passes the request to the office you choose below.</span>
                                    <span x-show="mode === 'done'" x-cloak>Marking this done completes the request — use it when your office is the last to handle the paper.</span>
                                    <span x-show="mode === 'return'" x-cloak>Returning sends the request back to {{ $document->requestingDepartment?->name }} for revision.</span>
                                    <span x-show="mode === 'deny'" x-cloak>Denying ends this request permanently. The filing office is notified.</span>
                                </p>

                                {{-- Where the paper goes next. A hop queued when the
                                     request was filed already answers this; from
                                     there on, the office holding it decides. --}}
                                <div x-show="mode === 'approve'" x-cloak>
                                    @if($queuedNextStep)
                                        <p class="rounded-[8px] border border-hairline bg-[#f6f8f7] px-3 py-2.5 text-[13px] text-ink">
                                            Next office (set when this request was filed):
                                            <span class="font-semibold">{{ $queuedNextStep->department?->name }}</span>
                                            <span class="font-mono text-[11px] text-ink-soft">{{ $queuedNextStep->department?->code }}</span>
                                        </p>
                                    @else
                                        <label for="next-department" class="block text-[13px] font-semibold text-ink">
                                            Send to which office next? <span class="text-status-red">*</span>
                                        </label>
                                        <select id="next-department" name="next_department_id"
                                                :required="mode === 'approve'"
                                                class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('next_department_id') border-status-red @enderror">
                                            <option value="">Select a department…</option>
                                            @foreach($forwardDepartments as $forwardDepartment)
                                                <option value="{{ $forwardDepartment->id }}" @selected(old('next_department_id') == $forwardDepartment->id)>
                                                    {{ $forwardDepartment->name }} ({{ $forwardDepartment->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-[12px] text-ink-soft">If your office is the last to handle this, use <strong>Done</strong> instead.</p>
                                        @error('next_department_id')<p class="mt-1 text-[12px] font-semibold text-status-red">{{ $message }}</p>@enderror
                                    @endif
                                </div>

                                <div>
                                    <label for="req-remarks" class="block text-[13px] font-semibold text-ink">
                                        Remarks <span x-show="mode === 'return' || mode === 'deny'" class="text-status-red">*</span>
                                    </label>
                                    <textarea id="req-remarks" name="remarks" rows="3" maxlength="500"
                                              :required="mode === 'return' || mode === 'deny'"
                                              :placeholder="(mode === 'approve' || mode === 'done') ? 'Optional note for the record' : 'Explain the decision so the filing office knows what to fix'"
                                              class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink shadow-none transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">{{ old('remarks') }}</textarea>
                                </div>

                                {{-- Permanent-action guard: Deny must be explicitly acknowledged. --}}
                                <label x-show="mode === 'deny'" x-cloak class="flex items-start gap-2 rounded-[8px] border border-status-red-wash bg-status-red-wash px-3 py-2.5 text-[13px] text-status-red">
                                    <input type="checkbox" x-model="denyAck" class="mt-0.5 h-4 w-4 rounded border-hairline-strong text-status-red focus:ring-status-red/30">
                                    <span>I understand denying <strong>permanently ends</strong> this request and cannot be undone.</span>
                                </label>

                                {{-- The decision is confirmed against the folder itself:
                                     scan this request's QR sticker. A code from another
                                     request — or any foreign QR — is refused. --}}
                                <div x-data="folderSign(@js($document->tracking_number))">
                                    <p class="block text-[13px] font-semibold text-ink">
                                        Scan this request's QR <span class="text-status-red">*</span>
                                    </p>

                                    <input type="hidden" name="document_scan" :value="payload">

                                    <div x-show="!payload" class="mt-2">
                                        <button type="button" @click="toggle()" class="cr-btn cr-btn-sm">
                                            <span x-text="scanning ? 'Cancel scan' : 'Scan request QR'"></span>
                                        </button>
                                        <div x-show="scanning" x-cloak class="mt-3">
                                            <div id="folderReader" class="min-h-[200px] max-w-[320px] overflow-hidden rounded-[8px]"></div>
                                            <p class="mt-1 text-[12px] text-ink-soft">Point the camera at the QR sticker on this folder.</p>
                                        </div>
                                    </div>

                                    <p x-show="payload" x-cloak class="mt-2 flex items-center gap-2 text-[13px] font-semibold text-green">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                                        Request QR scanned — ready to sign
                                        <button type="button" @click="reset()" class="ml-1 text-[12px] font-normal text-ink-soft underline">rescan</button>
                                    </p>

                                    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-2 text-[12.5px] font-semibold text-status-red"></p>
                                    @error('document_scan')
                                        <p class="mt-2 text-[12.5px] font-semibold text-status-red">{{ $message }}</p>
                                    @enderror

                                    {{-- Audited fallback, mirroring the custody widget. Kept
                                         visibly secondary so scanning stays the norm, but it
                                         must exist: with no fallback a machine without a
                                         working camera cannot action internal requests at
                                         all, which halts the office. Revealed once the user
                                         has tried to scan (or the camera has failed) — which
                                         is exactly when it is needed. --}}
                                    <div x-show="!payload && (scanning || error)" x-cloak class="mt-2">
                                        <button type="button"
                                                @click="manualOpen = !manualOpen"
                                                class="text-[12px] font-semibold text-ink-soft underline">
                                            QR torn, or no camera on this machine? Confirm manually
                                        </button>

                                        <div x-show="manualOpen" x-cloak class="mt-2">
                                            <label for="scan_override_reason" class="block text-[12px] font-semibold text-ink">
                                                Why can't you scan? <span class="text-status-red">*</span>
                                            </label>
                                            <textarea id="scan_override_reason"
                                                      name="scan_override_reason"
                                                      rows="2"
                                                      minlength="10"
                                                      maxlength="500"
                                                      placeholder="e.g. Sticker torn on the folder cover; verified tracking number by hand."
                                                      class="mt-1 w-full rounded-[8px] border border-hairline bg-paper px-2.5 py-1.5 text-[13px]">{{ old('scan_override_reason') }}</textarea>
                                            <p class="mt-1 text-[12px] text-ink-soft">
                                                This is recorded against the request and shown in its history.
                                            </p>
                                            @error('scan_override_reason')
                                                <p class="mt-1 text-[12.5px] font-semibold text-status-red">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <p class="mt-1 text-[12px] text-ink-soft">
                                        Sticker missing? <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="font-semibold underline">Print the QR</a>.
                                    </p>
                                </div>

                                <button type="submit"
                                        :disabled="(mode === 'approve' && !hasSignature) || (mode === 'deny' && !denyAck)"
                                        :class="{
                                            'cr-btn-primary': mode === 'approve' || mode === 'done',
                                            'cr-btn-danger': mode === 'deny',
                                        }"
                                        class="cr-btn w-full justify-center py-2.5 text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-40">
                                    <span x-show="mode === 'approve'">Approve &amp; sign</span>
                                    <span x-show="mode === 'done'" x-cloak>Mark as done — complete the request</span>
                                    <span x-show="mode === 'return'" x-cloak>Return for revision</span>
                                    <span x-show="mode === 'deny'" x-cloak>Deny request</span>
                                </button>
                            </form>
                    </div>
                </section>
            @endif
        </div>
    </div>

    {{-- Folder-QR scanning for the signature step (see the decision panel above). --}}
    @include('partials.qr-scan-helpers')
    <script>
        window.folderSign = function (trackingNumber) {
            return {
                scanning: false,
                payload: '',
                error: '',
                // Whether the "confirm without scanning" panel is expanded. The
                // reason textarea is only submitted when the supervisor has
                // deliberately opened it.
                manualOpen: false,
                tracking: String(trackingNumber || '').toUpperCase(),

                toggle() {
                    if (this.scanning) {
                        window.SpeedQr.stop();
                        this.scanning = false;

                        return;
                    }
                    this.error = '';
                    this.scanning = true;
                    window.SpeedQr.start('folderReader', (decodedText) => {
                        // The same rules the server applies: a code this system
                        // issued, and this request's — not the folder next to it.
                        const scanned = window.SpeedQr.extractTracking(decodedText);

                        if (!scanned) {
                            this.error = window.SpeedQr.FOREIGN_CODE_MESSAGE;

                            return;
                        }

                        if (scanned !== this.tracking) {
                            this.error = `That QR is for ${scanned}. Scan the sticker on this request's folder.`;

                            return;
                        }

                        window.SpeedQr.stop();
                        this.scanning = false;
                        this.payload = scanned;
                        this.error = '';
                    }, (cameraError) => {
                        this.scanning = false;
                        this.error = window.SpeedQr.describe(cameraError);
                    });
                },

                reset() {
                    this.payload = '';
                    this.error = '';
                },
            };
        };
    </script>
