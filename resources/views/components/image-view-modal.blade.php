{{-- Full-screen image viewer. Any element with data-lightbox-src opens here instead of a new tab.
     Zoom with the +/− buttons or mouse wheel; drag to pan while zoomed. Sits above the
     New Submission modal (z-[100]) so attachments on the created-card are viewable in place. --}}
<div id="imageLightbox" class="fixed inset-0 z-[120] hidden select-none" role="dialog" aria-modal="true" aria-label="Image viewer">
    <div class="absolute inset-0 bg-black/85"></div>

    <div id="lightboxStage" class="absolute inset-0 flex items-center justify-center overflow-hidden">
        <img id="lightboxImage" src="" alt="Document attachment" class="max-h-[88vh] max-w-[92vw] rounded-lg shadow-2xl" draggable="false">
    </div>

    <button type="button" id="lightboxClose"
            class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
            aria-label="Close image viewer">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
    </button>

    <div class="absolute bottom-5 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1 rounded-full bg-white/10 px-2 py-1.5 backdrop-blur">
        <button type="button" data-lightbox-zoom="out"
                class="flex h-9 w-9 items-center justify-center rounded-full text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                aria-label="Zoom out">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
        </button>
        <span id="lightboxZoomLabel" class="w-14 text-center text-sm font-semibold text-white">100%</span>
        <button type="button" data-lightbox-zoom="in"
                class="flex h-9 w-9 items-center justify-center rounded-full text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                aria-label="Zoom in">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
        </button>
        <button type="button" data-lightbox-zoom="reset"
                class="ml-1 rounded-full px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-white/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
            Reset
        </button>
    </div>
</div>

<script>
    (function () {
        const lightbox = document.getElementById('imageLightbox');
        const stage = document.getElementById('lightboxStage');
        const img = document.getElementById('lightboxImage');
        const zoomLabel = document.getElementById('lightboxZoomLabel');
        if (!lightbox || !stage || !img) return;

        const MIN_SCALE = 1;
        const MAX_SCALE = 8;
        let scale = 1, tx = 0, ty = 0;
        let dragging = false, moved = false;
        let startX = 0, startY = 0, startTx = 0, startTy = 0;
        let bodyWasLocked = false;

        function apply() {
            img.style.transform = 'translate(' + tx + 'px, ' + ty + 'px) scale(' + scale + ')';
            if (zoomLabel) zoomLabel.textContent = Math.round(scale * 100) + '%';
            img.style.cursor = scale > 1 ? (dragging ? 'grabbing' : 'grab') : 'default';
        }

        function setScale(next) {
            scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, next));
            if (scale === MIN_SCALE) { tx = 0; ty = 0; }
            apply();
        }

        function openLightbox(src) {
            img.src = src;
            scale = 1; tx = 0; ty = 0;
            apply();
            bodyWasLocked = document.body.classList.contains('overflow-hidden');
            document.body.classList.add('overflow-hidden');
            lightbox.classList.remove('hidden');
        }

        function closeLightbox() {
            lightbox.classList.add('hidden');
            img.src = '';
            if (!bodyWasLocked) document.body.classList.remove('overflow-hidden');
        }

        // Delegated so it also works for content injected later (e.g. the created-document card in the modal).
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-lightbox-src]');
            if (!trigger) return;
            e.preventDefault();
            openLightbox(trigger.dataset.lightboxSrc);
        });

        lightbox.addEventListener('click', function (e) {
            if (e.target.closest('#lightboxClose')) { closeLightbox(); return; }
            const zoomBtn = e.target.closest('[data-lightbox-zoom]');
            if (zoomBtn) {
                const action = zoomBtn.dataset.lightboxZoom;
                if (action === 'in') setScale(scale * 1.5);
                else if (action === 'out') setScale(scale / 1.5);
                else setScale(1);
                return;
            }
            // Click on the empty area (not a drag that ended) closes the viewer.
            if (e.target === stage && !moved) closeLightbox();
        });

        stage.addEventListener('pointerdown', function (e) {
            moved = false;
            if (scale <= 1) return;
            dragging = true;
            startX = e.clientX; startY = e.clientY;
            startTx = tx; startTy = ty;
            stage.setPointerCapture(e.pointerId);
            apply();
            e.preventDefault();
        });

        stage.addEventListener('pointermove', function (e) {
            if (!dragging) return;
            tx = startTx + (e.clientX - startX);
            ty = startTy + (e.clientY - startY);
            if (Math.abs(e.clientX - startX) + Math.abs(e.clientY - startY) > 4) moved = true;
            apply();
        });

        ['pointerup', 'pointercancel'].forEach(function (ev) {
            stage.addEventListener(ev, function () {
                dragging = false;
                apply();
            });
        });

        stage.addEventListener('wheel', function (e) {
            e.preventDefault();
            setScale(scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15));
        }, { passive: false });

        // Capture phase so Escape closes only the viewer, not also the modal behind it.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lightbox.classList.contains('hidden')) {
                e.stopImmediatePropagation();
                e.preventDefault();
                closeLightbox();
            }
        }, true);
    })();
</script>
