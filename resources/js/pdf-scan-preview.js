/**
 * First-page preview for a scanned PDF picked in the internal request wizard.
 *
 * Offices scan the signed paper straight to PDF as often as to JPG, so the QR
 * placement stage must work for both. pdf.js rasterises the chosen page in the
 * browser, and the resulting PNG data URL feeds the same drag-and-size stage the
 * image path already uses — nothing about the placement UI has to change.
 */
import * as pdfjsLib from 'pdfjs-dist';
import pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorker;

/** Preview width in CSS pixels — enough detail to aim the QR, cheap to render. */
const TARGET_WIDTH = 1000;

/**
 * @param {File} file
 * @param {number} pageNumber 1-based
 * @returns {Promise<{dataUrl: string, pageCount: number, page: number}>}
 */
window.renderPdfPagePreview = async function renderPdfPagePreview(file, pageNumber = 1) {
    const bytes = await file.arrayBuffer();
    const doc = await pdfjsLib.getDocument({ data: bytes }).promise;

    const page = Math.min(Math.max(pageNumber, 1), doc.numPages);
    const pdfPage = await doc.getPage(page);

    const base = pdfPage.getViewport({ scale: 1 });
    const viewport = pdfPage.getViewport({ scale: Math.min(3, TARGET_WIDTH / base.width) });

    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

    return { dataUrl: canvas.toDataURL('image/png'), pageCount: doc.numPages, page };
};

window.dispatchEvent(new CustomEvent('pdf-preview-ready'));
