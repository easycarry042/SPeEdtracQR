<x-citizen-layout>
    <x-slot name="title">Verify {{ $trackingNumber }}</x-slot>

    <div class="mx-auto max-w-lg">
        <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-ink-soft">Document authenticity check</p>

        @if($verdict === 'genuine')
            <div class="panel">
                <div class="verify-head verify-genuine">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5 9-10"/></svg>
                    <div>
                        <p class="verify-verdict">Genuine record</p>
                        <p class="verify-sub">This document is on official record at the San Pedro records office.</p>
                    </div>
                </div>
                <div class="pb">
                    <dl class="verify-rows">
                        <div><dt>Tracking number</dt><dd><span class="id-chip">{{ $document->tracking_number }}</span></dd></div>
                        <div><dt>Document type</dt><dd>{{ $document->document_type }}</dd></div>
                        <div><dt>Current status</dt><dd>{{ $document->statusEnum()->label() }}</dd></div>
                        @if($isIssued && $document->completed_at)
                            <div><dt>Issued</dt><dd><span class="id-chip">{{ $document->completed_at->format('M d, Y') }}</span></dd></div>
                        @endif
                        @if($document->claimed_at)
                            <div><dt>Released to citizen</dt><dd><span class="id-chip">{{ $document->claimed_at->format('M d, Y') }}</span></dd></div>
                        @endif
                    </dl>
                    @unless($isIssued)
                        <p class="mt-4 text-sm text-ink-soft">Note: this record exists but has <b>not been issued yet</b> — it is still being processed.</p>
                    @endunless
                </div>
            </div>
        @elseif($verdict === 'invalid')
            <div class="panel">
                <div class="verify-head verify-invalid">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                    <div>
                        <p class="verify-verdict">Verification failed</p>
                        <p class="verify-sub">The security signature on this code is missing or does not match the record.</p>
                    </div>
                </div>
                <div class="pb">
                    <p class="text-sm text-ink">A document presenting this code may be <b>altered or counterfeit</b>. Please contact the San Pedro records office to confirm before accepting it.</p>
                    <p class="mt-3 text-xs text-ink-soft">Reference presented: <span class="id-chip">{{ $trackingNumber }}</span></p>
                </div>
            </div>
        @else
            <div class="panel">
                <div class="verify-head verify-unknown">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5m0 3h.01"/></svg>
                    <div>
                        <p class="verify-verdict">Not on record</p>
                        <p class="verify-sub">No document with this tracking number exists in the system.</p>
                    </div>
                </div>
                <div class="pb">
                    <p class="text-sm text-ink">Check the number for typing mistakes. If it was scanned from a printed document, that document is <b>not from this office</b>.</p>
                    <p class="mt-3 text-xs text-ink-soft">Reference presented: <span class="id-chip">{{ $trackingNumber }}</span></p>
                </div>
            </div>
        @endif

        <p class="mt-4 text-xs text-ink-soft">
            This check is provided by SPeED TraQR, the document tracking system of the San Pedro records office.
            Verification links are cryptographically signed — a matching tracking number alone is not proof of authenticity.
        </p>

        <p class="mt-4 text-center text-sm">
            <a href="{{ url('/') }}" class="font-semibold text-green underline">Go to homepage</a>
            <span class="mx-2 text-ink-soft">·</span>
            <a href="{{ route('citizen.track') }}" class="font-semibold text-green underline">Track a document</a>
        </p>
    </div>
</x-citizen-layout>
