'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { FormDepartment, FormRecipient } from '@/lib/domain/internal-request-form';
import { fileRequest } from '@/app/actions/internal';
import { renderPdfFilePage } from '@/lib/client/pdf-preview';

type QrPlacement = {
  ready: boolean;
  x: number;
  y: number;
  size: number;
  sizeH: number;
  scaleShort: number;
  imgW: number;
  imgH: number;
  userMoved: boolean;
};

const INITIAL_QR: QrPlacement = {
  ready: false,
  x: 0.75,
  y: 0.75,
  size: 0.24,
  sizeH: 0.24,
  scaleShort: 0.22,
  imgW: 0,
  imgH: 0,
  userMoved: false,
};

function QrGlyph() {
  return (
    <div className="flex h-full w-full items-center justify-center">
      <svg
        className="h-1/2 w-1/2 text-green"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
      >
        <path
          fillRule="evenodd"
          d="M3 3h7v7H3V3Zm2 2v3h3V5H5Zm9-2h7v7h-7V3Zm2 2v3h3V5h-3ZM3 14h7v7H3v-7Zm2 2v3h3v-3H5Z"
        />
        <path d="M14 14h2.5v2.5H14zM18.5 14H21v2.5h-2.5zM16.5 16.5H19V19h-2.5zM14 18.5h2.5V21H14zM18.5 18.5H21V21h-2.5z" />
      </svg>
    </div>
  );
}

/**
 * Port of `requests/partials/file-form.blade.php` — 2-step Details → Review
 * wizard with QR placement on the scanned page.
 */
export function FilingForm({
  departments,
  officeName,
  officeCode = null,
}: {
  departments: FormDepartment[];
  officeName: string;
  officeCode?: string | null;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  const [step, setStep] = useState<1 | 2>(1);
  const [firstDepartment, setFirstDepartment] = useState('');
  const [purpose, setPurpose] = useState('');
  const [hasHardCopy, setHasHardCopy] = useState(true);
  const [recipientId, setRecipientId] = useState('');
  const [people, setPeople] = useState<FormRecipient[]>([]);

  const [scan, setScan] = useState<File | null>(null);
  const [fileName, setFileName] = useState('');
  const [fileError, setFileError] = useState('');
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isPdf, setIsPdf] = useState(false);
  const [pdfPage, setPdfPage] = useState(1);
  const [pdfPageCount, setPdfPageCount] = useState(1);
  const [pdfRendering, setPdfRendering] = useState(false);
  const [pdfError, setPdfError] = useState('');
  const [dragOver, setDragOver] = useState(false);

  const [qr, setQr] = useState<QrPlacement>(INITIAL_QR);
  const [qrModal, setQrModal] = useState(false);
  const paperInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (hasHardCopy || firstDepartment === '') {
      setPeople([]);
      return;
    }

    let live = true;

    void fetch(`/requests/recipients/${firstDepartment}`)
      .then((response) => (response.ok ? response.json() : []))
      .then((rows: FormRecipient[]) => {
        if (live) {
          setPeople(rows);
        }
      })
      .catch(() => setPeople([]));

    return () => {
      live = false;
    };
  }, [firstDepartment, hasHardCopy]);

  useEffect(() => {
    return () => {
      if (previewUrl && previewUrl.startsWith('blob:')) {
        URL.revokeObjectURL(previewUrl);
      }
    };
  }, [previewUrl]);

  const selectedDepartment =
    departments.find((d) => String(d.id) === String(firstDepartment)) ?? null;
  const selectedRecipient =
    people.find((p) => String(p.id) === String(recipientId)) ?? null;

  const loadPdfPage = async (file: File, pageNumber: number) => {
    setPdfRendering(true);
    setPdfError('');
    try {
      const result = await renderPdfFilePage(file, pageNumber);
      setPdfPageCount(result.pageCount);
      setPdfPage(result.page);
      setQr((prev) => ({ ...prev, ready: false }));
      setPreviewUrl(result.dataUrl);
    } catch {
      setPreviewUrl(null);
      setPdfError(
        'That PDF could not be previewed, so the QR will be stamped at the bottom-right of page 1.',
      );
    } finally {
      setPdfRendering(false);
    }
  };

  const handleFile = (file: File | null) => {
    if (previewUrl && previewUrl.startsWith('blob:')) {
      URL.revokeObjectURL(previewUrl);
    }
    setQr(INITIAL_QR);
    setFileError('');
    setIsPdf(false);
    setPdfPage(1);
    setPdfPageCount(1);
    setPdfError('');

    if (!file) {
      setScan(null);
      setFileName('');
      setPreviewUrl(null);
      return;
    }

    if (!/\.(pdf|jpe?g|png|webp)$/i.test(file.name)) {
      if (paperInputRef.current) {
        paperInputRef.current.value = '';
      }
      setScan(null);
      setFileName('');
      setPreviewUrl(null);
      setFileError(
        "That file can't carry the QR. Upload the signed paper as a PDF or a picture (JPG, PNG or WEBP) — save a Word file as PDF first.",
      );
      return;
    }

    setScan(file);
    setFileName(file.name);

    if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) {
      setIsPdf(true);
      setPreviewUrl(null);
      void loadPdfPage(file, 1);
      return;
    }

    setPreviewUrl(file.type.startsWith('image/') ? URL.createObjectURL(file) : null);
  };

  const recomputeQrBox = (state: QrPlacement, nextScale = state.scaleShort): QrPlacement => {
    const W = state.imgW;
    const H = state.imgH;
    if (!W || !H) return { ...state, scaleShort: nextScale };

    const boxPx = nextScale * Math.min(W, H) * 1.12;
    const size = boxPx / W;
    const sizeH = boxPx / H;
    const x = Math.max(0, Math.min(state.x, 1 - size));
    const y = Math.max(0, Math.min(state.y, 1 - sizeH));

    return {
      ...state,
      scaleShort: nextScale,
      size,
      sizeH,
      x,
      y,
    };
  };

  const initQrPlacement = (img: HTMLImageElement) => {
    if (!img || !img.naturalWidth) return;
    setQr((prev) => {
      const next = recomputeQrBox({
        ...prev,
        imgW: img.naturalWidth,
        imgH: img.naturalHeight,
      });
      if (!next.userMoved) {
        const margin = 0.1 * next.scaleShort * Math.min(next.imgW, next.imgH);
        next.x = Math.max(0, 1 - next.size - margin / next.imgW);
        next.y = Math.max(0, 1 - next.sizeH - margin / next.imgH);
      }
      next.ready = true;
      return next;
    });
  };

  const startDragQr = (event: React.PointerEvent<HTMLDivElement>) => {
    event.preventDefault();
    const stage = event.currentTarget.closest('[data-qr-stage]');
    const img = stage?.querySelector('img');
    if (!img) return;

    const rect = img.getBoundingClientRect();
    const boxW = qr.size * rect.width;
    const boxH = qr.sizeH * rect.height;
    const startX = event.clientX;
    const startY = event.clientY;
    const origX = qr.x * rect.width;
    const origY = qr.y * rect.height;

    const move = (ev: PointerEvent) => {
      const nx = Math.max(0, Math.min(origX + (ev.clientX - startX), rect.width - boxW));
      const ny = Math.max(0, Math.min(origY + (ev.clientY - startY), rect.height - boxH));
      setQr((prev) => ({
        ...prev,
        x: nx / rect.width,
        y: ny / rect.height,
        userMoved: true,
      }));
    };

    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  const canReview =
    Boolean(firstDepartment) &&
    purpose.trim() !== '' &&
    scan !== null &&
    (hasHardCopy || selectedRecipient !== null);

  const submit = (): void => {
    setError(null);

    const form = new FormData();
    form.set('first_department_id', firstDepartment);
    form.set('purpose', purpose.trim());
    form.set('has_hard_copy', hasHardCopy ? '1' : '0');

    if (!hasHardCopy) {
      form.set('recipient_id', recipientId);
    }

    if (scan) {
      form.set('paper_scan', scan);
    }

    if (qr.ready) {
      form.set('qr_x', qr.x.toFixed(4));
      form.set('qr_y', qr.y.toFixed(4));
      form.set('qr_size', qr.scaleShort.toFixed(4));
    }

    if (isPdf) {
      form.set('qr_page', String(pdfPage));
    }

    startTransition(async () => {
      const result = await fileRequest(form);

      if (result.ok && result.url) {
        router.push(result.url);
        return;
      }

      setError(result.ok ? 'Filed, but the confirmation could not be opened.' : result.message);
    });
  };

  return (
    <div className="flex h-full min-h-0 flex-col">
      {/* Header: title, filing office, and the two steps */}
      <div className="dm-head">
        <div className="min-w-0">
          <p className="dm-title">File a Request</p>
          <p className="text-[length:var(--fs-small)] text-ink">
            Filing from the {officeName}
          </p>
        </div>
        <div className="flex shrink-0 items-center gap-3">
          <ol className="ff-steps" aria-label="Progress">
            {(['Details', 'Review'] as const).map((label, i) => {
              const stepNum = (i + 1) as 1 | 2;
              const isCurrent = step === stepNum;
              const isDone = step > stepNum;
              return (
                <li key={label} className="flex items-center gap-2">
                  {i > 0 ? (
                    <span
                      className={`ff-step-line${step > i ? ' is-done' : ''}`}
                      aria-hidden="true"
                    />
                  ) : null}
                  <button
                    type="button"
                    onClick={() => {
                      if (stepNum < step) {
                        setStep(stepNum);
                      }
                    }}
                    aria-current={isCurrent ? 'step' : undefined}
                    className={`ff-step${isCurrent ? ' is-current' : isDone ? ' is-done' : ''}`}
                  >
                    <span className="ff-step-n">
                      {isDone ? (
                        <svg
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="3"
                          viewBox="0 0 24 24"
                          aria-hidden="true"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="m6 12.5 4 4 8-9"
                          />
                        </svg>
                      ) : (
                        <span>{stepNum}</span>
                      )}
                    </span>
                    <span>{label}</span>
                  </button>
                </li>
              );
            })}
          </ol>
        </div>
      </div>

      <form
        onSubmit={(event) => {
          event.preventDefault();
          if (step === 1) {
            if (canReview) setStep(2);
          } else {
            submit();
          }
        }}
        className="flex min-h-0 flex-1 flex-col"
      >
        <div className="dm-body">
          {error ? (
            <div
              className="mb-5 rounded-[12px] border border-status-red-wash bg-status-red-wash px-4 py-3 text-[length:var(--fs-small)] font-medium text-status-red"
              role="alert"
            >
              <ul className="list-inside list-disc space-y-0.5">
                <li>{error}</li>
              </ul>
            </div>
          ) : null}

          {/* ============ STEP 1: Details ============ */}
          {step === 1 ? (
            <div className="grid min-h-full grid-cols-1 gap-6 md:grid-cols-2 md:grid-rows-[1fr]">
              <div className="flex flex-col gap-5">
                <div>
                  <label htmlFor="route-select" className="st-filter-label">
                    First Department Route <span className="text-status-red">*</span>
                  </label>

                  <div
                    className={`ff-route${selectedDepartment ? ' is-set' : ''}`}
                    aria-hidden="true"
                  >
                    <div className="ff-route-track">
                      <span className="ff-route-node is-own" />
                      <span className="ff-route-line" />
                      <span className="ff-route-node is-next" />
                    </div>
                    <div className="ff-route-labels">
                      <span className="is-own">{officeName}</span>
                      <span className="is-next">
                        {selectedDepartment ? selectedDepartment.name : 'Next Route'}
                      </span>
                    </div>
                  </div>

                  <select
                    id="route-select"
                    name="first_department_id"
                    value={firstDepartment}
                    onChange={(event) => {
                      setFirstDepartment(event.target.value);
                      setRecipientId('');
                    }}
                    required
                    className="st-filter-field mt-3"
                  >
                    <option value="">Select a Department…</option>
                    {departments.map((d) => (
                      <option key={d.id} value={d.id}>
                        {d.name} ({d.code})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label htmlFor="purpose" className="st-filter-label">
                    What is being Requested? <span className="text-status-red">*</span>
                  </label>
                  <input
                    id="purpose"
                    type="text"
                    name="purpose"
                    value={purpose}
                    onChange={(event) => setPurpose(event.target.value)}
                    required
                    maxLength={255}
                    placeholder="e.g. 10 Monoblock chairs and a flower vase…"
                    className="st-filter-field"
                  />
                </div>

                <div>
                  <label className="ff-toggle">
                    <input
                      type="checkbox"
                      role="switch"
                      checked={hasHardCopy}
                      onChange={(event) => setHasHardCopy(event.target.checked)}
                      className="ff-toggle-input"
                    />
                    <span className="ff-toggle-track" aria-hidden="true">
                      <span className="ff-toggle-thumb" />
                    </span>
                    <span className="min-w-0">
                      <span className="block text-[length:var(--fs-small)] font-semibold text-ink">
                        A hard copy goes to the first office
                      </span>
                      <span className="block text-[length:var(--fs-caption)] text-ink-soft">
                        {hasHardCopy
                          ? 'Someone from your office hands over the paper; they scan its QR to receive it.'
                          : 'Soft copy only: it reaches the person you choose below as soon as you file.'}
                      </span>
                    </span>
                  </label>
                </div>

                {!hasHardCopy ? (
                  <div>
                    <label htmlFor="recipient-select" className="st-filter-label">
                      Who in the first office receives it?{' '}
                      <span className="text-status-red">*</span>
                    </label>
                    <select
                      id="recipient-select"
                      name="recipient_id"
                      value={recipientId}
                      required={!hasHardCopy}
                      disabled={hasHardCopy || !firstDepartment}
                      onChange={(event) => setRecipientId(event.target.value)}
                      className="st-filter-field"
                    >
                      <option value="">
                        {firstDepartment
                          ? 'Select a person…'
                          : 'Choose the first department first'}
                      </option>
                      {people.map((person) => (
                        <option key={person.id} value={person.id}>
                          {person.name} · {person.role}
                        </option>
                      ))}
                    </select>
                    {firstDepartment && people.length === 0 ? (
                      <p className="mt-1 text-[length:var(--fs-caption)] font-semibold text-status-amber">
                        No one in that office has an active account yet. Send a hard copy instead,
                        or ask an administrator to add them.
                      </p>
                    ) : null}
                  </div>
                ) : null}
              </div>

              <div className="flex flex-col">
                <p className="st-filter-label">
                  Scanned Document <span className="text-status-red">*</span>
                </p>
                <label
                  className={`ff-upload${fileName ? ' has-file' : ''}${
                    dragOver ? ' is-over' : ''
                  }`}
                  onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                  }}
                  onDragLeave={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                  }}
                  onDrop={(e) => {
                    e.preventDefault();
                    setDragOver(false);
                    const file = e.dataTransfer?.files?.[0] ?? null;
                    if (file) {
                      handleFile(file);
                    }
                  }}
                >
                  {previewUrl ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={previewUrl}
                      alt="Preview of the scanned document"
                      className="max-h-[220px] rounded-[10px] border border-hairline bg-white shadow-sm"
                    />
                  ) : (
                    <svg
                      className="ff-upload-icon"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M12 15V4m0 0L7.5 8.5M12 4l4.5 4.5M5 15v3.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V15"
                      />
                    </svg>
                  )}
                  <span className="ff-upload-title max-w-full truncate">
                    {fileName || 'Click or Drop to upload a file'}
                  </span>
                  <span className="ff-upload-hint">
                    {fileName
                      ? 'Click or drop another file to replace it'
                      : 'JPG, PNG, WEBP or PDF up to 10 MB'}
                  </span>
                  {fileError ? (
                    <span
                      className="mt-2 text-[length:var(--fs-small)] font-semibold text-status-red"
                      role="alert"
                    >
                      {fileError}
                    </span>
                  ) : null}
                  <input
                    ref={paperInputRef}
                    type="file"
                    name="paper_scan"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    className="sr-only"
                    onChange={(event) => handleFile(event.target.files?.[0] ?? null)}
                  />
                </label>
              </div>
            </div>
          ) : (
            /* ============ STEP 2: Review ============ */
            <div className="grid min-h-full grid-cols-1 gap-6 md:grid-cols-2 md:grid-rows-[1fr]">
              <div className="flex flex-col">
                <p className="st-filter-label">Summary</p>

                <div className="ff-route is-set" aria-hidden="true">
                  <div className="ff-route-track">
                    <span className="ff-route-node is-own" />
                    <span className="ff-route-line" />
                    <span className="ff-route-node is-next" />
                  </div>
                  <div className="ff-route-labels">
                    <span className="is-own">{officeName}</span>
                    <span className="is-next">
                      {selectedDepartment ? selectedDepartment.name : 'Next Route'}
                    </span>
                  </div>
                </div>

                <dl className="dm-info mt-2">
                  <div className="dm-info-row">
                    <dt>Requesting office</dt>
                    <dd>
                      {officeName}
                      {officeCode ? ` (${officeCode})` : ''}
                    </dd>
                  </div>
                  <div className="dm-info-row">
                    <dt>First department</dt>
                    <dd>
                      {selectedDepartment
                        ? `${selectedDepartment.name} (${selectedDepartment.code})`
                        : '—'}
                    </dd>
                  </div>
                  <div className="dm-info-row">
                    <dt>Request</dt>
                    <dd>{purpose}</dd>
                  </div>
                  <div className="dm-info-row">
                    <dt>Hard copy</dt>
                    <dd>
                      {hasHardCopy
                        ? 'Yes — paper goes to the first office'
                        : 'No — soft copy only'}
                    </dd>
                  </div>
                  {!hasHardCopy ? (
                    <div className="dm-info-row">
                      <dt>Received by</dt>
                      <dd>
                        {selectedRecipient
                          ? `${selectedRecipient.name} (${selectedRecipient.role})`
                          : '—'}
                      </dd>
                    </div>
                  ) : null}
                  <div className="dm-info-row">
                    <dt>Scanned document</dt>
                    <dd>{fileName || 'None attached'}</dd>
                  </div>
                </dl>

                <p className="ff-note mt-auto">
                  <svg
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <circle cx="12" cy="12" r="9" />
                    <path strokeLinecap="round" d="M12 11v5M12 8h.01" />
                  </svg>
                  A tracking number and QR are generated when you file.
                </p>
              </div>

              <div className="flex min-h-0 flex-col">
                <div className="flex items-center justify-between gap-2">
                  <p className="st-filter-label !mb-0">QR Placement</p>
                  {isPdf && pdfPageCount > 1 ? (
                    <div className="flex items-center gap-1.5">
                      <button
                        type="button"
                        onClick={() => scan && void loadPdfPage(scan, pdfPage - 1)}
                        disabled={pdfPage <= 1 || pdfRendering}
                        className="ff-page-btn"
                        aria-label="Previous page"
                      >
                        ‹
                      </button>
                      <span className="text-[length:var(--fs-caption)] font-semibold text-ink-soft">
                        Page {pdfPage} of {pdfPageCount}
                      </span>
                      <button
                        type="button"
                        onClick={() => scan && void loadPdfPage(scan, pdfPage + 1)}
                        disabled={pdfPage >= pdfPageCount || pdfRendering}
                        className="ff-page-btn"
                        aria-label="Next page"
                      >
                        ›
                      </button>
                    </div>
                  ) : null}
                </div>

                <div className="ff-stage">
                  {pdfRendering ? (
                    <p className="text-[length:var(--fs-small)] font-semibold text-ink-soft">
                      Opening the PDF…
                    </p>
                  ) : pdfError ? (
                    <p className="px-4 text-center text-[length:var(--fs-small)] font-semibold text-status-amber">
                      {pdfError}
                    </p>
                  ) : !previewUrl ? (
                    <p className="px-4 text-center text-[length:var(--fs-small)] text-ink-soft">
                      This file can&apos;t be previewed, so the QR will be stamped at the
                      bottom-right corner.
                    </p>
                  ) : (
                    <div
                      className="relative inline-block select-none leading-none"
                      data-qr-stage
                    >
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src={previewUrl}
                        alt="Scanned document"
                        className="block max-h-[330px] w-auto rounded-[4px] shadow-sm"
                        onLoad={(e) => initQrPlacement(e.currentTarget)}
                        draggable={false}
                      />
                      {qr.ready ? (
                        <div
                          className="ff-qr-box"
                          style={{
                            left: `${qr.x * 100}%`,
                            top: `${qr.y * 100}%`,
                            width: `${qr.size * 100}%`,
                            aspectRatio: '1',
                          }}
                          onPointerDown={startDragQr}
                        >
                          <QrGlyph />
                        </div>
                      ) : null}
                    </div>
                  )}
                </div>

                {qr.ready ? (
                  <div className="mt-3 flex items-center gap-3">
                    <span className="text-[length:var(--fs-caption)] font-semibold text-ink-soft">
                      Size
                    </span>
                    <input
                      type="range"
                      min="0.12"
                      max="0.40"
                      step="0.005"
                      value={qr.scaleShort}
                      onChange={(e) => {
                        const scale = Number(e.target.value);
                        setQr((prev) => recomputeQrBox({ ...prev, userMoved: true }, scale));
                      }}
                      className="h-1.5 flex-1 accent-green"
                      aria-label="QR code size"
                    />
                    <button
                      type="button"
                      onClick={() => setQrModal(true)}
                      className="cr-btn shrink-0"
                    >
                      Adjust in detail
                    </button>
                  </div>
                ) : null}
                <p className="ff-hint">
                  Drag the QR onto a clear area of the paper — it is stamped exactly there.
                </p>
              </div>
            </div>
          )}
        </div>

        <div className="dm-foot dm-actions">
          {step === 2 ? (
            <button type="button" onClick={() => setStep(1)} className="cr-btn">
              ← Back
            </button>
          ) : null}
          <div className="ml-auto flex items-center gap-2">
            {step === 1 ? (
              <button
                type="button"
                disabled={!canReview}
                onClick={() => {
                  if (canReview) setStep(2);
                }}
                className="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-40"
              >
                Review →
              </button>
            ) : (
              <button type="submit" disabled={pending} className="cr-btn cr-btn-primary">
                {pending ? 'Filing…' : 'File Request'}
              </button>
            )}
          </div>
        </div>
      </form>

      {/* Detailed QR editor modal */}
      {qrModal && previewUrl ? (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
          onPointerDown={(e) => {
            if (e.target === e.currentTarget) setQrModal(false);
          }}
        >
          <div className="flex max-h-[calc(92vh/var(--ui-zoom))] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-hairline bg-paper shadow-2xl">
            <div className="dm-head">
              <p className="dm-section">Place &amp; size the QR code</p>
              <button
                type="button"
                onClick={() => setQrModal(false)}
                aria-label="Close editor"
                className="dm-close"
              >
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.2"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path strokeLinecap="round" d="M6 6l12 12M18 6 6 18" />
                </svg>
              </button>
            </div>
            <div className="min-h-0 flex-1 overflow-auto bg-[#f4f7f5] p-4 text-center">
              <div className="relative inline-block select-none leading-none" data-qr-stage>
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={previewUrl}
                  alt="Scanned document"
                  className="block max-h-[calc(70vh/var(--ui-zoom))] w-auto rounded"
                  onLoad={(e) => initQrPlacement(e.currentTarget)}
                  draggable={false}
                />
                {qr.ready ? (
                  <div
                    className="ff-qr-box"
                    style={{
                      left: `${qr.x * 100}%`,
                      top: `${qr.y * 100}%`,
                      width: `${qr.size * 100}%`,
                      aspectRatio: '1',
                    }}
                    onPointerDown={startDragQr}
                  >
                    <QrGlyph />
                  </div>
                ) : null}
              </div>
            </div>
            <div className="dm-foot dm-actions">
              <span className="shrink-0 text-[length:var(--fs-caption)] font-semibold text-ink-soft">
                Size
              </span>
              <input
                type="range"
                min="0.12"
                max="0.40"
                step="0.005"
                value={qr.scaleShort}
                onChange={(e) => {
                  const scale = Number(e.target.value);
                  setQr((prev) => recomputeQrBox({ ...prev, userMoved: true }, scale));
                }}
                className="h-1.5 flex-1 accent-green"
                aria-label="QR code size"
              />
              <button
                type="button"
                onClick={() => setQrModal(false)}
                className="cr-btn cr-btn-primary shrink-0"
              >
                Done
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
