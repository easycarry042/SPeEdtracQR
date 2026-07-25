{{-- ── Physical custody: where is the paper folder right now?
     Taking custody requires a live scan of THIS folder's QR, verified
     server-side; manual recording is an audited fallback. Reused by the
     public tracking page and the internal-request action panel. ── --}}
@php
    $custody = $custody ?? $document->currentCustody();
    $canTakeCustody = ! $custody || (int) $custody->user_id !== (int) auth()->id();
@endphp
<div class="custody-box" x-data="custodyScan(@js(strtoupper($document->tracking_number)))">
    <div class="custody-strip">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h5l2 3h11v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/></svg>
        @if($custody)
            <span>Folder with <b>{{ $custody->user->name ?? 'staff' }}</b> <span class="mono">since {{ $custody->created_at->format('M d, h:i A') }}</span>{{ $custody->capture_method === 'manual' ? ' · recorded manually' : '' }}</span>
        @else
            <span>No physical custody recorded yet.</span>
        @endif
        @if($canTakeCustody)
            <button type="button" class="cr-btn cr-btn-sm" style="margin-left:auto;"
                    @click="toggleScan()" x-text="scanning ? 'Cancel scan' : 'I have this folder'"></button>
        @endif
    </div>

    @if($canTakeCustody)
        <div x-show="scanning" x-cloak class="custody-scan">
            <p class="cs-hint">Point the camera at <b>this folder's QR</b> to confirm you physically hold it.</p>
            <div id="custodyReader"></div>
        </div>

        <p x-show="error" x-cloak x-text="error" class="cs-error" role="alert"></p>
        @if($errors->hasAny(['custody', 'scanned_value', 'override_reason', 'capture_method']))
            <p class="cs-error" role="alert">{{ collect(['custody', 'scanned_value', 'override_reason', 'capture_method'])->map(fn ($f) => $errors->first($f))->filter()->first() }}</p>
        @endif

        {{-- Auto-submitted when the scanned QR matches this document. --}}
        <form x-ref="scanForm" method="POST" action="{{ route('documents.custody.store', $document) }}" class="hidden">
            @csrf
            <input type="hidden" name="capture_method" value="scan">
            <input type="hidden" name="scanned_value" x-ref="scannedValue">
        </form>

        {{-- Audited fallback for a torn/unreadable QR — visibly secondary. --}}
        <div class="custody-manual">
            <button type="button" class="cs-manual-link" @click="openManual()">QR torn or unreadable? Record manually</button>
            <form x-show="manualOpen" x-cloak method="POST" action="{{ route('documents.custody.store', $document) }}" class="cs-manual-form">
                @csrf
                <input type="hidden" name="capture_method" value="manual">
                <label>Why can't the QR be scanned? (required)
                    <textarea name="override_reason" rows="2" maxlength="500" required
                              placeholder="e.g. QR sticker torn off the folder"></textarea>
                </label>
                <button type="submit" class="cr-btn cr-btn-sm">Record custody manually</button>
            </form>
        </div>
    @endif
</div>

@if($canTakeCustody)
    @include('partials.qr-scan-helpers')
    <script>
        function custodyScan(expected) {
            return {
                scanning: false,
                manualOpen: false,
                error: '',
                submitted: false,

                toggleScan() {
                    this.error = '';
                    this.manualOpen = false;
                    if (this.scanning) {
                        window.SpeedQr.stop();
                        this.scanning = false;
                        return;
                    }
                    this.scanning = true;
                    window.SpeedQr.start('custodyReader', (decodedText) => {
                        if (this.submitted) return;
                        const tracking = window.SpeedQr.extractTracking(decodedText);
                        window.SpeedQr.stop();
                        this.scanning = false;
                        if (tracking === expected) {
                            // Match: submit with the raw decoded value for server-side verification.
                            this.submitted = true;
                            this.$refs.scannedValue.value = decodedText;
                            this.$refs.scanForm.submit();
                        } else {
                            this.error = tracking
                                ? `That's a different folder (${tracking}). Scan this document's folder.`
                                : 'That QR does not contain a tracking number.';
                        }
                    }, () => {
                        this.scanning = false;
                        this.error = 'Could not start the camera. Check permissions, or record manually below.';
                    });
                },

                openManual() {
                    if (this.scanning) this.toggleScan();
                    this.error = '';
                    this.manualOpen = ! this.manualOpen;
                },
            };
        }
    </script>
@endif
