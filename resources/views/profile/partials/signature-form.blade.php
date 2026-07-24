<section x-data="signaturePad()">
    <header>
        <h2 class="text-[15px] font-semibold text-green-deep">E-Signature</h2>
        <p class="mt-1 text-[13px] text-ink-soft">
            Draw your signature once. Approving an internal request affixes it (after a password confirmation) as the official endorsement of your office.
        </p>
    </header>

    @if(session('status') === 'signature-saved')
        <p class="mt-3 rounded-[8px] border border-green-wash bg-green-wash px-4 py-2.5 text-[13px] font-medium text-green-deep">Signature saved.</p>
    @elseif(session('status') === 'signature-removed')
        <p class="mt-3 rounded-[8px] border border-status-amber-wash bg-status-amber-wash px-4 py-2.5 text-[13px] font-medium text-status-amber">Signature removed.</p>
    @endif
    @error('signature')<p class="mt-3 text-[13px] text-status-red">{{ $message }}</p>@enderror

    @if(auth()->user()->signature_path)
        <div class="mt-4 flex items-center gap-4">
            <img src="{{ route('profile.signature.show') }}" alt="Your registered e-signature"
                 class="h-16 rounded-[8px] border border-hairline bg-white px-3 py-2">
            <form method="POST" action="{{ route('profile.signature.destroy') }}"
                  onsubmit="return confirm('Remove your registered signature? You will not be able to approve internal requests until you draw a new one.');">
                @csrf @method('DELETE')
                <button type="submit" class="text-[13px] font-semibold text-status-red hover:underline">Remove</button>
            </form>
        </div>
        <p class="mt-2 text-[12px] text-ink-soft">Drawing and saving below replaces it. Signatures already affixed to past approvals are frozen copies and stay unchanged.</p>
    @endif

    <div class="mt-4">
        <canvas x-ref="pad" width="560" height="180" role="img" aria-label="Signature drawing area"
                class="w-full max-w-xl cursor-crosshair touch-none rounded-[10px] border-2 border-dashed border-hairline-strong bg-white"
                @pointerdown="start($event)" @pointermove="draw($event)" @pointerup="stop()" @pointerleave="stop()"></canvas>
        <p class="mt-1 text-[12px] text-ink-soft">Sign inside the box using your mouse, finger, or stylus.</p>
    </div>

    <form method="POST" action="{{ route('profile.signature.store') }}" class="mt-4 flex items-center gap-3" @submit="fill()">
        @csrf
        <input type="hidden" name="signature" x-ref="payload">
        <button type="submit" :disabled="!dirty"
                class="cr-btn cr-btn-primary"
                :class="!dirty ? 'cursor-not-allowed opacity-40' : ''">
            Save signature
        </button>
        <button type="button" @click="clearPad()" class="cr-btn">Clear</button>
    </form>

    <script>
        function signaturePad() {
            return {
                drawing: false,
                dirty: false,
                ctx: null,

                init() {
                    this.ctx = this.$refs.pad.getContext('2d');
                    this.ctx.lineWidth = 2.5;
                    this.ctx.lineCap = 'round';
                    this.ctx.lineJoin = 'round';
                    this.ctx.strokeStyle = '#16211b';
                },

                point(event) {
                    const rect = this.$refs.pad.getBoundingClientRect();
                    // The canvas is CSS-scaled; map pointer coords back to bitmap space.
                    return {
                        x: (event.clientX - rect.left) * (this.$refs.pad.width / rect.width),
                        y: (event.clientY - rect.top) * (this.$refs.pad.height / rect.height),
                    };
                },

                start(event) {
                    this.drawing = true;
                    const p = this.point(event);
                    this.ctx.beginPath();
                    this.ctx.moveTo(p.x, p.y);
                },

                draw(event) {
                    if (!this.drawing) { return; }
                    const p = this.point(event);
                    this.ctx.lineTo(p.x, p.y);
                    this.ctx.stroke();
                    this.dirty = true;
                },

                stop() { this.drawing = false; },

                clearPad() {
                    this.ctx.clearRect(0, 0, this.$refs.pad.width, this.$refs.pad.height);
                    this.dirty = false;
                },

                fill() {
                    this.$refs.payload.value = this.$refs.pad.toDataURL('image/png');
                },
            };
        }
    </script>
</section>
