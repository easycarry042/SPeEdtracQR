<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Internal Request</h1>
            <span class="font-mono text-sm font-bold text-emerald-800">{{ $document->tracking_number }}</span>
            @php $stage = $document->statusEnum(); @endphp
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold
                {{ match($stage->band()) {
                    'green' => 'bg-emerald-100 text-emerald-800',
                    'brass' => 'bg-amber-100 text-amber-800',
                    'warn' => 'bg-red-100 text-red-700',
                    'hold' => 'bg-amber-100 text-amber-700',
                    default => 'bg-gray-100 text-gray-600',
                } }}">
                {{ $stage->label() }}
            </span>
        </div>
    </x-slot>

    <div class="page-shell page-shell-loose">

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

            {{-- Left: request details + chain --}}
            <div class="space-y-6 lg:col-span-3">
                <div class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-md">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-emerald-900">Request</h2>
                    <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">What is requested</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-gray-800">{{ $document->purpose }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Route</dt>
                            <dd class="mt-0.5 text-sm text-gray-800">{{ $document->document_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Amount</dt>
                            <dd class="mt-0.5 text-sm text-gray-800">
                                {{ $document->amount !== null ? '₱'.number_format((float) $document->amount, 2) : 'No budget involved' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Filed by</dt>
                            <dd class="mt-0.5 text-sm text-gray-800">
                                {{ $document->creator?->name ?? '—' }} · {{ $document->requestingDepartment?->name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Filed on</dt>
                            <dd class="mt-0.5 text-sm text-gray-800">{{ $document->created_at->format('M j, Y g:i A') }}</dd>
                        </div>
                        @if($document->description)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Details</dt>
                                <dd class="mt-0.5 whitespace-pre-line text-sm text-gray-700">{{ $document->description }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($document->attachments->isNotEmpty())
                        <div class="mt-5 border-t border-gray-100 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Files</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($document->attachments as $attachment)
                                    <a href="{{ $attachment->authorizedUrl() }}" target="_blank"
                                       class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-100">
                                        {{ str_ends_with($attachment->file_path, '-qr-stamped.png') ? 'QR-stamped copy' : 'Paper scan' }}
                                    </a>
                                @endforeach
                                <a href="{{ route('documents.sticker', $document) }}" target="_blank"
                                   class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                    Print QR sticker
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-md">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-emerald-900">Endorsement chain</h2>
                    <div class="mt-5">
                        @include('requests.partials.chain')
                    </div>
                </div>
            </div>

            {{-- Right: action panel --}}
            <div class="lg:col-span-2">
                @if($canAct && $currentStep)
                    <div class="sticky top-24 rounded-2xl border border-amber-200 bg-white shadow-md"
                         x-data="{ mode: 'approve' }">
                        <div class="rounded-t-2xl border-b border-amber-100 bg-amber-50/60 px-6 py-4">
                            <h2 class="text-base font-semibold text-amber-900">Your office holds this request</h2>
                            <p class="mt-0.5 text-sm text-amber-800/80">{{ $currentStep->department?->name }} — {{ $currentStep->action }}</p>
                        </div>

                        <div class="p-6">
                            @if($errors->any())
                                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                                    <ul class="list-inside list-disc space-y-0.5">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @unless(auth()->user()->signature_path)
                                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    You have no registered e-signature yet, so you cannot approve.
                                    <a href="{{ route('profile.edit') }}" class="font-semibold underline">Register it on your Profile</a> first.
                                </div>
                            @endunless

                            <div class="flex gap-2">
                                <button type="button" @click="mode = 'approve'"
                                        :class="mode === 'approve' ? 'bg-emerald-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">Approve</button>
                                <button type="button" @click="mode = 'return'"
                                        :class="mode === 'return' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">Return</button>
                                <button type="button" @click="mode = 'deny'"
                                        :class="mode === 'deny' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">Deny</button>
                            </div>

                            <form method="POST"
                                  :action="mode === 'approve' ? '{{ route('requests.steps.approve', $document) }}'
                                          : (mode === 'return' ? '{{ route('requests.steps.return', $document) }}'
                                          : '{{ route('requests.steps.deny', $document) }}')"
                                  class="mt-5 space-y-4">
                                @csrf

                                <p class="text-sm text-gray-600">
                                    <span x-show="mode === 'approve'">Approving affixes your registered e-signature and passes the request to the next office.</span>
                                    <span x-show="mode === 'return'" x-cloak>Returning sends the request back to {{ $document->requestingDepartment?->name }} for revision.</span>
                                    <span x-show="mode === 'deny'" x-cloak>Denying ends this request permanently. The filing office is notified.</span>
                                </p>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">
                                        Remarks <span x-show="mode !== 'approve'" class="text-red-500">*</span>
                                    </label>
                                    <textarea name="remarks" rows="3" maxlength="500"
                                              :required="mode !== 'approve'"
                                              :placeholder="mode === 'approve' ? 'Optional note for the record' : 'Explain the decision so the filing office knows what to fix'"
                                              class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30">{{ old('remarks') }}</textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700">Confirm your password <span class="text-red-500">*</span></label>
                                    <input type="password" name="password" required autocomplete="current-password"
                                           class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                    <p class="mt-1 text-xs text-gray-500">Identity is re-confirmed on every decision — this is what makes the e-signature credible.</p>
                                </div>

                                <button type="submit"
                                        :class="mode === 'approve' ? 'bg-emerald-700 hover:bg-emerald-800' : (mode === 'return' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-red-600 hover:bg-red-700')"
                                        class="w-full rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition">
                                    <span x-show="mode === 'approve'">Approve &amp; sign</span>
                                    <span x-show="mode === 'return'" x-cloak>Return for revision</span>
                                    <span x-show="mode === 'deny'" x-cloak>Deny request</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-gray-200/90 bg-white p-6 shadow-md">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-emerald-900">Status</h2>
                        <p class="mt-3 text-sm text-gray-600">
                            @if($currentStep)
                                Awaiting <span class="font-semibold text-gray-800">{{ $currentStep->department?->name }}</span> — {{ $currentStep->action }}.
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
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
