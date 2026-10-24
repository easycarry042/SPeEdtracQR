{{-- ── Physical custody: where is the paper folder right now?
     Taking custody requires a live scan of THIS folder's QR, verified
     server-side; manual recording is an audited fallback. Reused by the
     public tracking page and the internal-request action panel.

     A soft-copy internal request has no folder, but it still has to be
     RECEIVED before anyone acts on it. With no paper to point a camera at,
     its QR is snipped off the screen: the page fades, a crosshair drags a box
     around the QR (window.SpeedQr.scanFromScreen) — "QR on screen" in the
     trail. The camera, or a saved picture ("QR image"), work too. ── --}}
@php
    $custody = $custody ?? $document->currentCustody();
    $softCopy = $document->isSoftCopy();
    $canTakeCustody = ! $custody || (int) $custody->user_id !== (int) auth()->id();
@endphp
<div class="custody-box" x-data="custodyScan(@js(strtoupper($document->tracking_number)))">
    <div class="custody-strip">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h5l2 3h11v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z"/></svg>
        @if($softCopy)
            <span>Soft copy — no paper folder. Receive it by scanning its QR.</span>
        @elseif($custody)
            <span>Folder with <b>{{ $custody->user->name ?? 'staff' }}</b> <span class="mono">since {{ $custody->created_at->format('M d, h:i A') }}</span>{{ $custody->capture_method === 'manual' ? ' · recorded manually' : '' }}</span>
        @else
            <span>No physical custody recorded yet.</span>
        @endif
        @if($canTakeCustody)
            <button type="button" class="cr-btn cr-btn-sm" style="margin-left:auto;"
                    @click="toggleScan()" x-text="scanning ? 'Cancel scan' : @js($softCopy ? 'Scan with camera' : 'I have this folder')"></button>
        @endif
    </div>

    @if($canTakeCustody)
        @if($softCopy)
            {{-- The snipping tool: the page fades, the pointer becomes a
                 crosshair, and the box dragged around the request's QR (open
                 beside the tracking number, or on its QR-stamped copy) is read
                 as a scan. A saved picture of the QR works too. --}}
            <div class="qr-drop">
                <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 9h2v2H9zM13 9h2v2h-2zM9 13h2v2H9zM13 13h2v2h-2z"/></svg>
                <button type="button" class="cr-btn cr-btn-primary" @click="snip('page')" :disabled="reading">
                    <span x-text="reading ? 'Reading the QR…' : 'Select the QR on screen'"></span>
                </button>
                <span class="qr-drop-hint">This request's QR opens and the page dims — drag a box around the QR.</span>
                <span class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                    {{-- The QR is open somewhere else (an email, a PDF): the
                         browser asks which window, then the box is drawn on a
                         still of it. --}}
                    <button type="button" class="qr-drop-alt" @click="snip('any')" :disabled="reading">QR open in another window?</button>
                    <span class="text-ink-soft" aria-hidden="true">·</span>
                    <label class="qr-drop-alt">
                        <input type="file" accept="image/*" class="sr-only" @change="readFile($event.target.files[0]); $event.target.value = ''">
                        Choose a saved picture
                    </label>
                </span>
            </div>
        @endif

        <div x-show="scanning" x-cloak class="custody-scan">
            <p class="cs-hint">Point the camera at <b>{{ $softCopy ? "this request's QR" : "this folder's QR" }}</b> to confirm you {{ $softCopy ? 'received it' : 'physically hold it' }}.</p>
            @include('partials.qr-camera', ['id' => 'custodyReader'])
        </div>

        <p x-show="error" x-cloak x-text="error" class="cs-error" role="alert"></p>
        @if($errors->hasAny(['custody', 'scanned_value', 'override_reason', 'capture_method']))
            <p class="cs-error" role="alert">{{ collect(['custody', 'scanned_value', 'override_reason', 'capture_method'])->map(fn ($f) => $errors->first($f))->filter()->first() }}</p>
        @endif

        {{-- Auto-submitted when the scanned QR matches this document. --}}
        <form x-ref="scanForm" method="POST" action="{{ route('documents.custody.store', $document) }}" class="hidden">
            @csrf
            @if($inModal ?? false)<input type="hidden" name="from" value="internal">@endif
            <input type="hidden" name="capture_method" x-ref="captureMethod" value="scan">
            <input type="hidden" name="scanned_value" x-ref="scannedValue">
        </form>

        {{-- Audited fallback for a torn/unreadable QR — visibly secondary, and
             only offered once a scan is actually being attempted, so the detail
             view stays a single quiet line until someone acts. --}}
        {{-- Stays reachable when the camera failed, not only while scanning —
             a broken camera is exactly when the manual fallback is needed. --}}
        @unless($softCopy)
            <div class="custody-manual" x-show="scanning || error" x-cloak>
                <button type="button" class="cs-manual-link" @click="openManual()">QR torn or unreadable? Record manually</button>
                <form x-show="manualOpen" x-cloak method="POST" action="{{ route('documents.custody.store', $document) }}" class="cs-manual-form">
                    @csrf
                    @if($inModal ?? false)<input type="hidden" name="from" value="internal">@endif
                    <input type="hidden" name="capture_method" value="manual">
                    <label>Why can't the QR be scanned? (required)
                        <textarea name="override_reason" rows="2" maxlength="500" required
                                  placeholder="e.g. QR sticker torn off the folder"></textarea>
                    </label>
                    <button type="submit" class="cr-btn cr-btn-sm">Record custody manually</button>
                </form>
            </div>
        @endunless
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
                reading: false,

                /** One verdict for every way a QR arrives: camera or picture. */
                accept(decodedText, method) {
                    const tracking = window.SpeedQr.extractTracking(decodedText);
                    if (tracking === expected) {
                        // Match: submit with the raw decoded value for server-side verification.
                        this.submitted = true;
                        this.$refs.captureMethod.value = method;
                        this.$refs.scannedValue.value = decodedText;
                        this.$refs.scanForm.submit();

                        return;
                    }

                    this.error = tracking
                        ? `That QR is for a different request (${tracking}). Use this request's own QR.`
                        : window.SpeedQr.FOREIGN_CODE_MESSAGE;
                },

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
                        window.SpeedQr.stop();
                        this.scanning = false;
                        this.accept(decodedText, 'scan');
                    }, (cameraError) => {
                        this.scanning = false;
                        this.manualOpen = true;
                        this.error = window.SpeedQr.describe(cameraError);
                    });
                },

                async readFile(file) {
                    if (! file || this.submitted) return;
                    this.error = '';
                    this.reading = true;
                    try {
                        this.accept(await window.SpeedQr.decodeImage(file), 'image');
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.reading = false;
                    }
                },

                /** Drag a box around the QR wherever it shows on screen. */
                /**
                 * 'page': open this request's QR viewer and snip it — one
                 * click, since the viewer, once open, covers this card.
                 * 'any': the QR is in another window; capture that, then snip.
                 */
                async snip(mode) {
                    if (this.submitted) return;
                    if (this.scanning) this.toggleScan();
                    this.error = '';
                    if (mode === 'page') {
                        window.dispatchEvent(new CustomEvent('show-request-qr', { detail: expected }));
                        // Let the viewer fade in (150ms) before the page dims.
                        await new Promise((r) => setTimeout(r, 250));
                    }
                    try {
                        const decoded = await window.SpeedQr.scanFromScreen(mode);
                        if (decoded === null) {
                            window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));

                            return; // cancelled
                        }
                        this.reading = true;
                        this.accept(decoded, 'screen');
                    } catch (e) {
                        this.error = e.message || 'No QR code was found in that box. Drag it around the whole QR.';
                    } finally {
                        if (! this.submitted) {
                            this.reading = false;
                            // Out of the way, so the message and the button show.
                            window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
                        }
                    }
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
