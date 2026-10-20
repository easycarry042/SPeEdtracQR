{{-- The internal-request filing form: Details → Review, with QR placement on
     the scanned page. Shown in a modal on the Internal page and on its own page
     at /requests/create; the wrapper supplies the card (.dm-card). Expects
     $department, $departments and $departmentsJson.

     $inModal adds the close button; Esc steps back one layer (the detailed QR
     editor first) and then asks the modal to close via `close-file-request`. --}}
@php $inModal = $inModal ?? false; @endphp

<div x-data="requestForm()" class="flex h-full min-h-0 flex-col"
     @keydown.escape.window="qrModal ? (qrModal = false) : $dispatch('close-file-request')">

    {{-- Header: title and the filing office. --}}
    <div class="dm-head">
        <div class="min-w-0">
            <p class="dm-title">File a Request</p>
            <p class="text-[length:var(--fs-small)] text-ink">Filing from the {{ $department->name }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            {{-- The two steps; a finished step can be clicked to go back to it. --}}
            <ol class="ff-steps" aria-label="Progress">
                <template x-for="(label, i) in ['Details', 'Review']" :key="i">
                    <li class="flex items-center gap-2">
                        <span x-show="i > 0" class="ff-step-line" :class="step > i && 'is-done'" aria-hidden="true"></span>
                        <button type="button" @click="if (i + 1 < step) step = i + 1"
                                :aria-current="step === i + 1 ? 'step' : false"
                                class="ff-step" :class="step === i + 1 ? 'is-current' : (step > i + 1 ? 'is-done' : '')">
                            <span class="ff-step-n">
                                <svg x-show="step > i + 1" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 12.5 4 4 8-9"/></svg>
                                <span x-show="step <= i + 1" x-text="i + 1"></span>
                            </span>
                            <span x-text="label"></span>
                        </button>
                    </li>
                </template>
            </ol>
            @if($inModal)
                <button type="button" @click="$dispatch('close-file-request')" aria-label="Close" class="dm-close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="flex min-h-0 flex-1 flex-col">
        @csrf

        <div class="dm-body">
            @if($errors->any())
                <div class="mb-5 rounded-[12px] border border-status-red-wash bg-status-red-wash px-4 py-3 text-[length:var(--fs-small)] font-medium text-status-red" role="alert">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    {{-- Browsers cannot repopulate a file input after a failed submit. --}}
                    <p class="mt-2 font-semibold">If you attached a scanned document, please re-attach it — it was not kept through the error.</p>
                </div>
            @endif

            {{-- ============ STEP 1: Details ============ --}}
            <div x-show="step === 1" class="grid min-h-full grid-cols-1 gap-6 md:grid-cols-2 md:grid-rows-[1fr]">
                <div class="flex flex-col gap-5">
                    <div>
                        <label for="route-select" class="st-filter-label">First Department Route <span class="text-status-red">*</span></label>

                        {{-- The route at a glance: your office endorses it, then it
                             goes to the department picked below. --}}
                        <div class="ff-route" :class="selectedDepartment && 'is-set'" aria-hidden="true">
                            <div class="ff-route-track">
                                <span class="ff-route-node is-own"></span>
                                <span class="ff-route-line"></span>
                                <span class="ff-route-node is-next"></span>
                            </div>
                            <div class="ff-route-labels">
                                <span class="is-own">{{ $department->name }}</span>
                                <span class="is-next" x-text="selectedDepartment ? selectedDepartment.name : 'Next Route'"></span>
                            </div>
                        </div>

                        <select id="route-select" name="first_department_id" x-model="form.first_department_id" required
                                class="st-filter-field mt-3 @error('first_department_id') !border-status-red @enderror">
                            <option value="">Select a Department…</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}" @selected(old('first_department_id') == $d->id)>{{ $d->name }} ({{ $d->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="purpose" class="st-filter-label">What is being Requested? <span class="text-status-red">*</span></label>
                        <input id="purpose" type="text" name="purpose" x-model="form.purpose" required maxlength="255"
                               placeholder="e.g. 10 Monoblock chairs and a flower vase…"
                               class="st-filter-field @error('purpose') !border-status-red @enderror">
                    </div>

                    {{-- Paper or not. A hard copy travels as a folder each office
                         scans in; without one there is nothing to carry or scan,
                         so the request goes straight to a person the filer names. --}}
                    <div>
                        <input type="hidden" name="has_hard_copy" :value="form.has_hard_copy ? 1 : 0">
                        <label class="ff-toggle">
                            <input type="checkbox" role="switch" x-model="form.has_hard_copy" class="ff-toggle-input">
                            <span class="ff-toggle-track" aria-hidden="true"><span class="ff-toggle-thumb"></span></span>
                            <span class="min-w-0">
                                <span class="block text-[length:var(--fs-small)] font-semibold text-ink">A hard copy goes to the first office</span>
                                <span class="block text-[length:var(--fs-caption)] text-ink-soft"
                                      x-text="form.has_hard_copy
                                          ? 'Someone from your office hands over the paper; they scan its QR to receive it.'
                                          : 'Soft copy only: it reaches the person you choose below as soon as you file.'"></span>
                            </span>
                        </label>
                    </div>

                    <div x-show="! form.has_hard_copy" x-cloak>
                        <label for="recipient-select" class="st-filter-label">Who in the first office receives it? <span class="text-status-red">*</span></label>
                        <select id="recipient-select" name="recipient_id" x-model="form.recipient_id"
                                :required="! form.has_hard_copy" :disabled="form.has_hard_copy || ! form.first_department_id"
                                class="st-filter-field @error('recipient_id') !border-status-red @enderror">
                            <option value="" x-text="form.first_department_id ? 'Select a person…' : 'Choose the first department first'"></option>
                            <template x-for="person in recipientOptions" :key="person.id">
                                <option :value="person.id" x-text="`${person.name} · ${person.role}`" :selected="String(person.id) === String(form.recipient_id)"></option>
                            </template>
                        </select>
                        <p x-show="form.first_department_id && recipientOptions.length === 0" x-cloak class="mt-1 text-[length:var(--fs-caption)] font-semibold text-status-amber">
                            No one in that office has an active account yet. Send a hard copy instead, or ask an administrator to add them.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col">
                    <p class="st-filter-label">Scanned Document <span class="text-status-red">*</span></p>
                    <label class="ff-upload" :class="{ 'has-file': fileName, 'is-over': dragOver }"
                           @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
                           @drop.prevent="dropFile($event)">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Preview of the scanned document" class="max-h-[220px] rounded-[10px] border border-hairline bg-white shadow-sm">
                        </template>
                        <template x-if="!previewUrl">
                            <svg class="ff-upload-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15V4m0 0L7.5 8.5M12 4l4.5 4.5M5 15v3.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V15"/>
                            </svg>
                        </template>
                        <span x-show="!fileName" class="ff-upload-title">Click or Drop to upload a file</span>
                        <span x-show="fileName" x-cloak class="ff-upload-title max-w-full truncate" x-text="fileName"></span>
                        <span class="ff-upload-hint" x-text="fileName ? 'Click or drop another file to replace it' : 'JPG, PNG, WEBP or PDF up to 10 MB'"></span>
                        <span x-show="fileError" x-cloak class="mt-2 text-[length:var(--fs-small)] font-semibold text-status-red" role="alert" x-text="fileError"></span>
                        {{-- sr-only (not hidden) keeps the input keyboard-focusable. --}}
                        <input type="file" name="paper_scan" accept="{{ \App\Support\UploadRules::accept(\App\Support\UploadRules::PAPER_SCAN_EXTENSIONS) }}"
                               class="sr-only" x-ref="paperInput" @change="onFileChange($event)">
                    </label>
                </div>
            </div>

            {{-- ============ STEP 2: Review ============ --}}
            {{-- Side by side, like Details: what is being filed on the left, and
                 where the QR lands on the paper on the right. --}}
            <div x-show="step === 2" x-cloak class="grid min-h-full grid-cols-1 gap-6 md:grid-cols-2 md:grid-rows-[1fr]">
                <div class="flex flex-col">
                    <p class="st-filter-label">Summary</p>

                    {{-- The same route card as Details, now complete. --}}
                    <div class="ff-route is-set" aria-hidden="true">
                        <div class="ff-route-track">
                            <span class="ff-route-node is-own"></span>
                            <span class="ff-route-line"></span>
                            <span class="ff-route-node is-next"></span>
                        </div>
                        <div class="ff-route-labels">
                            <span class="is-own">{{ $department->name }}</span>
                            <span class="is-next" x-text="selectedDepartment ? selectedDepartment.name : 'Next Route'"></span>
                        </div>
                    </div>

                    <dl class="dm-info mt-2">
                        <div class="dm-info-row"><dt>Requesting office</dt><dd>{{ $department->name }} ({{ $department->code }})</dd></div>
                        <div class="dm-info-row"><dt>First department</dt><dd x-text="selectedDepartment ? `${selectedDepartment.name} (${selectedDepartment.code})` : '—'"></dd></div>
                        <div class="dm-info-row"><dt>Request</dt><dd x-text="form.purpose"></dd></div>
                        <div class="dm-info-row"><dt>Hard copy</dt><dd x-text="form.has_hard_copy ? 'Yes — paper goes to the first office' : 'No — soft copy only'"></dd></div>
                        <div class="dm-info-row" x-show="! form.has_hard_copy"><dt>Received by</dt><dd x-text="selectedRecipient ? `${selectedRecipient.name} (${selectedRecipient.role})` : '—'"></dd></div>
                        <div class="dm-info-row"><dt>Scanned document</dt><dd x-text="fileName || 'None attached'"></dd></div>
                    </dl>

                    <p class="ff-note mt-auto">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5M12 8h.01"/></svg>
                        A tracking number and QR are generated when you file.
                    </p>
                </div>

                {{-- Drag the QR to where it should be stamped on the paper.
                     Works for image scans and for PDFs (page by page). --}}
                <div class="flex min-h-0 flex-col">
                    <div class="flex items-center justify-between gap-2">
                        <p class="st-filter-label !mb-0">QR Placement</p>
                        {{-- Multi-page PDFs: choose which page carries the stamp. --}}
                        <div x-show="pdfFile && pdfPageCount > 1" class="flex items-center gap-1.5">
                            <button type="button" @click="goToPdfPage(pdfPage - 1)" :disabled="pdfPage <= 1" class="ff-page-btn" aria-label="Previous page">‹</button>
                            <span class="text-[length:var(--fs-caption)] font-semibold text-ink-soft" x-text="`Page ${pdfPage} of ${pdfPageCount}`"></span>
                            <button type="button" @click="goToPdfPage(pdfPage + 1)" :disabled="pdfPage >= pdfPageCount" class="ff-page-btn" aria-label="Next page">›</button>
                        </div>
                    </div>

                    <div class="ff-stage">
                        <p x-show="pdfRendering" class="text-[length:var(--fs-small)] font-semibold text-ink-soft">Opening the PDF…</p>
                        <p x-show="pdfError" x-text="pdfError" class="px-4 text-center text-[length:var(--fs-small)] font-semibold text-status-amber"></p>
                        <p x-show="! previewUrl && ! pdfRendering && ! pdfError" class="px-4 text-center text-[length:var(--fs-small)] text-ink-soft">This file can't be previewed, so the QR will be stamped at the bottom-right corner.</p>

                        <div x-show="previewUrl" class="relative inline-block select-none leading-none" data-qr-stage>
                            <img :src="previewUrl" alt="Scanned document" class="block max-h-[330px] w-auto rounded-[4px] shadow-sm" @load="initQrPlacement($event.target)" draggable="false">
                            <div x-show="qr.ready" class="ff-qr-box"
                                 :style="`left:${qr.x*100}%; top:${qr.y*100}%; width:${qr.size*100}%; aspect-ratio:1;`"
                                 @pointerdown="startDragQr($event)">
                                @include('requests.partials.qr-glyph')
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3" x-show="qr.ready" x-cloak>
                        <span class="text-[length:var(--fs-caption)] font-semibold text-ink-soft">Size</span>
                        <input type="range" min="0.12" max="0.40" step="0.005" x-model.number="qr.scaleShort" @input="applyQrScale()" class="h-1.5 flex-1 accent-green" aria-label="QR code size">
                        <button type="button" @click="qrModal = true" class="cr-btn shrink-0">Adjust in detail</button>
                    </div>
                    <p class="ff-hint">Drag the QR onto a clear area of the paper — it is stamped exactly there.</p>

                    <input type="hidden" name="qr_x" :value="qr.ready ? qr.x.toFixed(4) : ''">
                    <input type="hidden" name="qr_y" :value="qr.ready ? qr.y.toFixed(4) : ''">
                    <input type="hidden" name="qr_size" :value="qr.ready ? qr.scaleShort.toFixed(4) : ''">
                    <input type="hidden" name="qr_page" :value="pdfFile ? pdfPage : ''">
                </div>
            </div>
        </div>

        <div class="dm-foot dm-actions">
            <button type="button" x-show="step === 2" @click="step = 1" class="cr-btn">← Back</button>
            <div class="ml-auto flex items-center gap-2">
                {{-- The scan is part of the request, so it gates Review too. --}}
                <button type="button" x-show="step === 1" @click="goReview()" :disabled="! canReview"
                        class="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-40">
                    Review →
                </button>
                <button type="submit" x-show="step === 2" x-cloak class="cr-btn cr-btn-primary">File Request</button>
            </div>
        </div>
    </form>

    {{-- Detailed QR editor: a larger canvas to place & size the stamp precisely.
         Shares the same `qr` state as the inline preview, so edits sync live. --}}
    <div x-show="qrModal" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
         @pointerdown.self="qrModal = false">
        <div class="flex max-h-[calc(92vh/var(--ui-zoom))] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-hairline bg-paper shadow-2xl">
            <div class="dm-head">
                <p class="dm-section">Place &amp; size the QR code</p>
                <button type="button" @click="qrModal = false" aria-label="Close editor" class="dm-close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-[#f4f7f5] p-4 text-center">
                <div class="relative inline-block select-none leading-none" data-qr-stage>
                    <img :src="previewUrl" alt="Scanned document" class="block max-h-[calc(70vh/var(--ui-zoom))] w-auto rounded" @load="initQrPlacement($event.target)" draggable="false">
                    <div x-show="qr.ready" class="ff-qr-box"
                         :style="`left:${qr.x*100}%; top:${qr.y*100}%; width:${qr.size*100}%; aspect-ratio:1;`"
                         @pointerdown="startDragQr($event)">
                        @include('requests.partials.qr-glyph')
                    </div>
                </div>
            </div>
            <div class="dm-foot dm-actions">
                <span class="shrink-0 text-[length:var(--fs-caption)] font-semibold text-ink-soft">Size</span>
                <input type="range" min="0.12" max="0.40" step="0.005" x-model.number="qr.scaleShort" @input="applyQrScale()" class="h-1.5 flex-1 accent-green" aria-label="QR code size">
                <button type="button" @click="qrModal = false" class="cr-btn cr-btn-primary shrink-0">Done</button>
            </div>
        </div>
    </div>
</div>

@once
<script>
    /**
     * The PDF preview (pdf.js) is heavy, so it is fetched only when a PDF scan
     * is actually chosen rather than with the page.
     */
    window.loadPdfScanPreview = function () {
        if (typeof window.renderPdfPagePreview === 'function') {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.type = 'module';
            script.src = @js(\Illuminate\Support\Facades\Vite::asset('resources/js/pdf-scan-preview.js'));
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    };

    function requestForm() {
        return {
            step: 1,
            departments: @json($departmentsJson),
            // Per office: who can be named to receive a soft copy, fetched
            // when the office is picked and kept for the session of the form.
            recipients: {},
            recipientsUrl: @js(route('requests.recipients', ['department' => '__DEPT__'])),
            fileName: '',
            fileError: '',

            init() {
                this.$watch('form.first_department_id', () => this.loadRecipients());
                this.loadRecipients();
            },

            async loadRecipients() {
                const id = this.form.first_department_id;
                if (!id || this.recipients[id]) { return; }
                try {
                    const response = await fetch(this.recipientsUrl.replace('__DEPT__', id), { headers: { Accept: 'application/json' } });
                    if (response.ok) { this.recipients = { ...this.recipients, [id]: await response.json() }; }
                } catch (error) {
                    // Left empty: the picker then says no one is available,
                    // and a hard copy still files.
                }
            },
            previewUrl: null,
            qrModal: false,
            dragOver: false,
            // PDF scans: the chosen file stays in memory so any page can be
            // re-rendered as the placement preview.
            pdfFile: null,
            pdfPage: 1,
            pdfPageCount: 1,
            pdfRendering: false,
            pdfError: '',
            // Normalized QR placement on the scanned page (fractions of the
            // image). `scaleShort` is the QR side as a fraction of the short
            // edge (matches the server's 0.22 default). Stays unset when
            // there is no previewable scan → the default bottom-right is used.
            qr: { ready: false, x: 0.75, y: 0.75, size: 0.24, sizeH: 0.24, scaleShort: 0.22, imgW: 0, imgH: 0, userMoved: false },
            form: {
                first_department_id: @json(old('first_department_id', '')),
                purpose: @json(old('purpose', '')),
                has_hard_copy: @json((bool) old('has_hard_copy', true)),
                recipient_id: @json(old('recipient_id', '')),
            },

            get selectedDepartment() {
                return this.departments.find(d => String(d.id) === String(this.form.first_department_id)) ?? null;
            },

            get recipientOptions() {
                return this.recipients[this.form.first_department_id] ?? [];
            },

            get selectedRecipient() {
                return this.recipientOptions.find(p => String(p.id) === String(this.form.recipient_id)) ?? null;
            },

            /** One forward hop now: the department the filer chose. */
            get resolvedSteps() {
                const target = this.selectedDepartment;

                return target ? [{ department: target, action: 'Review and action' }] : [];
            },

            onFileChange(event) {
                const file = event.target.files[0];
                this.qr.ready = false;
                this.qr.userMoved = false;
                this.qr.scaleShort = 0.22;
                this.pdfFile = null;
                this.pdfPage = 1;
                this.pdfPageCount = 1;
                this.pdfError = '';
                this.fileError = '';
                if (!file) { this.fileName = ''; this.previewUrl = null; return; }

                // Only what the QR can be stamped onto (UploadRules::PAPER_SCAN_EXTENSIONS);
                // a dropped file skips the picker's filter, so check here too.
                if (!/\.(pdf|jpe?g|png|webp)$/i.test(file.name)) {
                    event.target.value = '';
                    this.fileName = '';
                    this.previewUrl = null;
                    this.fileError = 'That file can\'t carry the QR. Upload the signed paper as a PDF or a picture (JPG, PNG or WEBP) — save a Word file as PDF first.';

                    return;
                }

                this.fileName = file.name;

                if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) {
                    // PDFs get rasterised page-by-page so the same drag-and-size
                    // stage works; the stamp is burned in later by pdf-lib.
                    this.pdfFile = file;
                    this.previewUrl = null;
                    this.renderPdfPreview();

                    return;
                }

                this.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            },

            /** A file dropped on the upload area goes into the real input. */
            dropFile(event) {
                this.dragOver = false;
                const file = event.dataTransfer?.files?.[0];
                if (!file) { return; }
                const transfer = new DataTransfer();
                transfer.items.add(file);
                this.$refs.paperInput.files = transfer.files;
                this.onFileChange({ target: this.$refs.paperInput });
            },

            /** Rasterise the chosen PDF page into the placement preview. */
            async renderPdfPreview() {
                if (!this.pdfFile) { return; }

                this.pdfRendering = true;
                this.pdfError = '';
                try {
                    await window.loadPdfScanPreview();
                    const result = await window.renderPdfPagePreview(this.pdfFile, this.pdfPage);
                    this.pdfPageCount = result.pageCount;
                    this.pdfPage = result.page;
                    this.qr.ready = false;
                    this.previewUrl = result.dataUrl;
                } catch (error) {
                    console.error(error);
                    this.previewUrl = null;
                    this.pdfError = 'That PDF could not be previewed, so the QR will be stamped at the bottom-right of page 1.';
                } finally {
                    this.pdfRendering = false;
                }
            },

            goToPdfPage(page) {
                const target = Math.min(Math.max(page, 1), this.pdfPageCount);
                if (target === this.pdfPage) { return; }
                this.pdfPage = target;
                this.renderPdfPreview();
            },

            // Recompute the on-screen box from `scaleShort` so it lands exactly
            // where the server will burn it in (box = QR + white padding, i.e.
            // scaleShort * shortEdge * 1.12), then keep it within the page.
            recomputeQrBox() {
                const W = this.qr.imgW, H = this.qr.imgH;
                if (!W || !H) { return; }
                const boxPx = this.qr.scaleShort * Math.min(W, H) * 1.12;
                this.qr.size = boxPx / W;
                this.qr.sizeH = boxPx / H;
                this.qr.x = Math.max(0, Math.min(this.qr.x, 1 - this.qr.size));
                this.qr.y = Math.max(0, Math.min(this.qr.y, 1 - this.qr.sizeH));
            },

            // Called on each preview image load (inline + editor). Sizes the box
            // to the server geometry and, until the user moves it, parks it in
            // the bottom-right corner.
            initQrPlacement(img) {
                if (!img || !img.naturalWidth) { return; }
                this.qr.imgW = img.naturalWidth;
                this.qr.imgH = img.naturalHeight;
                this.recomputeQrBox();
                if (!this.qr.userMoved) {
                    const margin = 0.10 * this.qr.scaleShort * Math.min(this.qr.imgW, this.qr.imgH);
                    this.qr.x = Math.max(0, 1 - this.qr.size - margin / this.qr.imgW);
                    this.qr.y = Math.max(0, 1 - this.qr.sizeH - margin / this.qr.imgH);
                }
                this.qr.ready = true;
            },

            applyQrScale() {
                this.qr.userMoved = true;
                this.recomputeQrBox();
            },

            startDragQr(event) {
                event.preventDefault();
                const stage = event.target.closest('[data-qr-stage]');
                const img = stage?.querySelector('img');
                if (!img) { return; }
                const rect = img.getBoundingClientRect();
                const boxW = this.qr.size * rect.width;
                const boxH = this.qr.sizeH * rect.height;
                const startX = event.clientX, startY = event.clientY;
                const origX = this.qr.x * rect.width, origY = this.qr.y * rect.height;
                const move = (ev) => {
                    const nx = Math.max(0, Math.min(origX + (ev.clientX - startX), rect.width - boxW));
                    const ny = Math.max(0, Math.min(origY + (ev.clientY - startY), rect.height - boxH));
                    this.qr.x = nx / rect.width;
                    this.qr.y = ny / rect.height;
                    this.qr.userMoved = true;
                };
                const up = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },

            get canReview() {
                return Boolean(this.form.first_department_id) && this.form.purpose.trim() !== '' && this.fileName !== ''
                    && (this.form.has_hard_copy || this.selectedRecipient !== null);
            },

            goReview() {
                if (this.canReview) { this.step = 2; }
            },
        };
    }
</script>
@endonce
