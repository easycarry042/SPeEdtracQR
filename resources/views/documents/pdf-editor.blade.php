<x-app-layout>
    @push('head')
        @vite('resources/js/pdf-editor.js')
    @endpush

    <div class="mx-auto w-full max-w-5xl py-4"
         id="pdfEditor"
         data-file-url="{{ $attachment->authorizedUrl() }}"
         data-save-url="{{ route('attachments.edit.store', $attachment) }}"
         data-signature-url="{{ $hasSignature ? route('profile.signature.show') : '' }}">

        <div class="panel">
            <div class="ph">
                <h2>Edit &amp; sign document</h2>
                <span class="sub">{{ $document->tracking_number }} · saving keeps the original on file</span>
            </div>

            <div class="pb">
                @unless($hasSignature)
                    <div class="mb-4 rounded-[8px] border border-status-amber-wash bg-status-amber-wash px-4 py-3 text-[13px] text-status-amber" role="alert">
                        You have no registered e-signature yet, so only text can be placed.
                        <a href="{{ route('profile.edit') }}" class="font-semibold underline">Register your signature</a> to sign documents.
                    </div>
                @endunless

                {{-- Toolbar: pick what a click on the page places. --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="segchips">
                        <button type="button" data-tool="signature" class="on">Place signature</button>
                        <button type="button" data-tool="text">Add text</button>
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" id="pdfPrev" class="cr-btn cr-btn-sm">‹ Prev</button>
                        <span id="pdfPageLabel" class="text-sm text-ink-soft">Loading…</span>
                        <button type="button" id="pdfNext" class="cr-btn cr-btn-sm">Next ›</button>
                    </div>
                </div>

                <p id="pdfStatus" class="mt-3 text-sm text-ink-soft" role="status">Opening the document…</p>

                {{-- The rendered page, with the draggable placement layer on top. --}}
                <div class="mt-3 overflow-auto rounded-[10px] border border-hairline bg-[#f4f7f5] p-4">
                    <div class="relative mx-auto w-fit shadow-sm">
                        <canvas id="pdfCanvas" class="block bg-white"></canvas>
                        <div id="pdfOverlay" class="pdfe-overlay"></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('track.show', $document->tracking_number) }}" class="cr-btn cr-btn-sm">Cancel</a>
                    <button type="button" id="pdfSave" class="cr-btn cr-btn-primary" disabled>
                        Save signed copy
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
