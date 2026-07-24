<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-green-deep">File Internal Request</h1>
    </x-slot>

    <div class="page-shell" x-data="requestWizard()">

        {{-- Step indicator --}}
        <nav class="flex items-center gap-2 text-[13px]" aria-label="Progress">
            <template x-for="(label, i) in ['Paper & OCR', 'Details & Route', 'Review']" :key="i">
                <div class="flex items-center gap-2">
                    <button type="button" @click="if (i + 1 < step) step = i + 1"
                            :aria-current="step === i + 1 ? 'step' : false"
                            class="flex items-center gap-2 rounded-full px-3 py-1.5 font-semibold transition"
                            :class="step === i + 1 ? 'bg-green text-white' : (step > i + 1 ? 'bg-green-wash text-green-deep' : 'bg-[#eef2ef] text-ink-soft')">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px]"
                              :class="step === i + 1 ? 'bg-white/20' : (step > i + 1 ? 'bg-white/70 text-green-deep' : 'bg-white/70')"
                              x-text="step > i + 1 ? '✓' : i + 1"></span>
                        <span x-text="label"></span>
                    </button>
                    <span x-show="i < 2" class="h-px w-6 bg-hairline-strong"></span>
                </div>
            </template>
        </nav>

        <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="mt-4">
            @csrf

            @if($errors->any())
                <div class="mb-4 rounded-[8px] border border-status-red-wash bg-status-red-wash px-4 py-3 text-[13px] font-medium text-status-red">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    {{-- Browsers cannot repopulate a file input after a failed submit. --}}
                    <p class="mt-2 flex items-center gap-1.5 font-semibold">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                        If you attached a paper scan, please re-attach it — it was not kept through the error.
                    </p>
                </div>
            @endif

            {{-- ============ STEP 1: Paper upload + OCR ============ --}}
            <section x-show="step === 1" class="panel">
                <div class="ph">
                    <h2>Scan the paper request</h2>
                    <span class="sub">Upload a photo or scan of the signed request from {{ $department->name }}.</span>
                </div>

                <div class="grid grid-cols-1 gap-5 p-4 lg:grid-cols-2 lg:p-6">
                    <div class="space-y-3">
                        <label class="flex min-h-[220px] cursor-pointer flex-col items-center justify-center gap-2 rounded-[10px] border-2 border-dashed border-hairline-strong bg-[#f4f7f5] p-6 text-center transition hover:border-green focus-within:border-green focus-within:ring-2 focus-within:ring-green/25"
                               :class="fileName ? 'border-green bg-green-wash' : ''">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Preview of the uploaded paper request" class="max-h-64 rounded-[8px] border border-hairline">
                            </template>
                            <template x-if="!previewUrl">
                                <div>
                                    <svg class="mx-auto h-10 w-10 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                    <p class="mt-2 text-[13px] font-semibold text-ink">Click or press Enter to upload the paper request</p>
                                    <p class="text-[12px] text-ink-soft">JPG, PNG, WEBP or PDF · up to 10&nbsp;MB</p>
                                </div>
                            </template>
                            <span x-show="fileName" x-text="fileName" class="max-w-full truncate text-[12px] font-medium text-green-deep"></span>
                            {{-- sr-only (not hidden) keeps the input keyboard-focusable and Tab-reachable. --}}
                            <input type="file" name="paper_scan" accept="image/jpeg,image/png,image/webp,application/pdf"
                                   class="sr-only" x-ref="paperInput" @change="onFileChange($event)">
                        </label>
                        @error('paper_scan')<p class="text-[12px] text-status-red">{{ $message }}</p>@enderror

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="runExtraction()" :disabled="!canOcr || ocrRunning"
                                    class="cr-btn cr-btn-primary"
                                    :class="(!canOcr || ocrRunning) ? 'cursor-not-allowed opacity-40' : ''">
                                <span x-show="!ocrRunning">Extract text (OCR)</span>
                                <span x-show="ocrRunning" x-text="`Reading… ${ocrProgress}%`"></span>
                            </button>
                            <p x-show="fileName && !canOcr" class="text-[12px] text-ink-soft">OCR works on image uploads; PDFs are attached as-is.</p>
                        </div>

                        <div x-show="ocrRunning" class="h-2 overflow-hidden rounded-full bg-[#eef2ef]" role="progressbar" :aria-valuenow="ocrProgress" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-green-bright transition-all" :style="`width:${ocrProgress}%`"></div>
                        </div>
                        <p x-show="ocrError" x-text="ocrError" class="text-[12px] text-status-red" role="alert"></p>
                    </div>

                    <div class="space-y-2">
                        <label for="ocr-text" class="block text-[13px] font-semibold text-ink">Extracted text</label>
                        <textarea id="ocr-text" x-model="ocrText" rows="10" placeholder="Run OCR to see the recognized text here. You can correct mistakes before continuing."
                                  class="w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 font-mono text-[12px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25"></textarea>
                        <p class="text-[12px] text-ink-soft">OCR is never trusted blindly — you review and can edit everything on the next step.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-hairline px-4 py-4 lg:px-6">
                    <button type="button" @click="skipOcr()" class="cr-btn">Skip — type manually</button>
                    <button type="button" @click="applyOcr()" :disabled="!ocrText.trim()"
                            class="cr-btn cr-btn-primary"
                            :class="!ocrText.trim() ? 'cursor-not-allowed opacity-40' : ''">
                        Use extracted text →
                    </button>
                </div>
            </section>

            {{-- ============ STEP 2: Details + route ============ --}}
            <section x-show="step === 2" x-cloak class="panel">
                <div class="ph">
                    <h2>Request details</h2>
                    <span class="sub">Filing on behalf of {{ $department->name }} ({{ $department->code }})</span>
                </div>

                <div class="grid grid-cols-1 gap-5 p-4 lg:grid-cols-2 lg:p-6">
                    <div class="space-y-5">
                        <div>
                            <label for="route-select" class="block text-[13px] font-semibold text-ink">Request Route <span class="text-status-red">*</span></label>
                            <select id="route-select" name="route_template_id" x-model="form.route_template_id" required
                                    class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('route_template_id') border-status-red @enderror">
                                <option value="">Select a route…</option>
                                <template x-for="t in templates" :key="t.id">
                                    <option :value="t.id" x-text="t.name" :selected="String(form.route_template_id) === String(t.id)"></option>
                                </template>
                            </select>
                            <p x-show="selectedTemplate?.description" x-text="selectedTemplate?.description" class="mt-1 text-[12px] text-ink-soft"></p>
                            @error('route_template_id')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="purpose" class="block text-[13px] font-semibold text-ink">What is being requested? <span class="text-status-red">*</span></label>
                            <input id="purpose" type="text" name="purpose" x-model="form.purpose" required maxlength="255"
                                   placeholder="e.g. 10 monoblock chairs and a flower vase for the lobby"
                                   class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('purpose') border-status-red @enderror">
                            @error('purpose')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="amount" class="block text-[13px] font-semibold text-ink">Estimated Amount (₱)</label>
                            <input id="amount" type="number" name="amount" x-model="form.amount" min="0" step="0.01"
                                   @input="amountGuessed = false"
                                   placeholder="Leave blank if no budget is involved"
                                   class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('amount') border-status-red @enderror">
                            <p class="mt-1 text-[12px]" :class="amountNumber !== null && amountNumber >= threshold ? 'font-semibold text-status-amber' : 'text-ink-soft'">
                                <span x-show="amountNumber === null">No amount → budget/procurement steps are skipped automatically.</span>
                                <span x-show="amountNumber !== null && amountNumber < threshold" x-text="`${formatPeso(amountNumber)} — below ${formatPeso(threshold)}: Small Value Procurement path.`"></span>
                                <span x-show="amountNumber !== null && amountNumber >= threshold" x-text="`${formatPeso(amountNumber)} — at or above ${formatPeso(threshold)}: Public Bidding path (RA 12009).`"></span>
                            </p>
                            <p x-show="amountGuessed" x-cloak class="mt-1 flex items-center gap-1 text-[12px] font-medium text-status-amber">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                Read from the scan — confirm it before continuing, it decides the procurement path.
                            </p>
                            @error('amount')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="description" class="block text-[13px] font-semibold text-ink">Details</label>
                            <textarea id="description" name="description" x-model="form.description" rows="5" maxlength="5000"
                                      placeholder="Specifications, justification, or the OCR text from the paper request."
                                      class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25"></textarea>
                        </div>
                    </div>

                    {{-- Live chain preview --}}
                    <div class="rounded-[10px] border border-hairline bg-green-wash/40 p-5">
                        <h3 class="text-[13px] font-bold text-green-deep">Endorsement chain preview</h3>
                        <p class="mt-0.5 text-[12px] text-ink-soft">Where this request will travel, based on the route and amount.</p>

                        <p x-show="!selectedTemplate" class="mt-4 text-[13px] text-ink-soft">Select a route to preview its steps.</p>

                        <ol x-show="selectedTemplate" class="mt-4 space-y-0">
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-green text-[11px] font-bold text-white">0</span>
                                    <span class="w-px flex-1 bg-green-wash"></span>
                                </div>
                                <div class="pb-4">
                                    <p class="text-[13px] font-semibold text-ink">{{ $department->name }}</p>
                                    <p class="text-[12px] text-ink-soft">Files the request</p>
                                </div>
                            </li>
                            <template x-for="(s, i) in resolvedSteps" :key="i">
                                <li class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-[11px] font-bold text-green-deep ring-2 ring-green/40" x-text="i + 1"></span>
                                        <span x-show="i < resolvedSteps.length - 1" class="w-px flex-1 bg-green-wash"></span>
                                    </div>
                                    <div class="pb-4">
                                        <p class="text-[13px] font-semibold text-ink">
                                            <span x-text="s.department.name"></span>
                                            <span class="ml-1 rounded bg-[#eef2ef] px-1.5 py-0.5 font-mono text-[10px] text-ink-soft" x-text="s.department.code"></span>
                                        </p>
                                        <p class="text-[12px] text-ink-soft" x-text="s.action"></p>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-hairline px-4 py-4 lg:px-6">
                    <button type="button" @click="step = 1" class="cr-btn">← Back</button>
                    <button type="button" @click="goReview()" :disabled="!form.route_template_id || !form.purpose.trim()"
                            class="cr-btn cr-btn-primary"
                            :class="(!form.route_template_id || !form.purpose.trim()) ? 'cursor-not-allowed opacity-40' : ''">
                        Review →
                    </button>
                </div>
            </section>

            {{-- ============ STEP 3: Review + submit ============ --}}
            <section x-show="step === 3" x-cloak class="panel">
                <div class="ph">
                    <h2>Review &amp; file</h2>
                    <span class="sub">A tracking number and QR are generated on filing.</span>
                </div>

                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 p-4 sm:grid-cols-2 lg:p-6">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Requesting office</dt>
                        <dd class="mt-0.5 text-[14px] font-semibold text-ink">{{ $department->name }} ({{ $department->code }})</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Route</dt>
                        <dd class="mt-0.5 text-[14px] font-semibold text-ink" x-text="selectedTemplate?.name ?? '—'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Request</dt>
                        <dd class="mt-0.5 text-[14px] text-ink" x-text="form.purpose"></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Amount</dt>
                        <dd class="mt-0.5 text-[14px] text-ink">
                            <span x-text="amountNumber === null ? 'No budget involved' : formatPeso(amountNumber)"></span>
                            <span x-show="amountGuessed && amountNumber !== null" x-cloak class="ml-1 rounded bg-status-amber-wash px-1.5 py-0.5 text-[10px] font-semibold text-status-amber">read from scan</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Paper scan</dt>
                        <dd class="mt-0.5 text-[14px] text-ink" x-text="fileName || 'None attached'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Chain</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-1.5 text-[12px] font-semibold text-ink">
                            <span class="rounded-full bg-green-wash px-2.5 py-1 text-green-deep">{{ $department->code }}</span>
                            <template x-for="(s, i) in resolvedSteps" :key="i">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-ink-soft">→</span>
                                    <span class="rounded-full bg-[#eef2ef] px-2.5 py-1" x-text="s.department.code"></span>
                                </span>
                            </template>
                        </dd>
                    </div>
                </dl>

                <div class="flex items-center justify-between gap-3 border-t border-hairline px-4 py-4 lg:px-6">
                    <button type="button" @click="step = 2" class="cr-btn">← Back</button>
                    <button type="submit" class="cr-btn cr-btn-primary px-6">File Request</button>
                </div>
            </section>
        </form>
    </div>

    @vite('resources/js/ocr.js')
    <script>
        function requestWizard() {
            return {
                step: 1,
                templates: @json($templatesJson),
                threshold: {{ $biddingThreshold }},
                fileName: '',
                previewUrl: null,
                canOcr: false,
                ocrRunning: false,
                ocrProgress: 0,
                ocrError: '',
                ocrText: '',
                amountGuessed: false,
                form: {
                    route_template_id: @json(old('route_template_id', '')),
                    purpose: @json(old('purpose', '')),
                    amount: @json(old('amount', '')),
                    description: @json(old('description', '')),
                },

                init() {
                    // Coming back from a validation error skips straight to the form.
                    if (this.form.purpose || this.form.route_template_id) { this.step = 2; }
                },

                get amountNumber() {
                    const n = parseFloat(this.form.amount);
                    return Number.isFinite(n) && this.form.amount !== '' ? n : null;
                },

                get selectedTemplate() {
                    return this.templates.find(t => String(t.id) === String(this.form.route_template_id)) ?? null;
                },

                get resolvedSteps() {
                    if (!this.selectedTemplate) { return []; }
                    const amount = this.amountNumber;
                    return this.selectedTemplate.steps.filter(s => {
                        switch (s.condition) {
                            case 'has_amount': return amount !== null;
                            case 'below_threshold': return amount !== null && amount < this.threshold;
                            case 'at_least_threshold': return amount !== null && amount >= this.threshold;
                            default: return true;
                        }
                    });
                },

                formatPeso(value) {
                    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(value);
                },

                onFileChange(event) {
                    const file = event.target.files[0];
                    this.ocrError = '';
                    this.ocrText = '';
                    if (!file) { this.fileName = ''; this.previewUrl = null; this.canOcr = false; return; }
                    this.fileName = file.name;
                    this.canOcr = file.type.startsWith('image/');
                    this.previewUrl = this.canOcr ? URL.createObjectURL(file) : null;
                },

                async runExtraction() {
                    const file = this.$refs.paperInput.files[0];
                    if (!file || !this.canOcr) { return; }
                    this.ocrRunning = true;
                    this.ocrProgress = 0;
                    this.ocrError = '';
                    try {
                        this.ocrText = await window.runOcr(file, (p) => { this.ocrProgress = p; });
                        if (!this.ocrText.trim()) {
                            this.ocrError = 'No readable text found — try a sharper, well-lit photo, or type the details manually.';
                        }
                    } catch (e) {
                        console.error(e);
                        this.ocrError = 'OCR failed to load or read the image. You can still type the details manually.';
                    } finally {
                        this.ocrRunning = false;
                    }
                },

                applyOcr() {
                    const text = this.ocrText.trim();
                    if (text) {
                        // First substantial line becomes the request summary…
                        const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 3);
                        if (!this.form.purpose && lines.length) { this.form.purpose = lines[0].slice(0, 255); }
                        // …the largest peso-looking figure becomes the amount (flagged as a guess)…
                        const matches = text.match(/(?:₱|PHP|P)?\s?\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d+\.\d{2}/g) ?? [];
                        const amounts = matches.map(m => parseFloat(m.replace(/[^\d.]/g, ''))).filter(n => Number.isFinite(n) && n > 0);
                        if (!this.form.amount && amounts.length) {
                            this.form.amount = String(Math.max(...amounts));
                            this.amountGuessed = true;
                        }
                        // …and the full text lands in Details for reference.
                        if (!this.form.description) { this.form.description = text; }
                    }
                    this.step = 2;
                },

                skipOcr() { this.step = 2; },

                goReview() {
                    if (this.form.route_template_id && this.form.purpose.trim()) { this.step = 3; }
                },
            };
        }
    </script>
</x-app-layout>
