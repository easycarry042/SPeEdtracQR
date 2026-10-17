/**
 * Built-in PDF editor.
 *
 * Renders the attached PDF with pdf.js, lets staff drop their registered
 * e-signature and typed notes onto any page, then writes a NEW PDF with pdf-lib
 * and posts it back. The original file is never modified — the server stores the
 * result as another attachment.
 *
 * Everything runs in the browser, so no PDF toolchain is needed on the office
 * server, and the private file is fetched through the same authorized route the
 * rest of the app uses.
 */
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { PDFDocument, StandardFonts, rgb } from 'pdf-lib';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

/** Placements are stored in PDF-space fractions so zoom never shifts them. */
const state = {
    pdfBytes: null,
    doc: null,
    pageIndex: 0,
    pageCount: 0,
    scale: 1,
    /** @type {Array<{page:number, type:'signature'|'text', x:number, y:number, w:number, h:number, text?:string}>} */
    items: [],
    signatureDataUrl: null,
    selected: null,
    tool: 'signature',
};

const els = {};

function $(id) {
    return document.getElementById(id);
}

function setStatus(message, isError = false) {
    els.status.textContent = message || '';
    els.status.className = isError
        ? 'text-sm font-semibold text-status-red'
        : 'text-sm text-ink-soft';
}

async function loadPdf(url) {
    const response = await fetch(url, { headers: { Accept: 'application/pdf' } });
    if (!response.ok) throw new Error('Could not load the document.');

    state.pdfBytes = await response.arrayBuffer();
    // pdf.js takes ownership of the buffer it reads, so hand it a copy and keep
    // the pristine bytes for pdf-lib at save time.
    state.doc = await pdfjsLib.getDocument({ data: state.pdfBytes.slice(0) }).promise;
    state.pageCount = state.doc.numPages;
    await renderPage();
}

async function renderPage() {
    const page = await state.doc.getPage(state.pageIndex + 1);
    const viewport = page.getViewport({ scale: state.scale });

    els.canvas.width = viewport.width;
    els.canvas.height = viewport.height;
    els.overlay.style.width = `${viewport.width}px`;
    els.overlay.style.height = `${viewport.height}px`;

    await page.render({ canvasContext: els.canvas.getContext('2d'), viewport }).promise;

    els.pageLabel.textContent = `Page ${state.pageIndex + 1} of ${state.pageCount}`;
    els.prev.disabled = state.pageIndex === 0;
    els.next.disabled = state.pageIndex >= state.pageCount - 1;
    drawItems();
}

/** Re-draw the draggable boxes for the current page. */
function drawItems() {
    els.overlay.querySelectorAll('[data-item]').forEach((node) => node.remove());

    state.items.forEach((item, index) => {
        if (item.page !== state.pageIndex) return;

        const box = document.createElement('div');
        box.dataset.item = String(index);
        box.className = 'pdfe-item' + (state.selected === index ? ' is-selected' : '');
        box.style.left = `${item.x * els.overlay.clientWidth}px`;
        box.style.top = `${item.y * els.overlay.clientHeight}px`;
        box.style.width = `${item.w * els.overlay.clientWidth}px`;
        box.style.height = `${item.h * els.overlay.clientHeight}px`;

        if (item.type === 'signature' && state.signatureDataUrl) {
            const img = document.createElement('img');
            img.src = state.signatureDataUrl;
            img.alt = 'Your e-signature';
            img.className = 'pdfe-signature';
            box.appendChild(img);
        } else if (item.type === 'text') {
            const span = document.createElement('span');
            span.className = 'pdfe-text';
            span.textContent = item.text || '';
            box.appendChild(span);
        }

        const handle = document.createElement('span');
        handle.className = 'pdfe-handle';
        handle.dataset.resize = String(index);
        box.appendChild(handle);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'pdfe-remove';
        remove.title = 'Remove';
        remove.textContent = '×';
        remove.dataset.remove = String(index);
        box.appendChild(remove);

        els.overlay.appendChild(box);
    });

    els.saveBtn.disabled = state.items.length === 0;
}

function addItem(fractionX, fractionY) {
    if (state.tool === 'signature') {
        if (!state.signatureDataUrl) {
            setStatus('Register your e-signature on your profile first.', true);

            return;
        }
        state.items.push({ page: state.pageIndex, type: 'signature', x: fractionX, y: fractionY, w: 0.22, h: 0.07 });
    } else {
        const text = window.prompt('Text to place on the document:');
        if (!text) return;
        state.items.push({ page: state.pageIndex, type: 'text', x: fractionX, y: fractionY, w: 0.3, h: 0.04, text });
    }

    state.selected = state.items.length - 1;
    drawItems();
    setStatus('Drag to position, use the corner to resize.');
}

function bindOverlayInteraction() {
    let drag = null;

    els.overlay.addEventListener('pointerdown', (event) => {
        const removeTarget = event.target.closest('[data-remove]');
        if (removeTarget) {
            state.items.splice(Number(removeTarget.dataset.remove), 1);
            state.selected = null;
            drawItems();

            return;
        }

        const resizeTarget = event.target.closest('[data-resize]');
        const itemTarget = event.target.closest('[data-item]');

        if (resizeTarget) {
            drag = { mode: 'resize', index: Number(resizeTarget.dataset.resize) };
        } else if (itemTarget) {
            const index = Number(itemTarget.dataset.item);
            const rect = els.overlay.getBoundingClientRect();
            const item = state.items[index];
            drag = {
                mode: 'move',
                index,
                grabX: (event.clientX - rect.left) / rect.width - item.x,
                grabY: (event.clientY - rect.top) / rect.height - item.y,
            };
            state.selected = index;
            drawItems();
        } else {
            // Empty space: drop a new item where the click landed.
            const rect = els.overlay.getBoundingClientRect();
            addItem((event.clientX - rect.left) / rect.width, (event.clientY - rect.top) / rect.height);

            return;
        }

        els.overlay.setPointerCapture(event.pointerId);
    });

    els.overlay.addEventListener('pointermove', (event) => {
        if (!drag) return;

        const rect = els.overlay.getBoundingClientRect();
        const item = state.items[drag.index];
        if (!item) return;

        const px = (event.clientX - rect.left) / rect.width;
        const py = (event.clientY - rect.top) / rect.height;

        if (drag.mode === 'move') {
            item.x = Math.min(Math.max(px - drag.grabX, 0), 1 - item.w);
            item.y = Math.min(Math.max(py - drag.grabY, 0), 1 - item.h);
        } else {
            item.w = Math.min(Math.max(px - item.x, 0.05), 1 - item.x);
            item.h = Math.min(Math.max(py - item.y, 0.02), 1 - item.y);
        }

        drawItems();
    });

    ['pointerup', 'pointercancel'].forEach((type) => {
        els.overlay.addEventListener(type, () => { drag = null; });
    });
}

/** Write the placements into a new PDF and post it back. */
async function save() {
    els.saveBtn.disabled = true;
    setStatus('Saving…');

    try {
        const pdf = await PDFDocument.load(state.pdfBytes.slice(0));
        const font = await pdf.embedFont(StandardFonts.Helvetica);

        let signatureImage = null;
        if (state.signatureDataUrl && state.items.some((item) => item.type === 'signature')) {
            const bytes = await (await fetch(state.signatureDataUrl)).arrayBuffer();
            signatureImage = await pdf.embedPng(bytes);
        }

        for (const item of state.items) {
            const page = pdf.getPages()[item.page];
            if (!page) continue;

            const { width, height } = page.getSize();
            const x = item.x * width;
            const boxHeight = item.h * height;
            // PDF space has its origin bottom-left; the overlay measures from the top.
            const y = height - (item.y * height) - boxHeight;

            if (item.type === 'signature' && signatureImage) {
                page.drawImage(signatureImage, { x, y, width: item.w * width, height: boxHeight });
            } else if (item.type === 'text') {
                const size = Math.max(8, boxHeight * 0.8);
                page.drawText(item.text || '', {
                    x,
                    y: y + (boxHeight - size) / 2,
                    size,
                    font,
                    color: rgb(0.07, 0.13, 0.1),
                });
            }
        }

        const bytes = await pdf.save();
        const body = new FormData();
        body.append('pdf', new Blob([bytes], { type: 'application/pdf' }), 'edited.pdf');

        const response = await fetch(els.root.dataset.saveUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                Accept: 'application/json',
            },
            body,
        });

        const data = await response.json();

        if (!response.ok) {
            setStatus(data.message || 'Could not save the edited document.', true);
            els.saveBtn.disabled = false;

            return;
        }

        setStatus(data.message);
        window.location.href = data.redirect;
    } catch (error) {
        setStatus('Could not save the edited document. Please try again.', true);
        els.saveBtn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    els.root = $('pdfEditor');
    if (!els.root) return;

    Object.assign(els, {
        canvas: $('pdfCanvas'),
        overlay: $('pdfOverlay'),
        pageLabel: $('pdfPageLabel'),
        prev: $('pdfPrev'),
        next: $('pdfNext'),
        saveBtn: $('pdfSave'),
        status: $('pdfStatus'),
    });

    els.prev.addEventListener('click', async () => {
        if (state.pageIndex > 0) { state.pageIndex -= 1; await renderPage(); }
    });
    els.next.addEventListener('click', async () => {
        if (state.pageIndex < state.pageCount - 1) { state.pageIndex += 1; await renderPage(); }
    });
    els.saveBtn.addEventListener('click', save);

    document.querySelectorAll('[data-tool]').forEach((button) => {
        button.addEventListener('click', () => {
            state.tool = button.dataset.tool;
            document.querySelectorAll('[data-tool]').forEach((b) => b.classList.toggle('on', b === button));
            setStatus(state.tool === 'signature'
                ? 'Click the page to place your signature.'
                : 'Click the page to add text.');
        });
    });

    // The signature is private, so it is fetched through its authorized route and
    // kept as a data URL for both the preview and the embed at save time.
    const signatureUrl = els.root.dataset.signatureUrl;
    if (signatureUrl) {
        try {
            const response = await fetch(signatureUrl);
            if (response.ok) {
                const blob = await response.blob();
                state.signatureDataUrl = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });
            }
        } catch (error) {
            state.signatureDataUrl = null;
        }
    }

    bindOverlayInteraction();

    try {
        await loadPdf(els.root.dataset.fileUrl);
        setStatus('Click the page to place your signature.');
    } catch (error) {
        setStatus('Could not open this document in the editor.', true);
    }
});
