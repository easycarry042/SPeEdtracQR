{{-- Look up an internal request by its QR or number — no account needed.
     On the staff sign-in page ("Track a request") and on the tracker's own
     page. Three ways in: type the number, scan the QR with the camera, or
     choose / drop a picture of the QR. Any of them submits the number to
     internal.track. $heading: show the title and line under it. --}}
<div x-data="internalTrackForm()">
    @if($heading ?? false)
        <h2 class="auth-heading">Track a request</h2>
        <p class="auth-subheading">Scan an internal request's QR, or type its number, to see which office has it and who is handling it. No account needed.</p>
    @endif

    <form method="GET" action="{{ route('internal.track') }}" x-ref="form" class="auth-form">
        <div class="form-group">
            <label for="trackNumber" class="form-label">Internal request number</label>
            <div class="field-shell">
                <span class="field-icon" aria-hidden="true">
                    <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2"/></svg>
                </span>
                <input id="trackNumber" type="text" name="number" x-ref="number" value="{{ $number ?? '' }}"
                       required autocomplete="off" spellcheck="false" autocapitalize="characters"
                       placeholder="INT-20261019-AB12CD"
                       class="form-input form-input-with-icon mono uppercase">
            </div>
        </div>

        <div class="it-actions">
            <button type="button" class="it-secondary" @click="toggleScan()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg>
                <span x-text="scanning ? 'Stop camera' : 'Scan QR'"></span>
            </button>
            <button type="submit" class="auth-button !mt-0">
                Track
                <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </button>
        </div>

        <div x-show="scanning" x-cloak class="it-camera">
            @include('partials.qr-camera', ['id' => 'internalTrackReader', 'class' => 'mx-auto w-full max-w-[320px]'])
        </div>

        {{-- No camera? Snip the QR off the screen — the browser asks which
             tab or window shows it, then a box is dragged around the QR — or
             choose a saved picture of it. --}}
        <div class="it-alt-row">
            <button type="button" class="it-image-link" @click="snip()" :disabled="reading">
                <span x-text="reading ? 'Reading the QR…' : 'Select the QR on screen'"></span>
            </button>
            <span aria-hidden="true">·</span>
            <label class="it-image-link" @dragover.prevent @drop.prevent="readDrop($event)">
                <input type="file" accept="image/*" class="sr-only" @change="readFile($event.target.files[0]); $event.target.value = ''">
                Choose a picture of the QR
            </label>
        </div>

        <p x-show="error" x-cloak x-text="error" class="form-error" role="alert"></p>
    </form>
</div>

@include('partials.qr-scan-helpers')
@once
    <script>
        window.internalTrackForm = function () {
            return {
                scanning: false,
                reading: false,
                error: '',

                /** Any QR this system issued — but only internal requests are tracked here. */
                use(decodedText) {
                    const tracking = window.SpeedQr.extractTracking(decodedText);
                    if (! tracking) {
                        this.error = window.SpeedQr.FOREIGN_CODE_MESSAGE;

                        return;
                    }
                    this.$refs.number.value = tracking;
                    this.$refs.form.submit();
                },

                toggleScan() {
                    this.error = '';
                    if (this.scanning) {
                        window.SpeedQr.stop();
                        this.scanning = false;

                        return;
                    }
                    this.scanning = true;
                    window.SpeedQr.start('internalTrackReader', (decodedText) => {
                        window.SpeedQr.stop();
                        this.scanning = false;
                        this.use(decodedText);
                    }, (cameraError) => {
                        this.scanning = false;
                        this.error = window.SpeedQr.describe(cameraError) + ' You can choose a picture of the QR instead, or type the number.';
                    });
                },

                async readFile(file) {
                    if (! file) return;
                    this.error = '';
                    this.reading = true;
                    try {
                        this.use(await window.SpeedQr.decodeImage(file));
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.reading = false;
                    }
                },

                /** Capture the tab / window showing the QR, then box it. */
                async snip() {
                    if (this.scanning) this.toggleScan();
                    this.error = '';
                    try {
                        const decoded = await window.SpeedQr.scanFromScreen('any');
                        if (decoded !== null) this.use(decoded);
                    } catch (e) {
                        this.error = e.message || 'No QR code was found in that box. Drag it around the whole QR.';
                    }
                },

                async readDrop(event) {
                    try {
                        await this.readFile(await window.SpeedQr.imageFromDrop(event.dataTransfer));
                    } catch (e) {
                        this.error = e.message;
                    }
                },
            };
        };
    </script>
@endonce
