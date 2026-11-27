'use client';

import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { renderPdfBytesToCanvas } from '@/lib/client/pdf-preview';

export type PreviewFileTarget = {
  url: string;
  downloadUrl?: string;
  title: string;
  ext: string;
};

export type PreviewKind = 'image' | 'pdf' | 'docx' | 'text' | 'csv' | 'unsupported';

const PREVIEW_IMAGE_EXTS = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

/** Classify an extension into the in-screen preview renderer to use. */
export function previewKindFor(ext: string): PreviewKind {
  const normalized = ext.trim().replace(/^\./, '').toLowerCase();

  if (PREVIEW_IMAGE_EXTS.has(normalized)) return 'image';
  if (normalized === 'pdf') return 'pdf';
  if (normalized === 'docx') return 'docx';
  if (normalized === 'txt') return 'text';
  if (normalized === 'csv') return 'csv';

  return 'unsupported';
}

/** Minimal RFC-4180 CSV parser for tabular in-screen preview. */
export function parseCsvRows(input: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let inQuotes = false;

  for (let i = 0; i < input.length; i += 1) {
    const ch = input[i];

    if (inQuotes) {
      if (ch === '"') {
        if (input[i + 1] === '"') {
          cell += '"';
          i += 1;
        } else {
          inQuotes = false;
        }
      } else {
        cell += ch;
      }
      continue;
    }

    if (ch === '"') {
      inQuotes = true;
    } else if (ch === ',') {
      row.push(cell);
      cell = '';
    } else if (ch === '\r') {
      if (input[i + 1] === '\n') {
        i += 1;
      }
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else if (ch === '\n') {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else {
      cell += ch;
    }
  }

  if (cell.length > 0 || row.length > 0) {
    row.push(cell);
    rows.push(row);
  }

  return rows;
}

/**
 * In-screen document and image viewer modal.
 *
 * Clicking "View" on a requirement or attachment opens this overlay instead of
 * letting the browser navigate straight to a binary download (such as `.docx`).
 */
export function FilePreviewModal({
  file,
  onClose,
}: {
  file: PreviewFileTarget | null;
  onClose: () => void;
}) {
  const [mounted, setMounted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [retrySeed, setRetrySeed] = useState(0);

  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [pdfBytes, setPdfBytes] = useState<ArrayBuffer | null>(null);
  const [pdfPage, setPdfPage] = useState(1);
  const [pdfPageCount, setPdfPageCount] = useState(1);
  const [pdfRendering, setPdfRendering] = useState(false);
  const [textContent, setTextContent] = useState<string | null>(null);
  const [csvRows, setCsvRows] = useState<string[][] | null>(null);
  const [unsupportedNotice, setUnsupportedNotice] = useState(false);

  const pdfCanvasRef = useRef<HTMLCanvasElement | null>(null);
  const docxHostRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    setMounted(true);
  }, []);

  // Capture Escape before parent modals (like ReviewModal) so only this viewer closes.
  useEffect(() => {
    if (!file) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        onClose();
      }
    };

    document.addEventListener('keydown', onKey, true);
    return () => {
      document.removeEventListener('keydown', onKey, true);
      document.body.style.overflow = previousOverflow;
    };
  }, [file, onClose]);

  const resetPreviewState = useCallback(() => {
    setError(null);
    setImageUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev);
      return null;
    });
    setPdfBytes(null);
    setPdfPage(1);
    setPdfPageCount(1);
    setPdfRendering(false);
    setTextContent(null);
    setCsvRows(null);
    setUnsupportedNotice(false);
    if (docxHostRef.current) {
      docxHostRef.current.innerHTML = '';
    }
  }, []);

  // Fetch file bytes and prepare the appropriate renderer whenever `file` or retry changes.
  useEffect(() => {
    if (!mounted || !file) {
      resetPreviewState();
      return;
    }

    resetPreviewState();

    const controller = new AbortController();
    const kind = previewKindFor(file.ext);
    let createdObjectUrl: string | null = null;

    setLoading(true);

    const load = async (): Promise<void> => {
      try {
        const response = await fetch(file.url, {
          credentials: 'same-origin',
          signal: controller.signal,
        });

        if (!response.ok) {
          throw new Error(
            response.status === 403
              ? 'You do not have permission to view this file.'
              : response.status === 404
                ? 'This file could not be found.'
                : 'Could not load this file.',
          );
        }

        if (kind === 'image') {
          const blob = await response.blob();
          if (controller.signal.aborted) return;
          createdObjectUrl = URL.createObjectURL(blob);
          setImageUrl(createdObjectUrl);
          setLoading(false);
          return;
        }

        if (kind === 'pdf') {
          const bytes = await response.arrayBuffer();
          if (controller.signal.aborted) return;
          setPdfBytes(bytes);
          setPdfPage(1);
          setLoading(false);
          return;
        }

        if (kind === 'docx') {
          const bytes = await response.arrayBuffer();
          if (controller.signal.aborted) return;
          if (bytes.byteLength === 0) {
            throw new Error('This document is empty.');
          }

          const { renderAsync } = await import('docx-preview');
          if (controller.signal.aborted) return;

          const host = docxHostRef.current;
          if (!host) return;
          host.innerHTML = '';

          try {
            await renderAsync(bytes, host, undefined, {
              className: 'docx-paper',
              inWrapper: true,
              ignoreWidth: false,
              ignoreHeight: false,
              ignoreFonts: false,
              breakPages: true,
              renderHeaders: true,
              renderFooters: true,
              renderFootnotes: true,
              renderEndnotes: true,
              useBase64URL: true,
            });
          } catch {
            if (!controller.signal.aborted) {
              host.innerHTML = '';
              setUnsupportedNotice(true);
            }
          }

          if (!controller.signal.aborted) {
            setLoading(false);
          }
          return;
        }

        if (kind === 'text' || kind === 'csv') {
          const raw = await response.text();
          if (controller.signal.aborted) return;
          if (kind === 'csv') {
            setCsvRows(parseCsvRows(raw));
          } else {
            setTextContent(raw);
          }
          setLoading(false);
          return;
        }

        // Non-previewable formats (.doc, .xlsx, .heic, etc.): keep the fetch above
        // so `?open=1` still records the open and access is checked, then offer Download.
        await response.arrayBuffer();
        if (!controller.signal.aborted) {
          setUnsupportedNotice(true);
          setLoading(false);
        }
      } catch (err: unknown) {
        if (controller.signal.aborted) return;
        if (err instanceof Error && err.name === 'AbortError') return;

        setError(err instanceof Error ? err.message : 'Could not load this file.');
        setLoading(false);
      }
    };

    void load();

    return () => {
      controller.abort();
      if (createdObjectUrl) {
        URL.revokeObjectURL(createdObjectUrl);
      }
    };
  }, [mounted, file, retrySeed, resetPreviewState]);

  // Render the active PDF page onto `<canvas>` once `pdfBytes` is loaded.
  useEffect(() => {
    if (!file || previewKindFor(file.ext) !== 'pdf' || !pdfBytes) {
      return;
    }

    const canvas = pdfCanvasRef.current;
    if (!canvas) {
      return;
    }

    const controller = new AbortController();
    setPdfRendering(true);

    void renderPdfBytesToCanvas(pdfBytes, canvas, pdfPage, controller.signal)
      .then((res) => {
        if (controller.signal.aborted) return;
        setPdfPageCount(res.pageCount);
        setPdfPage(res.page);
        setPdfRendering(false);
      })
      .catch((err: unknown) => {
        if (controller.signal.aborted) return;
        if (
          err instanceof Error &&
          (err.name === 'AbortError' || err.name === 'RenderingCancelledException')
        ) {
          return;
        }
        setPdfRendering(false);
        setUnsupportedNotice(true);
      });

    return () => {
      controller.abort();
    };
  }, [file, pdfBytes, pdfPage]);

  if (!mounted || !file) {
    return null;
  }

  const kind = previewKindFor(file.ext);
  const extLabel = file.ext ? file.ext.toUpperCase() : 'FILE';
  const downloadHref = file.downloadUrl ?? file.url;

  return createPortal(
    <div
      className="dm-qr-layer"
      role="dialog"
      aria-modal="true"
      aria-label={file.title}
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        className="dm-qr-panel flex flex-col"
        style={{ maxWidth: 'min(94vw, 900px)' }}
      >
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex min-w-0 items-center gap-2.5">
            <h2 className="min-w-0 truncate text-xl font-semibold text-green-deep">{file.title}</h2>
            <span className="shrink-0 rounded-full border border-hairline bg-[#f4f7f5] px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-ink-soft">
              {extLabel}
            </span>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {kind === 'pdf' && pdfPageCount > 1 ? (
              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => setPdfPage((p) => Math.max(1, p - 1))}
                  disabled={pdfPage <= 1 || pdfRendering}
                  className="ff-page-btn !h-10 !w-10 text-base"
                  aria-label="Previous page"
                >
                  ‹
                </button>
                <span className="text-sm font-semibold text-ink-soft">
                  {pdfPage}/{pdfPageCount}
                </span>
                <button
                  type="button"
                  onClick={() => setPdfPage((p) => Math.min(pdfPageCount, p + 1))}
                  disabled={pdfPage >= pdfPageCount || pdfRendering}
                  className="ff-page-btn !h-10 !w-10 text-base"
                  aria-label="Next page"
                >
                  ›
                </button>
              </div>
            ) : null}

            <a
              href={downloadHref}
              download
              className="cr-btn cr-btn-sm"
            >
              Download
            </a>

            <button
              type="button"
              onClick={onClose}
              aria-label="Close preview"
              className="dm-close min-h-[40px] min-w-[40px]"
            >
              <svg fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>
        </div>

        <div className="relative mt-3 flex min-h-[280px] max-h-[72vh] w-full flex-col items-center justify-start overflow-auto rounded-[12px] border border-hairline bg-[#f4f7f5] p-3">
          {error ? (
            <div className="flex min-h-[240px] w-full flex-col items-center justify-center gap-3 p-6 text-center" role="alert">
              <p className="text-sm font-semibold text-status-red">{error}</p>
              <div className="flex flex-wrap items-center justify-center gap-2">
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  onClick={() => setRetrySeed((prev) => prev + 1)}
                >
                  Retry
                </button>
                <a href={downloadHref} download className="cr-btn cr-btn-sm cr-btn-primary">
                  Download file
                </a>
              </div>
            </div>
          ) : null}

          {!error && unsupportedNotice ? (
            <div className="flex min-h-[240px] w-full flex-col items-center justify-center gap-3 p-6 text-center">
              <p className="text-base font-semibold text-ink">
                In-screen preview isn&rsquo;t available for .{extLabel} files.
              </p>
              <p className="text-sm text-ink-soft">
                You can still download the file to open it on your device.
              </p>
              <a href={downloadHref} download className="cr-btn cr-btn-primary mt-1">
                Download {extLabel}
              </a>
            </div>
          ) : null}

          {!error && !unsupportedNotice && kind === 'image' && imageUrl ? (
            <div className="flex w-full items-center justify-center py-1">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={imageUrl}
                alt={file.title}
                className="block max-h-[66vh] max-w-full w-auto rounded-[6px] bg-white object-contain shadow-sm"
              />
            </div>
          ) : null}

          {!error && !unsupportedNotice && kind === 'pdf' && pdfBytes ? (
            <div className="relative flex w-full items-center justify-center py-1">
              <canvas
                ref={pdfCanvasRef}
                aria-label={`${file.title} page ${pdfPage}`}
                className="block max-w-full w-auto h-auto rounded-[6px] bg-white shadow-sm"
              />
            </div>
          ) : null}

          {!error && !unsupportedNotice && kind === 'docx' ? (
            <div
              ref={docxHostRef}
              className="docx-preview-host w-full"
              aria-label={file.title}
            />
          ) : null}

          {!error && !unsupportedNotice && kind === 'text' && textContent !== null ? (
            <div className="w-full max-w-[760px] rounded-[8px] border border-hairline bg-white p-6 shadow-sm">
              <pre className="whitespace-pre-wrap break-words font-sans text-sm leading-relaxed text-ink">
                {textContent || '(Empty file)'}
              </pre>
            </div>
          ) : null}

          {!error && !unsupportedNotice && kind === 'csv' && csvRows !== null ? (
            <div className="w-full overflow-auto rounded-[8px] border border-hairline bg-white shadow-sm">
              {csvRows.length === 0 ? (
                <p className="p-6 text-center text-sm text-ink-soft">(Empty file)</p>
              ) : (
                <table className="w-full border-collapse text-left text-sm text-ink">
                  <thead>
                    <tr className="border-b border-hairline bg-[#f4f7f5]">
                      {csvRows[0].map((cell, idx) => (
                        <th key={idx} className="border-r border-hairline px-3 py-2 font-semibold last:border-r-0">
                          {cell}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {csvRows.slice(1).map((row, rowIdx) => (
                      <tr key={rowIdx} className="border-b border-hairline last:border-b-0">
                        {row.map((cell, colIdx) => (
                          <td key={colIdx} className="border-r border-hairline px-3 py-2 last:border-r-0">
                            {cell}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          ) : null}

          {(loading || pdfRendering) && !error ? (
            <div
              className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-[12px] bg-[#f4f7f5]/90"
              role="status"
            >
              <svg className="h-7 w-7 animate-spin text-green" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity=".25" strokeWidth="3" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
              </svg>
              <p className="text-sm font-semibold text-ink-soft">Loading file…</p>
            </div>
          ) : null}
        </div>
      </div>
    </div>,
    document.body,
  );
}

/** Button that opens an in-screen `FilePreviewModal` on click (usable from Server Components). */
export function PreviewFileButton({
  file,
  className,
  children,
}: {
  file: PreviewFileTarget;
  className?: string;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button type="button" className={className} onClick={() => setOpen(true)}>
        {children}
      </button>
      <FilePreviewModal file={open ? file : null} onClose={() => setOpen(false)} />
    </>
  );
}
