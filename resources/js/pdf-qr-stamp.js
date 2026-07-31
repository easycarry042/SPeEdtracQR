/**
 * Burns the tracking QR into a PDF paper scan, in the browser.
 *
 * Raster scans are stamped server-side with GD, but PHP has no PDF toolchain on
 * an office server — so PDFs are stamped here with pdf-lib (the same library the
 * built-in PDF editor already uses) on the confirmation screen, right after the
 * request is filed and its QR exists. The stamped file is posted back and stored
 * as an extra attachment; the original scan is never modified.
 *
 * Geometry deliberately mirrors QrCodeService::stampQrOntoImage() so a PDF and an
 * image land the QR in the same spot for the same placement.
 */
import { PDFDocument, rgb } from 'pdf-lib';

const root = document.getElementById('pdfQrStamp');

if (root) {
    stamp(root).catch((error) => {
        console.error('QR stamping failed', error);
        setStatus(root, 'The QR could not be burned into the PDF. The original scan is safe — print the QR sticker instead.', true);
    });
}

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function setStatus(node, message, isError = false) {
    const status = node.querySelector('[data-stamp-status]');
    if (!status) return;
    status.textContent = message;
    status.className = isError
        ? 'mt-3 text-[12px] font-semibold text-status-red'
        : 'mt-3 text-[12px] text-ink-soft';
}

async function fetchBytes(url) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`Could not load ${url}`);

    return new Uint8Array(await response.arrayBuffer());
}

async function stamp(node) {
    setStatus(node, 'Stamping the QR onto the scanned PDF…');

    const [pdfBytes, qrBytes] = await Promise.all([
        fetchBytes(node.dataset.pdfUrl),
        fetchBytes(node.dataset.qrUrl),
    ]);

    const pdf = await PDFDocument.load(pdfBytes);
    const pageIndex = clamp(parseInt(node.dataset.page || '1', 10) || 1, 1, pdf.getPageCount()) - 1;
    const page = pdf.getPages()[pageIndex];
    const { width, height } = page.getSize();

    const qr = await pdf.embedPng(qrBytes);

    // ~22% of the short edge by default, clamped to the same 12%–40% band the
    // server enforces; the white pad keeps it scannable on busy paper.
    const fraction = clamp(parseFloat(node.dataset.size) || 0.22, 0.12, 0.40);
    const size = Math.max(72, Math.min(width, height) * fraction);
    const pad = size * 0.06;
    const box = size + pad * 2;
    const margin = size * 0.10;

    const hasPlacement = node.dataset.x !== '' && node.dataset.y !== '';
    const boxX = hasPlacement
        ? clamp(parseFloat(node.dataset.x) * width, 0, Math.max(0, width - box))
        : Math.max(0, width - box - margin);
    // Placements arrive as top-left fractions; PDF coordinates start bottom-left.
    const topOffset = hasPlacement
        ? clamp(parseFloat(node.dataset.y) * height, 0, Math.max(0, height - box))
        : margin;
    const boxY = Math.max(0, height - topOffset - box);

    page.drawRectangle({ x: boxX, y: boxY, width: box, height: box, color: rgb(1, 1, 1) });
    page.drawImage(qr, { x: boxX + pad, y: boxY + pad, width: size, height: size });

    const form = new FormData();
    form.append('pdf', new Blob([await pdf.save()], { type: 'application/pdf' }), 'qr-stamped.pdf');

    const response = await fetch(node.dataset.saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
        body: form,
    });

    if (!response.ok) throw new Error(`Save failed (${response.status})`);

    const saved = await response.json();
    const link = node.querySelector('[data-stamped-link]');
    if (link && saved.url) {
        link.href = saved.url;
        link.classList.remove('hidden');
    }
    setStatus(node, 'QR burned into the scanned PDF — the original is still on file.');
}
