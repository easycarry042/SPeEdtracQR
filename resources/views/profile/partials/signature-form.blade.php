<section x-data="signaturePad()">
    <header>
        <h2 class="text-lg font-medium text-gray-900">E-Signature</h2>
        <p class="mt-1 text-sm text-gray-600">
            Draw your signature once. Approving an internal request affixes it (after a password confirmation) as the official endorsement of your office.
        </p>
    </header>

    @if(session('status') === 'signature-saved')
        <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800">Signature saved.</p>
    @elseif(session('status') === 'signature-removed')
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-800">Signature removed.</p>
    @endif
    @error('signature')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror

    @if(auth()->user()->signature_path)
        <div class="mt-4 flex items-center gap-4">
            <img src="{{ route('profile.signature.show') }}" alt="Your registered signature"
                 class="h-16 rounded-xl border border-gray-200 bg-white px-3 py-2">
            <form method="POST" action="{{ route('profile.signature.destroy') }}"
                  onsubmit="return confirm('Remove your registered signature? You will not be able to approve internal requests until you draw a new one.');">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-red-600 hover:underline">Remove</button>
            </form>
        </div>
        <p class="mt-2 text-xs text-gray-500">Drawing and saving below replaces it. Signatures already affixed to past approvals are frozen copies and stay unchanged.</p>
    @endif

    <div class="mt-4">
        <canvas x-ref="pad" width="560" height="180"
                class="w-full max-w-xl cursor-crosshair touch-none rounded-xl border-2 border-dashed border-gray-300 bg-white"
                @pointerdown="start($event)" @pointermove="draw($event)" @pointerup="stop()" @pointerleave="stop()"></canvas>
        <p class="mt-1 text-xs text-gray-500">Sign inside the box using your mouse, finger, or stylus.</p>
    </div>

    <form method="POST" action="{{ route('profile.signature.store') }}" class="mt-4 flex items-center gap-3" @submit="fill()">
        @csrf
        <input type="hidden" name="signature" x-ref="payload">
        <button type="submit" :disabled="!dirty"
                class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">
            Save signature
        </button>
        <button type="button" @click="clearPad()"
                class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
            Clear
        </button>
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
                    this.ctx.strokeStyle = '#1f2937';
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
