'use client';

const TARGET_WIDTH = 1800;

export type PdfPreviewResult = {
  dataUrl: string;
  pageCount: number;
  page: number;
};

export type PdfCanvasResult = {
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

/**
 * Fetches a PDF URL and renders the requested page directly onto a provided
 * HTML `<canvas>` element with an opaque white backdrop and abort support.
 */
export async function renderPdfUrlToCanvas(
  url: string,
  canvas: HTMLCanvasElement,
  pageNumber = 1,
  signal?: AbortSignal,
): Promise<PdfCanvasResult> {
  if (signal?.aborted) {
    throw new DOMException('Aborted', 'AbortError');
  }

  const response = await fetch(url, { credentials: 'same-origin', signal });
  if (!response.ok) {
    throw new Error('Could not load the PDF attachment.');
  }

  const bytes = await response.arrayBuffer();
  return renderPdfBytesToCanvas(bytes, canvas, pageNumber, signal);
}

/**
 * Renders an already-fetched PDF `ArrayBuffer` page directly onto a provided
 * HTML `<canvas>` element with an opaque white backdrop and abort support.
 */
export async function renderPdfBytesToCanvas(
  bytes: ArrayBuffer,
  canvas: HTMLCanvasElement,
  pageNumber = 1,
  signal?: AbortSignal,
): Promise<PdfCanvasResult> {
  if (bytes.byteLength === 0) {
    throw new Error('The attached PDF is empty.');
  }

  if (signal?.aborted) {
    throw new DOMException('Aborted', 'AbortError');
  }

  const pdfjsLib = await loadPdfjs();
  const loadingTask = pdfjsLib.getDocument({ data: new Uint8Array(bytes) });

  let renderTask: { promise: Promise<void>; cancel: () => void } | null = null;
  let doc: Awaited<typeof loadingTask.promise> | null = null;

  const onAbort = () => {
    try {
      renderTask?.cancel();
    } catch {
      // Ignore cancellation errors
    }
    void loadingTask.destroy().catch(() => {});
  };

  signal?.addEventListener('abort', onAbort, { once: true });

  try {
    doc = await loadingTask.promise;

    if (signal?.aborted) {
      throw new DOMException('Aborted', 'AbortError');
    }

    const page = Math.min(Math.max(pageNumber, 1), doc.numPages);
    const pdfPage = await doc.getPage(page);

    const base = pdfPage.getViewport({ scale: 1 });
    const viewport = pdfPage.getViewport({
      scale: Math.min(3, TARGET_WIDTH / Math.max(1, base.width)),
    });

    canvas.width = Math.max(1, Math.round(viewport.width));
    canvas.height = Math.max(1, Math.round(viewport.height));

    const context = canvas.getContext('2d');
    if (!context) {
      throw new Error('Canvas context unavailable.');
    }

    // Ensure transparent PDF backgrounds paint visibly on an opaque white sheet.
    context.save();
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.restore();

    renderTask = pdfPage.render({ canvasContext: context, viewport });
    await renderTask.promise;

    return { pageCount: doc.numPages, page };
  } finally {
    signal?.removeEventListener('abort', onAbort);
    if (doc) {
      await doc.destroy().catch(() => {});
    } else {
      await loadingTask.destroy().catch(() => {});
    }
  }
}

async function renderPdfBytesPage(
  bytes: ArrayBuffer,
  pageNumber: number,
): Promise<PdfPreviewResult> {
  const pdfjsLib = await loadPdfjs();
  const doc = await pdfjsLib.getDocument({ data: new Uint8Array(bytes) }).promise;

  try {
    const page = Math.min(Math.max(pageNumber, 1), doc.numPages);
    const pdfPage = await doc.getPage(page);

    const base = pdfPage.getViewport({ scale: 1 });
    const viewport = pdfPage.getViewport({
      scale: Math.min(3, TARGET_WIDTH / Math.max(1, base.width)),
    });

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(viewport.width));
    canvas.height = Math.max(1, Math.round(viewport.height));

    const context = canvas.getContext('2d');
    if (!context) {
      throw new Error('Canvas context unavailable.');
    }

    context.save();
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.restore();

    await pdfPage.render({ canvasContext: context, viewport }).promise;

    return {
      dataUrl: canvas.toDataURL('image/png'),
      pageCount: doc.numPages,
      page,
    };
  } finally {
    await doc.destroy().catch(() => {});
  }
}
