<<<<<<< HEAD
<x-app-layout>
    {{-- The filing form on its own page — the same form the Internal page opens
         in a modal (requests/partials/file-form). Kept for direct links and as
         the no-JavaScript route from the "+" button. --}}
    <div class="page-shell">
        <div class="dm-card mx-auto w-full max-w-[940px]">
            @include('requests.partials.file-form')
=======
<x-app-layout title="File Internal Request">
    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-green-deep">File Internal Request</h1>
    </x-slot>

    {{-- Lets a PDF scan be previewed (and QR-placed) exactly like an image one. --}}
    @push('head')
        @vite('resources/js/pdf-scan-preview.js')
    @endpush

    <div class="page-shell" x-data="requestForm()">

        {{-- Step indicator --}}
        <nav class="flex items-center gap-2 text-[13px]" aria-label="Progress">
            <template x-for="(label, i) in ['Details', 'Review']" :key="i">
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
                    <span x-show="i < 1" class="h-px w-6 bg-hairline-strong"></span>
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
                        If you attached a scanned document, please re-attach it — it was not kept through the error.
                    </p>
                </div>
            @endif

            {{-- ============ STEP 1: Details ============ --}}
            <section x-show="step === 1" class="panel">
                <div class="ph">
                    <h2>Request details</h2>
                    <span class="sub">Filing on behalf of {{ $department->name }} ({{ $department->code }})</span>
                </div>

                <div class="grid grid-cols-1 gap-5 p-4 lg:grid-cols-2 lg:p-6">
                    <div class="space-y-5">
                        <div>
                            <label for="route-select" class="block text-[13px] font-semibold text-ink">First department route <span class="text-status-red">*</span></label>
                            <select id="route-select" name="first_department_id" x-model="form.first_department_id" required
                                    class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('first_department_id') border-status-red @enderror">
                                <option value="">Select a department…</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d->id }}" @selected(old('first_department_id') == $d->id)>{{ $d->name }} ({{ $d->code }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[12px] text-ink-soft">The office this request goes to after your own department endorses it.</p>
                            @error('first_department_id')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="purpose" class="block text-[13px] font-semibold text-ink">What is being requested? <span class="text-status-red">*</span></label>
                            <input id="purpose" type="text" name="purpose" x-model="form.purpose" required maxlength="255"
                                   placeholder="e.g. 10 monoblock chairs and a flower vase for the lobby"
                                   class="mt-1 w-full rounded-[8px] border border-hairline-strong bg-white px-3 py-2 text-[13px] text-ink transition focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25 @error('purpose') border-status-red @enderror">
                            @error('purpose')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-[13px] font-semibold text-ink">Scanned document <span class="text-status-red">*</span></label>
                            <p class="mt-0.5 text-[12px] text-ink-soft">Attach the scanned copy of the signed paper request (from your office scanner or printer).</p>
                            <label class="mt-2 flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-[10px] border-2 border-dashed border-hairline-strong bg-[#f4f7f5] p-4 text-center transition hover:border-green focus-within:border-green focus-within:ring-2 focus-within:ring-green/25"
                                   :class="fileName ? 'border-green bg-green-wash' : ''">
                                <template x-if="previewUrl">
                                    <img :src="previewUrl" alt="Preview of the scanned document" class="max-h-40 rounded-[8px] border border-hairline">
                                </template>
                                <template x-if="!previewUrl">
                                    <div>
                                        <svg class="mx-auto h-8 w-8 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                        <p class="mt-1.5 text-[13px] font-semibold text-ink">Click or press Enter to attach the scan</p>
                                        <p class="text-[12px] text-ink-soft">JPG, PNG, WEBP or PDF · up to 10&nbsp;MB · required</p>
                                    </div>
                                </template>
                                <span x-show="fileName" x-text="fileName" class="max-w-full truncate text-[12px] font-medium text-green-deep"></span>
                                {{-- sr-only (not hidden) keeps the input keyboard-focusable and Tab-reachable. --}}
                                <input type="file" name="paper_scan" accept="{{ \App\Support\UploadRules::accept() }}"
                                       class="sr-only" x-ref="paperInput" @change="onFileChange($event)">
                            </label>
                            @error('paper_scan')<p class="mt-1 text-[12px] text-status-red">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Live chain preview --}}
                    <div class="rounded-[10px] border border-hairline bg-green-wash/40 p-5">
                        <h3 class="text-[13px] font-bold text-green-deep">Endorsement chain preview</h3>
                        <p class="mt-0.5 text-[12px] text-ink-soft">Where this request goes: your own department endorses it, then the office you pick.</p>

                        <p x-show="!selectedDepartment" class="mt-4 text-[13px] text-ink-soft">Select a department to preview the chain.</p>

                        <ol x-show="selectedDepartment" class="mt-4 space-y-0">
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

                <div class="flex items-center justify-end gap-3 border-t border-hairline px-4 py-4 lg:px-6">
                    {{-- The scan is part of the request, so it gates Review too. --}}
                    <button type="button" @click="goReview()" :disabled="! canReview"
                            class="cr-btn cr-btn-primary"
                            :class="! canReview ? 'cursor-not-allowed opacity-40' : ''">
                        Review →
                    </button>
                </div>
            </section>

            {{-- ============ STEP 2: Review + submit ============ --}}
            <section x-show="step === 2" x-cloak class="panel">
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
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">First department</dt>
                        <dd class="mt-0.5 text-[14px] font-semibold text-ink"
                            x-text="selectedDepartment ? `${selectedDepartment.name} (${selectedDepartment.code})` : '—'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Request</dt>
                        <dd class="mt-0.5 text-[14px] text-ink" x-text="form.purpose"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">Scanned document</dt>
                        <dd class="mt-0.5 text-[14px] text-ink" x-text="fileName || 'None attached'"></dd>
                    </div>

                    {{-- Drag the QR to where it should be stamped on the paper.
                         Works for image scans and for PDFs (page by page). --}}
                    <div class="sm:col-span-2" x-show="previewUrl || pdfRendering || pdfError" x-cloak>
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">QR placement on the page</dt>
                        <dd class="mt-1.5">
                            <p class="mb-2 text-[12px] text-ink-soft">Drag the QR square onto a clear area of the paper, and use the slider to resize it — it will be stamped exactly there. Defaults to the bottom-right corner.</p>

                            <p x-show="pdfRendering" class="mb-2 text-[12px] font-semibold text-ink-soft">Opening the PDF…</p>
                            <p x-show="pdfError" x-text="pdfError" class="mb-2 text-[12px] font-semibold text-status-amber"></p>

                            {{-- Multi-page PDFs: choose which page carries the stamp. --}}
                            <div x-show="pdfFile && pdfPageCount > 1" class="mb-2 flex items-center gap-2">
                                <button type="button" @click="goToPdfPage(pdfPage - 1)" :disabled="pdfPage <= 1" class="cr-btn cr-btn-sm disabled:opacity-40">‹ Prev</button>
                                <span class="text-[12px] font-semibold text-ink-soft" x-text="`Page ${pdfPage} of ${pdfPageCount}`"></span>
                                <button type="button" @click="goToPdfPage(pdfPage + 1)" :disabled="pdfPage >= pdfPageCount" class="cr-btn cr-btn-sm disabled:opacity-40">Next ›</button>
                            </div>
                            <div class="inline-block select-none rounded-[8px] border border-hairline bg-[#f4f7f5] p-2">
                                <div class="relative inline-block leading-none" data-qr-stage>
                                    <img :src="previewUrl" alt="Scanned document" class="block max-h-[420px] w-auto rounded-[4px]" @load="initQrPlacement($event.target)" draggable="false">
                                    <div x-show="qr.ready"
                                         class="absolute cursor-move rounded-[3px] border-2 border-green bg-white/85 shadow-sm"
                                         :style="`left:${qr.x*100}%; top:${qr.y*100}%; width:${qr.size*100}%; aspect-ratio:1;`"
                                         @pointerdown="startDragQr($event)">
                                        <div class="flex h-full w-full items-center justify-center">
                                            <svg class="h-1/2 w-1/2 text-green" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM13.5 14.625h1.5v1.5h-1.5v-1.5ZM16.5 14.625h1.5v1.5h-1.5v-1.5ZM19.5 14.625h.75v1.5h-.75v-1.5ZM13.5 17.625h1.5v1.5h-1.5v-1.5ZM19.5 17.625h.75v1.5h-.75v-1.5Z"/></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 flex max-w-[420px] items-center gap-3" x-show="qr.ready" x-cloak>
                                <span class="text-[11px] font-semibold text-ink-soft">Size</span>
                                <input type="range" min="0.12" max="0.40" step="0.005" x-model.number="qr.scaleShort" @input="applyQrScale()" class="h-1.5 flex-1 accent-green" aria-label="QR code size">
                                <button type="button" @click="qrModal = true" class="cr-btn cr-btn-sm shrink-0">
                                    <svg class="mr-1 h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0-5 5M4 16v4m0 0h4m-4 0 5-5m11 5-5-5m5 5v-4m0 4h-4"/></svg>
                                    Adjust in detail
                                </button>
                            </div>

                            <input type="hidden" name="qr_x" :value="qr.ready ? qr.x.toFixed(4) : ''">
                            <input type="hidden" name="qr_y" :value="qr.ready ? qr.y.toFixed(4) : ''">
                            <input type="hidden" name="qr_size" :value="qr.ready ? qr.scaleShort.toFixed(4) : ''">
                            <input type="hidden" name="qr_page" :value="pdfFile ? pdfPage : ''">
                        </dd>
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
                    <button type="button" @click="step = 1" class="cr-btn">← Back</button>
                    <button type="submit" class="cr-btn cr-btn-primary px-6">File Request</button>
                </div>
            </section>
        </form>

        {{-- Detailed QR editor: a larger canvas to place & size the stamp precisely.
             Shares the same `qr` state as the inline preview, so edits sync live. --}}
        <div x-show="qrModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             @keydown.escape.window="qrModal = false" @pointerdown.self="qrModal = false">
            <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-hairline bg-paper shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-hairline px-4 py-3">
                    <h2 class="text-sm font-semibold text-green-deep">Place &amp; size the QR code</h2>
                    <button type="button" @click="qrModal = false" aria-label="Close editor"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-ink-soft transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="min-h-0 flex-1 overflow-auto bg-[#f4f7f5] p-4 text-center">
                    <div class="relative inline-block select-none leading-none" data-qr-stage>
                        <img :src="previewUrl" alt="Scanned document" class="block max-h-[70vh] w-auto rounded" @load="initQrPlacement($event.target)" draggable="false">
                        <div x-show="qr.ready"
                             class="absolute cursor-move rounded-[3px] border-2 border-green bg-white/85 shadow"
                             :style="`left:${qr.x*100}%; top:${qr.y*100}%; width:${qr.size*100}%; aspect-ratio:1;`"
                             @pointerdown="startDragQr($event)">
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-1/2 w-1/2 text-green" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM13.5 14.625h1.5v1.5h-1.5v-1.5ZM16.5 14.625h1.5v1.5h-1.5v-1.5ZM19.5 14.625h.75v1.5h-.75v-1.5ZM13.5 17.625h1.5v1.5h-1.5v-1.5ZM19.5 17.625h.75v1.5h-.75v-1.5Z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 border-t border-hairline px-4 py-3">
                    <span class="shrink-0 text-xs font-semibold text-ink-soft">Size</span>
                    <input type="range" min="0.12" max="0.40" step="0.005" x-model.number="qr.scaleShort" @input="applyQrScale()" class="h-1.5 flex-1 accent-green" aria-label="QR code size">
                    <button type="button" @click="qrModal = false" class="cr-btn cr-btn-primary shrink-0 px-5">Done</button>
                </div>
            </div>
>>>>>>> 445734fe6c2ab99dcac3772ebcf201eb1141dd91
        </div>
    </div>
</x-app-layout>
