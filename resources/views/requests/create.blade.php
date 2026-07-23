<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-emerald-950">File Internal Request</h1>
    </x-slot>

    <div class="page-shell" x-data="requestWizard()">

        {{-- Step indicator --}}
        <div class="flex items-center gap-2 text-sm">
            <template x-for="(label, i) in ['Paper & OCR', 'Details & Route', 'Review']" :key="i">
                <div class="flex items-center gap-2">
                    <button type="button" @click="if (i + 1 < step) step = i + 1"
                            class="flex items-center gap-2 rounded-full px-3 py-1.5 font-semibold transition"
                            :class="step === i + 1 ? 'bg-emerald-700 text-white' : (step > i + 1 ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-400')">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs"
                              :class="step === i + 1 ? 'bg-white/20' : (step > i + 1 ? 'bg-emerald-200' : 'bg-gray-200')"
                              x-text="step > i + 1 ? '✓' : i + 1"></span>
                        <span x-text="label"></span>
                    </button>
                    <span x-show="i < 2" class="h-px w-6 bg-gray-300"></span>
                </div>
            </template>
        </div>

        <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="mt-4">
            @csrf

            @if($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ============ STEP 1: Paper upload + OCR ============ --}}
            <div x-show="step === 1" class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Scan the paper request</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Upload a photo or scan of the signed request from {{ $department->name }}. OCR will prefill the details for you — or skip and type them manually.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 p-6 lg:grid-cols-2">
                    <div class="space-y-3">
                        <label class="flex min-h-[220px] cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40"
                               :class="fileName ? 'border-emerald-300 bg-emerald-50/40' : ''">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Paper preview" class="max-h-64 rounded-lg shadow-sm">
                            </template>
                            <template x-if="!previewUrl">
                                <div>
                                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                    <p class="mt-2 text-sm font-semibold text-gray-600">Click to upload the paper request</p>
                                    <p class="text-xs text-gray-400">JPG, PNG, WEBP or PDF · up to 10&nbsp;MB</p>
                                </div>
                            </template>
                            <span x-show="fileName" x-text="fileName" class="max-w-full truncate text-xs font-medium text-emerald-700"></span>
                            <input type="file" name="paper_scan" accept="image/jpeg,image/png,image/webp,application/pdf"
                                   class="hidden" x-ref="paperInput" @change="onFileChange($event)">
                        </label>
                        @error('paper_scan')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="runExtraction()" :disabled="!canOcr || ocrRunning"
                                    class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">
                                <span x-show="!ocrRunning">Extract text (OCR)</span>
                                <span x-show="ocrRunning" x-text="`Reading… ${ocrProgress}%`"></span>
                            </button>
                            <p x-show="fileName && !canOcr" class="text-xs text-gray-500">OCR works on image uploads; PDFs are attached as-is.</p>
                        </div>

                        <div x-show="ocrRunning" class="h-2 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width:${ocrProgress}%`"></div>
                        </div>
                        <p x-show="ocrError" x-text="ocrError" class="text-xs text-red-600"></p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Extracted text</label>
                        <textarea x-model="ocrText" rows="10" placeholder="Run OCR to see the recognized text here. You can correct mistakes before continuing."
                                  class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 font-mono text-xs shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30"></textarea>
                        <p class="text-xs text-gray-500">OCR is never trusted blindly — you review and can edit everything on the next step.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="skipOcr()"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        Skip — type manually
                    </button>
                    <button type="button" @click="applyOcr()" :disabled="!ocrText.trim()"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">
                        Use extracted text →
                    </button>
                </div>
            </div>

            {{-- ============ STEP 2: Details + route ============ --}}
            <div x-show="step === 2" x-cloak class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Request details</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Filing on behalf of <span class="font-semibold text-emerald-800">{{ $department->name }} ({{ $department->code }})</span>.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 p-6 lg:grid-cols-2">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Request Route <span class="text-red-500">*</span></label>
                            <select name="route_template_id" x-model="form.route_template_id" required
                                    class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:outline-none @error('route_template_id') border-red-400 @enderror">
                                <option value="">Select a route…</option>
                                <template x-for="t in templates" :key="t.id">
                                    <option :value="t.id" x-text="t.name" :selected="String(form.route_template_id) === String(t.id)"></option>
                                </template>
                            </select>
                            <p x-show="selectedTemplate?.description" x-text="selectedTemplate?.description" class="mt-1 text-xs text-gray-500"></p>
                            @error('route_template_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">What is being requested? <span class="text-red-500">*</span></label>
                            <input type="text" name="purpose" x-model="form.purpose" required maxlength="255"
                                   placeholder="e.g. 10 monoblock chairs and a flower vase for the lobby"
                                   class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('purpose') border-red-400 @enderror">
                            @error('purpose')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Estimated Amount (₱)</label>
                            <input type="number" name="amount" x-model="form.amount" min="0" step="0.01"
                                   placeholder="Leave blank if no budget is involved"
                                   class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 @error('amount') border-red-400 @enderror">
                            <p class="mt-1 text-xs" :class="amountNumber !== null && amountNumber >= threshold ? 'font-semibold text-amber-700' : 'text-gray-500'">
                                <span x-show="amountNumber === null">No amount → budget/procurement steps are skipped automatically.</span>
                                <span x-show="amountNumber !== null && amountNumber < threshold" x-text="`${formatPeso(amountNumber)} — below ${formatPeso(threshold)}: Small Value Procurement path.`"></span>
                                <span x-show="amountNumber !== null && amountNumber >= threshold" x-text="`${formatPeso(amountNumber)} — at or above ${formatPeso(threshold)}: Public Bidding path (RA 12009).`"></span>
                            </p>
                            @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Details</label>
                            <textarea name="description" x-model="form.description" rows="5"
                                      placeholder="Specifications, justification, or the OCR text from the paper request."
                                      class="mt-1 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm shadow-sm transition focus:border-emerald-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30"></textarea>
                        </div>
                    </div>

                    {{-- Live chain preview --}}
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-5">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-emerald-900">Endorsement chain preview</h3>
                        <p class="mt-0.5 text-xs text-emerald-800/70">Where this request will travel, based on the route and amount.</p>

                        <p x-show="!selectedTemplate" class="mt-4 text-sm text-gray-500">Select a route to preview its steps.</p>

                        <ol x-show="selectedTemplate" class="mt-4 space-y-0">
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white">0</span>
                                    <span class="w-px flex-1 bg-emerald-200"></span>
                                </div>
                                <div class="pb-4">
                                    <p class="text-sm font-semibold text-gray-800">{{ $department->name }}</p>
                                    <p class="text-xs text-gray-500">Files the request</p>
                                </div>
                            </li>
                            <template x-for="(s, i) in resolvedSteps" :key="i">
                                <li class="flex gap-3">
                                    <div class="flex flex-col items-center">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-xs font-bold text-emerald-800 ring-2 ring-emerald-300" x-text="i + 1"></span>
                                        <span x-show="i < resolvedSteps.length - 1" class="w-px flex-1 bg-emerald-200"></span>
                                    </div>
                                    <div class="pb-4">
                                        <p class="text-sm font-semibold text-gray-800">
                                            <span x-text="s.department.name"></span>
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-500" x-text="s.department.code"></span>
                                        </p>
                                        <p class="text-xs text-gray-500" x-text="s.action"></p>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="step = 1"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        ← Back
                    </button>
                    <button type="button" @click="goReview()" :disabled="!form.route_template_id || !form.purpose.trim()"
                            class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">
                        Review →
                    </button>
                </div>
            </div>

            {{-- ============ STEP 3: Review + submit ============ --}}
            <div x-show="step === 3" x-cloak class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Review &amp; file</h2>
                    <p class="mt-0.5 text-sm text-gray-500">On filing, a tracking number and QR are generated; print the sticker or use the QR-stamped digital copy.</p>
                </div>

                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Requesting office</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-gray-800">{{ $department->name }} ({{ $department->code }})</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Route</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-gray-800" x-text="selectedTemplate?.name ?? '—'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Request</dt>
                        <dd class="mt-0.5 text-sm text-gray-800" x-text="form.purpose"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Amount</dt>
                        <dd class="mt-0.5 text-sm text-gray-800" x-text="amountNumber === null ? 'No budget involved' : formatPeso(amountNumber)"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Paper scan</dt>
                        <dd class="mt-0.5 text-sm text-gray-800" x-text="fileName || 'None attached'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400">Chain</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-1.5 text-xs font-semibold text-gray-700">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800">{{ $department->code }}</span>
                            <template x-for="(s, i) in resolvedSteps" :key="i">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-gray-300">→</span>
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1" x-text="s.department.code"></span>
                                </span>
                            </template>
                        </dd>
                    </div>
                </dl>

                <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" @click="step = 2"
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                        ← Back
                    </button>
                    <button type="submit"
                            class="rounded-xl bg-emerald-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                        File Request
                    </button>
                </div>
            </div>
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
                        // …the largest peso-looking figure becomes the amount…
                        const matches = text.match(/(?:₱|PHP|P)?\s?\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d+\.\d{2}/g) ?? [];
                        const amounts = matches.map(m => parseFloat(m.replace(/[^\d.]/g, ''))).filter(n => Number.isFinite(n) && n > 0);
                        if (!this.form.amount && amounts.length) { this.form.amount = String(Math.max(...amounts)); }
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
