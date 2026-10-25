'use client';

const TARGET_WIDTH = 1200;

export type PdfPreviewResult = {
  dataUrl: string;
  pageCount: number;
  page: number;
};

async function loadPdfjs() {
  const pdfjsLib = await import('pdfjs-dist');
  if (typeof window !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';
  }
  return pdfjsLib;
}

export async function renderPdfFilePage(
  file: File,
  pageNumber = 1,
): Promise<PdfPreviewResult> {
  const bytes = await file.arrayBuffer();
  return renderPdfBytesPage(bytes, pageNumber);
}

export async function renderPdfUrlPage(
  url: string,
  pageNumber = 1,
): Promise<PdfPreviewResult> {
  const response = await fetch(url, { credentials: 'same-origin' });
  if (!response.ok) {
    throw new Error('Could not load the PDF attachment.');
  }
  const bytes = await response.arrayBuffer();
  return renderPdfBytesPage(bytes, pageNumber);
}

async function renderPdfBytesPage(
  bytes: ArrayBuffer,
  pageNumber: number,
): Promise<PdfPreviewResult> {
  const pdfjsLib = await loadPdfjs();
  const doc = await pdfjsLib.getDocument({ data: bytes }).promise;

  const page = Math.min(Math.max(pageNumber, 1), doc.numPages);
  const pdfPage = await doc.getPage(page);

  const base = pdfPage.getViewport({ scale: 1 });
  const viewport = pdfPage.getViewport({
    scale: Math.min(3, TARGET_WIDTH / Math.max(1, base.width)),
  });

  const canvas = document.createElement('canvas');
  canvas.width = Math.round(viewport.width);
  canvas.height = Math.round(viewport.height);

  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('Canvas context unavailable.');
  }

  await pdfPage.render({ canvasContext: context, viewport }).promise;

  return {
    dataUrl: canvas.toDataURL('image/png'),
    pageCount: doc.numPages,
    page,
  };
}
