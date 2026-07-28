<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-green-deep">Internal Request</h1>
            <span class="id-chip">{{ $document->tracking_number }}</span>
            @php $stage = $document->statusEnum(); @endphp
            <span class="pill {{ match($document->internalStatusBand()) {
                    'green' => 'p-green',
                    'red' => 'p-red',
                    'returned' => 'p-orange',
                    default => 'p-amber',
                } }}">{{ $document->internalStatusLabel() }}</span>
        </div>
    </x-slot>

    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="panel">
                <div class="pb flex items-center gap-2 text-[13px] font-medium text-green-deep">
                    <svg class="h-4 w-4 text-green" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('status') }}
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

            {{-- Left: request details + chain --}}
            <div class="space-y-6 lg:col-span-3">
                <section class="panel">
                    <div class="ph"><h2>Request</h2></div>
                    <div class="pb">
                        <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">What is requested</dt>
                                <dd class="mt-0.5 text-[14px] font-semibold text-ink">{{ $document->purpose }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Route</dt>
                                <dd class="mt-0.5 text-[14px] text-ink">{{ $document->document_type }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Filed by</dt>
                                <dd class="mt-0.5 text-[14px] text-ink">
                                    {{ $document->creator?->name ?? '—' }} · {{ $document->requestingDepartment?->name }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Filed on</dt>
                                <dd class="mt-0.5 text-[14px] text-ink">{{ $document->created_at->format('M j, Y g:i A') }}</dd>
                            </div>
                            @if($document->description)
                                <div class="sm:col-span-2">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Details</dt>
                                    <dd class="mt-0.5 whitespace-pre-line text-[14px] text-ink">{{ $document->description }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if($document->attachments->isNotEmpty())
                            <div class="mt-5 border-t border-hairline pt-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Files</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($document->attachments as $attachment)
                                        <a href="{{ $attachment->authorizedUrl() }}" target="_blank" class="cr-btn cr-btn-sm">
                                            {{ str_ends_with($attachment->file_path, '-qr-stamped.png') ? 'QR-stamped copy' : 'Paper scan' }}
                                        </a>
                                    @endforeach
                                    <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="cr-btn cr-btn-sm">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                                        Print QR sticker
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="panel">
                    <div class="ph"><h2>Endorsement chain</h2></div>
                    <div class="pb">
                        @include('requests.partials.chain')
                    </div>
                </section>
            </div>

            {{-- Right: action panel --}}
            <div class="lg:col-span-2">
                @if($canAct && $currentStep && ! $hasCustody)
                    {{-- QR is load-bearing: the endorsement stays locked until this
                         office scans the folder to prove the paper is in hand. --}}
                    <div class="panel sticky top-24">
                        <div class="ph">
                            <h2>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m0 14v1m8-8h-1M5 12H4m1.6-6.4.7.7m11.4-.7-.7.7M3 7h5l2 3h11v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/></svg>
                                Scan to take custody
                            </h2>
                            <span class="sub">{{ $currentStep->action }}</span>
                        </div>
                        <div class="pb space-y-4">
                            <p class="text-[13px] text-ink-soft">
                                Your office holds this hop, but you must <b class="text-ink">scan the folder's QR</b> to
                                confirm the paper is physically here before you can approve, return, or deny it.
                            </p>
                            @include('partials.custody-scan', ['document' => $document])
                        </div>
                    </div>
                @elseif($canAct && $currentStep)
                    @php $hasSignature = (bool) auth()->user()->signature_path; @endphp
                    <div class="panel sticky top-24" x-data="{ mode: 'approve', denyAck: false, hasSignature: {{ $hasSignature ? 'true' : 'false' }} }">
                        <div class="ph">
                            <h2>
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Your office holds this request
                            </h2>
                            <span class="sub">{{ $currentStep->action }}</span>
                        </div>

                        <div class="pb">
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
                                    <span>Folder in hand — held by <b>{{ $custody->user->name ?? 'your office' }}</b>{{ $custody->capture_method === 'manual' ? ' (recorded manually)' : ' (QR scanned)' }}.</span>
                                </div>
                            @endif

                            @unless($hasSignature)
                                <div class="mb-4 rounded-[8px] border border-status-amber-wash bg-status-amber-wash px-4 py-3 text-[13px] text-status-amber" role="alert">
                                    You have no registered e-signature yet, so you cannot approve.
                                    <a href="{{ route('profile.edit') }}" class="font-semibold underline">Register it on your Profile</a> first — you can still return or deny.
                                </div>
                            @endunless

                            <div class="segchips w-full" role="tablist" aria-label="Decision">
                                <button type="button" role="tab" :aria-selected="mode === 'approve'" @click="mode = 'approve'"
                                        :class="mode === 'approve' ? 'on' : ''" class="flex-1 justify-center">Approve</button>
                                <button type="button" role="tab" :aria-selected="mode === 'return'" @click="mode = 'return'"
                                        :class="mode === 'return' ? 'on' : ''" class="flex-1 justify-center">Return</button>
                                <button type="button" role="tab" :aria-selected="mode === 'deny'" @click="mode = 'deny'; denyAck = false"
                                        :class="mode === 'deny' ? 'on' : ''" class="flex-1 justify-center">Deny</button>
                            </div>

                            <form method="POST"
                                  :action="mode === 'approve' ? '{{ route('requests.steps.approve', $document) }}'
                                          : (mode === 'return' ? '{{ route('requests.steps.return', $document) }}'
                                          : '{{ route('requests.steps.deny', $document) }}')"
                                  class="mt-5 space-y-4">
                                @csrf

                                <p class="text-[13px] text-ink-soft">
                                    <span x-show="mode === 'approve'">Approving affixes your registered e-signature and passes the request to the next office.</span>
                                    <span x-show="mode === 'return'" x-cloak>Returning sends the request back to {{ $document->requestingDepartment?->name }} for revision.</span>
                                    <span x-show="mode === 'deny'" x-cloak>Denying ends this request permanently. The filing office is notified.</span>
                                </p>

                                <div>
                                    <label for="req-remarks" class="block text-[13px] font-semibold text-ink">
                                        Remarks <span x-show="mode !== 'approve'" class="text-status-red">*</span>
                                    </label>
                                    <textarea id="req-remarks" name="remarks" rows="3" maxlength="500"
                                              :required="mode !== 'approve'"
                                              :placeholder="mode === 'approve' ? 'Optional note for the record' : 'Explain the decision so the filing office knows what to fix'"
                                              class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink shadow-none transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">{{ old('remarks') }}</textarea>
                                </div>

                                {{-- Permanent-action guard: Deny must be explicitly acknowledged. --}}
                                <label x-show="mode === 'deny'" x-cloak class="flex items-start gap-2 rounded-[8px] border border-status-red-wash bg-status-red-wash px-3 py-2.5 text-[13px] text-status-red">
                                    <input type="checkbox" x-model="denyAck" class="mt-0.5 h-4 w-4 rounded border-hairline-strong text-status-red focus:ring-status-red/30">
                                    <span>I understand denying <strong>permanently ends</strong> this request and cannot be undone.</span>
                                </label>

                                <div>
                                    <label for="req-password" class="block text-[13px] font-semibold text-ink">Confirm your password <span class="text-status-red">*</span></label>
                                    <input id="req-password" type="password" name="password" required autocomplete="current-password"
                                           class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink shadow-none transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25">
                                    <p class="mt-1 text-[12px] text-ink-soft">Identity is re-confirmed on every decision — this is what makes the e-signature credible.</p>
                                </div>

                                <button type="submit"
                                        :disabled="(mode === 'approve' && !hasSignature) || (mode === 'deny' && !denyAck)"
                                        :class="{
                                            'cr-btn-primary': mode === 'approve',
                                            'cr-btn-danger': mode === 'deny',
                                        }"
                                        class="cr-btn w-full justify-center py-2.5 text-[13px] font-semibold disabled:cursor-not-allowed disabled:opacity-40">
                                    <span x-show="mode === 'approve'">Approve &amp; sign</span>
                                    <span x-show="mode === 'return'" x-cloak>Return for revision</span>
                                    <span x-show="mode === 'deny'" x-cloak>Deny request</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <section class="panel">
                        <div class="ph"><h2>Status</h2></div>
                        <div class="pb">
                            <p class="text-[13px] text-ink-soft">
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
                            </p>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
