'use client';

import { useEffect, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { InternalRequestDetail } from '@/lib/domain/internal-request';
import { NextSteps, currentStepIndex, stepFocus, type NextStep } from './next-steps';
import { CameraModal } from '@/components/camera-modal';
import { useConfirm, type ConfirmOptions } from '@/components/confirm-dialog';
import * as SpeedQr from '@/lib/client/speed-qr';
import { renderPdfUrlPage } from '@/lib/client/pdf-preview';
import { StampPlacer, type StampChoice } from './stamp-placer';
import {
  approveStep,
  completeStep,
  denyStep,
  receiveRequest,
  resendRequest,
  returnStep,
  withdrawRequest,
  type InternalResult,
} from '@/app/actions/internal';

async function openAndWaitForQr(expected: string): Promise<void> {
  await new Promise<void>((resolve) => {
    let settled = false;
    const finish = () => {
      if (settled) return;
      settled = true;
      window.removeEventListener('request-qr-ready', onReady);
      clearTimeout(timer);
      requestAnimationFrame(() => setTimeout(resolve, 120));
    };
    const onReady = (event: Event) => {
      const detail = (event as CustomEvent<string>).detail;
      if (!detail || detail.toUpperCase() === expected) {
        finish();
      }
    };
    const timer = setTimeout(finish, 1800);
    window.addEventListener('request-qr-ready', onReady);
    window.dispatchEvent(new CustomEvent('show-request-qr', { detail: expected }));
  });
}

/**
 * Port of `partials/qr-tile.blade.php`.
 * Listens for `show-request-qr` / `hide-request-qr` events dispatched by the
 * Receive or Decision card's "Select the QR on screen" button, opening the
 * attached QR-stamped file (or QR sticker fallback) so the user can snip the
 * QR code directly off the document.
 */
export function QrTile({
  trackingNumber,
  qrUrl,
  stickerUrl,
  stampedAttachment = null,
}: {
  trackingNumber: string;
  qrUrl: string | null;
  stickerUrl: string;
  stampedAttachment?: { id: number; url: string; label: string; ext: string } | null;
}) {
  const [qrOpen, setQrOpen] = useState(false);
  const [viewMode, setViewMode] = useState<'document' | 'sticker'>(
    stampedAttachment ? 'document' : 'sticker',
  );
  const [pdfDataUrl, setPdfDataUrl] = useState<string | null>(null);
  const [pdfPage, setPdfPage] = useState(1);
  const [pdfPageCount, setPdfPageCount] = useState(1);
  const [pdfLoading, setPdfLoading] = useState(false);

  const expected = trackingNumber.toUpperCase();
  const isPdf = stampedAttachment?.ext.toUpperCase() === 'PDF';
  const showingDocument = Boolean(stampedAttachment && viewMode === 'document');

  useEffect(() => {
    setViewMode(stampedAttachment ? 'document' : 'sticker');
  }, [stampedAttachment]);

  useEffect(() => {
    if (!qrOpen || !showingDocument || !stampedAttachment || !isPdf) {
      return;
    }

    let active = true;
    setPdfLoading(true);

    void renderPdfUrlPage(stampedAttachment.url, pdfPage)
      .then((res) => {
        if (!active) return;
        setPdfDataUrl(res.dataUrl);
        setPdfPageCount(res.pageCount);
        setPdfPage(res.page);
      })
      .catch(() => {
        if (!active) return;
        setViewMode('sticker');
      })
      .finally(() => {
        if (active) {
          setPdfLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, [qrOpen, showingDocument, stampedAttachment, isPdf, pdfPage]);

  useEffect(() => {
    const onShow = (event: Event): void => {
      const detail = (event as CustomEvent<string>).detail;
      if (detail?.toUpperCase() === expected) {
        if (stampedAttachment) {
          setViewMode('document');
        }
        setQrOpen(true);
      }
    };
    const onHide = (event: Event): void => {
      const detail = (event as CustomEvent<string>).detail;
      if (detail?.toUpperCase() === expected) {
        setQrOpen(false);
      }
    };
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setQrOpen(false);
      }
    };

    window.addEventListener('show-request-qr', onShow);
    window.addEventListener('hide-request-qr', onHide);
    window.addEventListener('keydown', onKey);

    return () => {
      window.removeEventListener('show-request-qr', onShow);
      window.removeEventListener('hide-request-qr', onHide);
      window.removeEventListener('keydown', onKey);
    };
  }, [expected, stampedAttachment]);

  const notifyReady = () => {
    window.dispatchEvent(new CustomEvent('request-qr-ready', { detail: expected }));
  };

  return (
    <>
      <div className="flex items-center gap-3">
        <button
          type="button"
          className="dm-qr-btn"
          aria-label={`Show QR code for ${trackingNumber}`}
          title="Show QR code"
          onClick={() => setQrOpen(true)}
        >
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path
              fillRule="evenodd"
              d="M3 3h7v7H3V3Zm2 2v3h3V5H5Zm9-2h7v7h-7V3Zm2 2v3h3V5h-3ZM3 14h7v7H3v-7Zm2 2v3h3v-3H5Z"
            />
            <path d="M14 14h2.5v2.5H14zM18.5 14H21v2.5h-2.5zM16.5 16.5H19V19h-2.5zM14 18.5h2.5V21H14zM18.5 18.5H21V21h-2.5z" />
          </svg>
          <span className="dm-qr-zoom" aria-hidden="true">
            <svg fill="none" stroke="currentColor" strokeWidth="2.6" viewBox="0 0 24 24">
              <circle cx="10.5" cy="10.5" r="6" />
              <path strokeLinecap="round" d="m15 15 5 5" />
            </svg>
          </span>
        </button>
        <div>
          <p className="dm-title !text-[length:var(--fs-label)]">Tracking Number</p>
          <p className="mono text-[length:var(--fs-small)] font-bold text-ink">{trackingNumber}</p>
        </div>
      </div>

      {qrOpen ? (
        <div
          className="dm-qr-layer"
          role="dialog"
          aria-modal="true"
          aria-label="QR code"
          onClick={(event) => {
            if (event.target === event.currentTarget) {
              setQrOpen(false);
            }
          }}
        >
          <div
            className="dm-qr-panel"
            style={showingDocument ? { maxWidth: 'min(92vw, 760px)' } : undefined}
          >
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="dm-section">
                {showingDocument ? stampedAttachment?.label ?? 'Attached document' : 'QR code'}
              </p>

              <div className="flex items-center gap-2">
                {stampedAttachment ? (
                  <div className="flex items-center rounded-lg border border-hairline bg-[#f4f7f5] p-0.5 text-xs font-semibold">
                    <button
                      type="button"
                      onClick={() => setViewMode('document')}
                      className={`rounded-md px-2.5 py-1 transition ${
                        showingDocument ? 'bg-white text-green-deep shadow-sm' : 'text-ink-soft'
                      }`}
                    >
                      Attached file
                    </button>
                    <button
                      type="button"
                      onClick={() => setViewMode('sticker')}
                      className={`rounded-md px-2.5 py-1 transition ${
                        !showingDocument ? 'bg-white text-green-deep shadow-sm' : 'text-ink-soft'
                      }`}
                    >
                      QR only
                    </button>
                  </div>
                ) : null}

                {showingDocument && isPdf && pdfPageCount > 1 ? (
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => setPdfPage((p) => Math.max(1, p - 1))}
                      disabled={pdfPage <= 1 || pdfLoading}
                      className="ff-page-btn"
                      aria-label="Previous page"
                    >
                      ‹
                    </button>
                    <span className="text-[11px] font-semibold text-ink-soft">
                      {pdfPage}/{pdfPageCount}
                    </span>
                    <button
                      type="button"
                      onClick={() => setPdfPage((p) => Math.min(pdfPageCount, p + 1))}
                      disabled={pdfPage >= pdfPageCount || pdfLoading}
                      className="ff-page-btn"
                      aria-label="Next page"
                    >
                      ›
                    </button>
                  </div>
                ) : null}

                <button
                  type="button"
                  aria-label="Close QR code"
                  className="dm-close"
                  onClick={() => setQrOpen(false)}
                >
                  <svg fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" d="M6 6l12 12M18 6 6 18" />
                  </svg>
                </button>
              </div>
            </div>

            {showingDocument && stampedAttachment ? (
              <div className="mt-3 flex min-h-[220px] flex-col items-center justify-center rounded-[12px] border border-hairline bg-[#f4f7f5] p-3">
                {isPdf && (pdfLoading || !pdfDataUrl) ? (
                  <p className="text-[length:var(--fs-small)] font-semibold text-ink-soft">
                    Opening attached PDF…
                  </p>
                ) : (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={isPdf ? pdfDataUrl! : stampedAttachment.url}
                    alt={`${stampedAttachment.label} for ${trackingNumber}`}
                    className="block max-h-[66vh] max-w-full w-auto rounded-[6px] bg-white shadow-sm"
                    onLoad={notifyReady}
                    onError={() => setViewMode('sticker')}
                  />
                )}
              </div>
            ) : qrUrl ? (
              <div className="dm-qr-frame">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={qrUrl}
                  alt={`QR code for ${trackingNumber}`}
                  onLoad={notifyReady}
                />
              </div>
            ) : (
              <p className="dm-qr-missing">No QR code has been generated for this request.</p>
            )}

            <p className="mono mt-3 text-center text-[length:var(--fs-body)] font-bold tracking-wide text-ink">
              {trackingNumber}
            </p>
            <p className="mt-1 text-center text-[length:var(--fs-caption)] text-ink-soft">
              {showingDocument
                ? 'Drag the selection box around the QR code stamped on this attached document.'
                : trackingNumber.startsWith('INT-')
                  ? 'Any phone camera can scan it to see which office has it — no account needed.'
                  : "Scanning it opens this request's public tracking page."}
            </p>

            <div className="dm-actions mt-4 justify-center">
              {stampedAttachment ? (
                <a
                  href={stampedAttachment.url}
                  target="_blank"
                  rel="noopener"
                  className="cr-btn"
                >
                  Open full file
                </a>
              ) : null}
              {qrUrl ? (
                <a href={qrUrl} download={`${trackingNumber}.png`} className="cr-btn">
                  <svg fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14" />
                  </svg>
                  Download QR
                </a>
              ) : null}
              <a href={stickerUrl} target="_blank" rel="noopener" className="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinejoin="round" d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2" />
                  <path strokeLinejoin="round" d="M7 14h10v6H7z" />
                </svg>
                Print slip
              </a>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

/**
 * The panels beside an internal request:
 *  - Receive it (when custody hasn't been recorded yet for this hop);
 *  - Decide on it (for supervisors or named recipients once custody is held);
 *  - Custody status (for office staff holding custody while awaiting supervisor review);
 *  - Revise or withdraw it (when returned to the filing office).
 */
export function RequestPanels({ detail }: { detail: InternalRequestDetail }) {
  const showReceive = detail.canReceive && detail.currentStep !== null && !detail.hasCustody;
  const showDecision = detail.canAct && detail.currentStep !== null && detail.hasCustody;
  const showCustodyStatus = detail.canReceive && detail.currentStep !== null && detail.hasCustody && !detail.canAct;

  if (showReceive) {
    return <ReceiveCard detail={detail} />;
  }

  if (showDecision) {
    return <DecisionCard detail={detail} />;
  }

  if (showCustodyStatus) {
    return <CustodyStatusCard detail={detail} />;
  }

  if (detail.canRevise) {
    return <ReviseCard detail={detail} />;
  }

  return null;
}

/**
 * Shared plumbing: run an action, show its refusal where the button is. With
 * `ask`, the person confirms first.
 */
function useAction() {
  const router = useRouter();
  const confirm = useConfirm();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const run = async (action: () => Promise<InternalResult>, ask?: ConfirmOptions): Promise<void> => {
    if (ask && !(await confirm(ask))) {
      return;
    }

    setError(null);

    startTransition(async () => {
      const result = await action();

      if (result.ok) {
        setNotice(result.message);
        router.refresh();

        return;
      }

      setError(result.message);
    });
  };

  return { pending, error, notice, run, setError };
}

function Feedback({ error, notice }: { error: string | null; notice: string | null }) {
  return (
    <>
      {notice ? (
        <div className="dm-callout dm-callout-green" role="status">
          <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p>{notice}</p>
        </div>
      ) : null}
      {error ? (
        <ul className="gate-errors" role="alert">
          <li>{error}</li>
        </ul>
      ) : null}
    </>
  );
}

/**
 * Receiving. QR is load-bearing: the endorsement stays locked until this office
 * scans the folder to prove the paper is in hand — or, for a soft copy, until
 * the person it was sent to picks it up by scanning or dropping its QR.
 */
function ReceiveCard({ detail }: { detail: InternalRequestDetail }) {
  const { pending, error, notice, run, setError } = useAction();
  const [scanning, setScanning] = useState(false);
  const [reading, setReading] = useState(false);
  const [manualOpen, setManualOpen] = useState(false);
  const [reason, setReason] = useState('');

  const expected = detail.trackingNumber.toUpperCase();

  const receive = (
    captureMethod: 'scan' | 'image' | 'screen' | 'manual',
    scannedValue: string | null = null,
  ): void =>
    void run(() =>
      receiveRequest(detail.id, {
        captureMethod,
        scannedValue,
        overrideReason: captureMethod === 'manual' ? reason.trim() : null,
      }),
    );

  const accept = (decoded: string, method: 'scan' | 'image' | 'screen'): boolean => {
    const scanned = SpeedQr.extractTracking(decoded);

    if (scanned === expected) {
      receive(method, decoded);

      return true;
    }

    setError(
      scanned
        ? `That QR is for a different request (${scanned}). Use this request's own QR.`
        : SpeedQr.FOREIGN_CODE_MESSAGE,
    );

    return false;
  };

  const toggleScan = (): void => {
    setError(null);
    setManualOpen(false);

    if (scanning) {
      void SpeedQr.stop();
      setScanning(false);

      return;
    }

    setScanning(true);

    SpeedQr.start(
      'custodyReader',
      (decoded) => {
        void SpeedQr.stop();
        setScanning(false);
        accept(decoded, 'scan');
      },
      (cameraError) => {
        setScanning(false);
        setManualOpen(true);
        setError(SpeedQr.describe(cameraError));
      },
    );
  };

  const readFile = async (file: File | null | undefined): Promise<void> => {
    if (!file || pending) {
      return;
    }

    setError(null);
    setReading(true);

    try {
      const decoded = await SpeedQr.decodeImage(file);
      accept(decoded, 'image');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No QR code was found in that picture.');
    } finally {
      setReading(false);
    }
  };

  /**
   * The snipping tool: dims the page and reads the QR out of the page's own
   * images, so a soft copy can be received with no camera and no permission
   * prompt. Paper is camera-only — a picture of a QR proves someone had the
   * file, not that the folder arrived.
   */
  const snip = async (mode: SpeedQr.SnipMode): Promise<void> => {
    if (pending) {
      return;
    }

    if (scanning) {
      void SpeedQr.stop();
      setScanning(false);
    }

    setError(null);

    if (mode === 'page') {
      await openAndWaitForQr(expected);
    }

    let accepted = false;

    try {
      const decoded = await SpeedQr.scanFromScreen(mode);

      if (decoded === null) {
        window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));

        return;
      }

      setReading(true);
      accepted = accept(decoded, 'screen');
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'No QR code was found in that box. Drag it around the whole QR.',
      );
    } finally {
      setReading(false);
      window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
    }
  };

  return (
    <section className="dm-card dm-page-card lg:sticky lg:top-24">
      <div className="dm-head">
        <div className="min-w-0">
          <h2 className="dm-section">
            {detail.isSoftCopy ? 'Receive this request' : 'Scan to take custody'}
          </h2>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            {detail.currentStep?.action}
          </p>
        </div>
      </div>

      <div className="dm-body space-y-4">
        <Feedback error={error} notice={notice} />

        <NextSteps
          steps={[
            {
              label: detail.isSoftCopy ? "Scan the request's QR to receive it" : 'Scan the QR on the document to take custody',
              hint: (
                <>
                  Click <b>Select the QR on screen</b> below and drag a box around the QR
                  {detail.isSoftCopy ? ' — or use the camera.' : ' — or press I have this folder to use the camera.'}
                </>
              ),
              done: false,
            },
            {
              label: detail.canAct
                ? 'Review it, then approve, return or deny it'
                : 'Your supervisor reviews it and decides',
              done: false,
            },
          ]}
        />

        {detail.isSoftCopy ? (
          <p className="text-[length:var(--fs-small)] text-ink-soft">
            {detail.currentStep?.recipient
              ? 'This request was sent to you.'
              : 'This request was sent to your office.'}{' '}
            Scan its QR to receive it: click <b className="text-ink">Select the QR on screen</b> —
            the QR opens and the page dims — and drag a box around the QR. Or use the camera. Then
            you can approve, return or deny it.
          </p>
        ) : detail.canAct ? (
          <p className="text-[length:var(--fs-small)] text-ink-soft">
            Your office holds this hop, but you must{' '}
            <b className="text-ink">scan the document&apos;s QR Code</b> to confirm the paper is
            physically here before you can approve, return, or deny it.
          </p>
        ) : (
          <p className="text-[length:var(--fs-small)] text-ink-soft">
            This folder was sent to your office. If you have it,{' '}
            <b className="text-ink">scan its QR Code</b> to record that it arrived. Your supervisor
            then approves, returns or denies it.
          </p>
        )}

        <div className={`custody-box ${stepFocus(true)}`}>
          <div className="custody-strip">
            <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 7h5l2 3h11v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z" />
            </svg>
            {detail.isSoftCopy ? (
              <span>Soft copy — no paper folder. Receive it by scanning its QR.</span>
            ) : detail.heldBy ? (
              <span>
                Folder with <b>{detail.heldBy.name ?? 'staff'}</b> ({detail.heldBy.how})
              </span>
            ) : (
              <span>No physical custody recorded yet.</span>
            )}
            <button
              type="button"
              className="cr-btn cr-btn-sm"
              style={{ marginLeft: 'auto' }}
              disabled={pending}
              onClick={toggleScan}
            >
              {scanning
                ? 'Cancel scan'
                : detail.isSoftCopy
                  ? 'Scan with camera'
                  : 'I have this folder'}
            </button>
          </div>

          <div className="qr-drop">
            <svg fill="none" stroke="currentColor" strokeWidth="1.9" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 9h2v2H9zM13 9h2v2h-2zM9 13h2v2H9zM13 13h2v2h-2z" />
            </svg>
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending || reading}
              onClick={() => void snip('page')}
            >
              {reading ? 'Reading the QR…' : 'Select the QR on screen'}
            </button>
            <span className="qr-drop-hint">
              Opens the attached document with its QR code — drag a box around the QR to receive it.
            </span>
            <span className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
              <button
                type="button"
                className="qr-drop-alt"
                disabled={pending || reading}
                onClick={() => void snip('any')}
              >
                QR open in another window?
              </button>
              <span className="text-ink-soft" aria-hidden="true">
                ·
              </span>
              <label className="qr-drop-alt">
                <input
                  type="file"
                  accept="image/*"
                  className="sr-only"
                  onChange={(event) => {
                    void readFile(event.target.files?.[0]);
                    event.target.value = '';
                  }}
                />
                Choose a saved picture
              </label>
            </span>
          </div>

          <CameraModal
            open={scanning}
            readerId="custodyReader"
            title={detail.isSoftCopy ? 'Receive this request' : 'Scan to take custody'}
            hint={
              <>
                Point the camera at{' '}
                <b>{detail.isSoftCopy ? "this request's QR" : "this folder's QR"}</b> to confirm you{' '}
                {detail.isSoftCopy ? 'received it' : 'physically hold it'}.
              </>
            }
            onClose={() => {
              void SpeedQr.stop();
              setScanning(false);
            }}
          />

          {/* Audited fallback for paper folders when QR is torn or machine has no camera. */}
          {!detail.isSoftCopy ? (
            <div className="custody-manual">
              <button
                type="button"
                className="cs-manual-link"
                onClick={() => {
                  if (scanning) {
                    void SpeedQr.stop();
                    setScanning(false);
                  }
                  setError(null);
                  setManualOpen(!manualOpen);
                }}
              >
                QR torn or unreadable? Record manually
              </button>
              {manualOpen ? (
                <div className="cs-manual-form">
                  <label htmlFor="custody-reason">
                    Why can&apos;t the QR be scanned? (required)
                    <textarea
                      id="custody-reason"
                      rows={2}
                      maxLength={500}
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                      placeholder="e.g. QR sticker torn off the folder"
                    />
                  </label>
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm"
                    disabled={pending || reason.trim() === ''}
                    onClick={() =>
                      void run(
                        () =>
                          receiveRequest(detail.id, {
                            captureMethod: 'manual',
                            scannedValue: null,
                            overrideReason: reason.trim(),
                          }),
                        {
                          title: 'Record custody without a scan?',
                          message: 'The reason you gave is kept on the audit trail.',
                          confirmLabel: 'Record custody',
                        },
                      )
                    }
                  >
                    Record custody manually
                  </button>
                </div>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>
    </section>
  );
}

/**
 * Displayed when the document has been received / taken into custody at this office,
 * but the current user does not hold decision privileges (e.g. staff member whose
 * department supervisor must sign off on the step).
 *
 * Keeps the action panel visible, confirms physical custody with clear audit trail,
 * and maintains essential staff actions: on-screen QR viewing, slip printing,
 * and custody re-scan / handover.
 */
function CustodyStatusCard({ detail }: { detail: InternalRequestDetail }) {
  const { pending, error, notice, run, setError } = useAction();
  const [scanning, setScanning] = useState(false);
  const [reading, setReading] = useState(false);
  const [reScanOpen, setReScanOpen] = useState(false);
  const [manualOpen, setManualOpen] = useState(false);
  const [reason, setReason] = useState('');

  const expected = detail.trackingNumber.toUpperCase();

  const receive = (
    captureMethod: 'scan' | 'image' | 'screen' | 'manual',
    scannedValue: string | null = null,
  ): void =>
    void run(() =>
      receiveRequest(detail.id, {
        captureMethod,
        scannedValue,
        overrideReason: captureMethod === 'manual' ? reason.trim() : null,
      }),
    );

  const accept = (decoded: string, method: 'scan' | 'image' | 'screen'): boolean => {
    const scanned = SpeedQr.extractTracking(decoded);

    if (scanned === expected) {
      receive(method, decoded);
      setReScanOpen(false);
      return true;
    }

    setError(
      scanned
        ? `That QR is for a different request (${scanned}). Use this request's own QR.`
        : SpeedQr.FOREIGN_CODE_MESSAGE,
    );

    return false;
  };

  const toggleScan = (): void => {
    setError(null);
    setManualOpen(false);

    if (scanning) {
      void SpeedQr.stop();
      setScanning(false);
      return;
    }

    setScanning(true);

    SpeedQr.start(
      'custodyStatusReader',
      (decoded) => {
        void SpeedQr.stop();
        setScanning(false);
        accept(decoded, 'scan');
      },
      (cameraError) => {
        setScanning(false);
        setManualOpen(true);
        setError(SpeedQr.describe(cameraError));
      },
    );
  };

  const readFile = async (file: File | null | undefined): Promise<void> => {
    if (!file || pending) return;
    setError(null);
    setReading(true);

    try {
      const decoded = await SpeedQr.decodeImage(file);
      accept(decoded, 'image');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No QR code was found in that picture.');
    } finally {
      setReading(false);
    }
  };

  const snip = async (mode: SpeedQr.SnipMode): Promise<void> => {
    if (pending) return;
    if (scanning) {
      void SpeedQr.stop();
      setScanning(false);
    }

    setError(null);

    if (mode === 'page') {
      await openAndWaitForQr(expected);
    }

    try {
      const decoded = await SpeedQr.scanFromScreen(mode);
      if (decoded === null) {
        window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
        return;
      }

      setReading(true);
      accept(decoded, 'screen');
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'No QR code was found in that box. Drag it around the whole QR.',
      );
    } finally {
      setReading(false);
      window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
    }
  };

  return (
    <section className="dm-card dm-page-card lg:sticky lg:top-24">
      <div className="dm-head">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="pill pill-green flex items-center gap-1">
              <svg width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              In Office Custody
            </span>
          </div>
          <h2 className="dm-section mt-1">
            {detail.isSoftCopy ? 'Received & In Queue' : 'Folder in Custody'}
          </h2>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            {detail.currentStep?.office} · {detail.currentStep?.action}
          </p>
        </div>
      </div>

      <div className="dm-body space-y-4">
        <Feedback error={error} notice={notice} />

        <NextSteps
          steps={[
            { label: detail.isSoftCopy ? 'Received' : 'Document received', done: true },
            {
              label: detail.currentStep?.recipient
                ? `${detail.currentStep.recipient} reviews it and decides`
                : "Your office's supervisor reviews it and decides",
              hint: 'Nothing more for you to do here — they approve, return or deny it.',
              done: false,
              waiting: true,
            },
          ]}
        />

        <div className="dm-callout dm-callout-green" role="status">
          <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p className="font-semibold text-ink">
              {detail.heldBy?.name
                ? `Custody confirmed with ${detail.heldBy.name}`
                : 'Physical custody confirmed with your office'}
            </p>
            <p className="text-xs text-ink-soft mt-0.5">
              Verified via {detail.heldBy?.how ?? 'QR scan'}. The physical folder is at {detail.currentStep?.office}.
            </p>
          </div>
        </div>

        <p className="text-[length:var(--fs-small)] text-ink-soft">
          This request has been verified and received by your office. Official sign-off (approval,
          return for revision, or completion) rests with your department supervisor.
        </p>

        <div className="rounded-xl border border-hairline bg-[#f8faf9] p-3 space-y-2.5">
          <p className="text-xs font-semibold text-ink uppercase tracking-wider">Office Actions</p>

          <div className="flex flex-col gap-2">
            <button
              type="button"
              className="cr-btn cr-btn-primary w-full justify-center"
              disabled={pending || reading}
              onClick={() => void snip('page')}
            >
              <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="w-4 h-4 mr-1.5" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3" />
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 9h2v2H9zM13 9h2v2h-2zM9 13h2v2H9zM13 13h2v2h-2z" />
              </svg>
              {reading ? 'Reading the QR…' : 'Select the QR on screen'}
            </button>

            <div className="grid grid-cols-2 gap-2">
              <a
                href={detail.stickerUrl}
                target="_blank"
                rel="noopener"
                className="cr-btn justify-center text-xs"
              >
                <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="w-3.5 h-3.5 mr-1" aria-hidden="true">
                  <path strokeLinejoin="round" d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2" />
                  <path strokeLinejoin="round" d="M7 14h10v6H7z" />
                </svg>
                Print slip
              </a>

              {detail.stampedAttachment ? (
                <a
                  href={detail.stampedAttachment.url}
                  target="_blank"
                  rel="noopener"
                  className="cr-btn justify-center text-xs"
                >
                  <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="w-3.5 h-3.5 mr-1" aria-hidden="true">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                  Open file
                </a>
              ) : (
                <button
                  type="button"
                  onClick={() => setReScanOpen(!reScanOpen)}
                  className="cr-btn justify-center text-xs"
                >
                  {reScanOpen ? 'Close re-scan' : 'Re-scan / Update'}
                </button>
              )}
            </div>

            {detail.stampedAttachment ? (
              <button
                type="button"
                onClick={() => setReScanOpen(!reScanOpen)}
                className="cr-btn cr-btn-sm justify-center text-xs text-ink-soft hover:text-ink mt-0.5"
              >
                <svg fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" className="w-3.5 h-3.5 mr-1" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {reScanOpen ? 'Hide re-scan options' : 'Update custody / Re-scan'}
              </button>
            ) : null}
          </div>
        </div>

        {reScanOpen ? (
          <div className="custody-box mt-3 pt-3 border-t border-hairline">
            <p className="text-xs text-ink-soft mb-2 font-medium">Re-scan or transfer physical custody:</p>
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                className="cr-btn cr-btn-sm"
                disabled={pending}
                onClick={toggleScan}
              >
                {scanning ? 'Cancel camera' : 'Scan with camera'}
              </button>
              <label className="cr-btn cr-btn-sm cursor-pointer">
                <input
                  type="file"
                  accept="image/*"
                  className="sr-only"
                  onChange={(event) => {
                    void readFile(event.target.files?.[0]);
                    event.target.value = '';
                  }}
                />
                Upload image
              </label>
              <button
                type="button"
                className="qr-drop-alt text-xs"
                disabled={pending || reading}
                onClick={() => void snip('any')}
              >
                QR in another window?
              </button>
            </div>

            <CameraModal
              open={scanning}
              readerId="custodyStatusReader"
              title="Re-scan this folder"
              hint="Point the camera at the QR on this folder."
              onClose={() => {
                void SpeedQr.stop();
                setScanning(false);
              }}
            />

            {!detail.isSoftCopy ? (
              <div className="custody-manual mt-2 pt-2 border-t border-hairline">
                <button
                  type="button"
                  className="cs-manual-link"
                  onClick={() => {
                    if (scanning) {
                      void SpeedQr.stop();
                      setScanning(false);
                    }
                    setError(null);
                    setManualOpen(!manualOpen);
                  }}
                >
                  QR torn or unreadable? Record manually
                </button>
                {manualOpen ? (
                  <div className="cs-manual-form mt-2">
                    <label htmlFor="custody-status-reason">
                      Why can&apos;t the QR be scanned? (required)
                      <textarea
                        id="custody-status-reason"
                        rows={2}
                        maxLength={500}
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        placeholder="e.g. QR sticker torn off the folder"
                      />
                    </label>
                    <button
                      type="button"
                      className="cr-btn cr-btn-sm"
                      disabled={pending || reason.trim() === ''}
                      onClick={() =>
                      void run(
                        () =>
                          receiveRequest(detail.id, {
                            captureMethod: 'manual',
                            scannedValue: null,
                            overrideReason: reason.trim(),
                          }),
                        {
                          title: 'Record custody without a scan?',
                          message: 'The reason you gave is kept on the audit trail.',
                          confirmLabel: 'Record custody',
                        },
                      )
                    }
                    >
                      Record custody manually
                    </button>
                  </div>
                ) : null}
              </div>
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  );
}

type Mode = 'approve' | 'done' | 'return' | 'deny';

/** The office holding the request decides. Four actions, one form. */
function DecisionCard({ detail }: { detail: InternalRequestDetail }) {
  const { pending, error, notice, run, setError } = useAction();
  const [mode, setMode] = useState<Mode>('approve');
  const [remarks, setRemarks] = useState('');
  const [denyAck, setDenyAck] = useState(false);
  const [nextDepartment, setNextDepartment] = useState('');
  const [nextRecipient, setNextRecipient] = useState('');
  const [scanning, setScanning] = useState(false);
  const [reading, setReading] = useState(false);
  const [payload, setPayload] = useState('');
  const [manualOpen, setManualOpen] = useState(false);
  const [override, setOverride] = useState('');
  // The stamp is optional: the office ticks it when it wants one.
  const [stampWanted, setStampWanted] = useState(false);
  const [stampChoice, setStampChoice] = useState<StampChoice | null>(null);

  const expected = detail.trackingNumber.toUpperCase();
  const people = nextDepartment ? (detail.forwardRecipients[Number(nextDepartment)] ?? []) : [];

  const acceptDecisionScan = (decoded: string): boolean => {
    const scanned = SpeedQr.extractTracking(decoded);

    if (!scanned) {
      setError(SpeedQr.FOREIGN_CODE_MESSAGE);
      return false;
    }

    if (scanned !== expected) {
      setError(`That QR is for ${scanned}. Scan the QR on this request's document.`);
      return false;
    }

    setPayload(decoded);
    return true;
  };

  const snipForDecision = async (snipMode: SpeedQr.SnipMode): Promise<void> => {
    if (pending) {
      return;
    }

    if (scanning) {
      void SpeedQr.stop();
      setScanning(false);
    }

    setError(null);

    if (snipMode === 'page') {
      await openAndWaitForQr(expected);
    }

    let accepted = false;

    try {
      const decoded = await SpeedQr.scanFromScreen(snipMode);

      if (decoded === null) {
        window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
        return;
      }

      setReading(true);
      accepted = acceptDecisionScan(decoded);
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : 'No QR code was found in that box. Drag it around the whole QR.',
      );
    } finally {
      setReading(false);
      window.dispatchEvent(new CustomEvent('hide-request-qr', { detail: expected }));
    }
  };

  const readFileForDecision = async (file: File | null | undefined): Promise<void> => {
    if (!file || pending) {
      return;
    }

    setError(null);
    setReading(true);

    try {
      const decoded = await SpeedQr.decodeImage(file);
      acceptDecisionScan(decoded);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No QR code was found in that picture.');
    } finally {
      setReading(false);
    }
  };

  const input = {
    remarks: remarks.trim() || null,
    documentScan: payload || null,
    scanOverrideReason: override.trim() || null,
    nextDepartmentId: nextDepartment ? Number(nextDepartment) : null,
    nextRecipientId: nextRecipient ? Number(nextRecipient) : null,
    stamp: mode === 'approve' && stampWanted && detail.stampTarget ? stampChoice : null,
  };

  const submit = (): void => {
    if (mode === 'approve') {
      void run(() => approveStep(detail.id, input), {
        title: 'Approve and sign this step?',
        message: input.stamp
          ? 'Your e-signature is placed on it, your office’s RECEIVED stamp goes on a new copy of the file, and the request moves to the next office.'
          : 'Your e-signature is placed on it and the request moves to the next office.',
        confirmLabel: 'Approve & sign',
      });
    } else if (mode === 'done') {
      void run(() => completeStep(detail.id, input), {
        title: 'Mark this request as done?',
        message: 'This completes the request. The filing office is told.',
        confirmLabel: 'Mark as done',
      });
    } else if (mode === 'return') {
      void run(() => returnStep(detail.id, input), {
        title: 'Return this request for revision?',
        message: 'It goes back to the office that filed it.',
        confirmLabel: 'Return',
      });
    } else {
      void run(() => denyStep(detail.id, input), {
        title: 'Deny this request?',
        message: 'Denying ends the request for good.',
        confirmLabel: 'Deny request',
        tone: 'danger',
      });
    }
  };

  const startCamera = (): void => {
    setError(null);
    setScanning(true);

    SpeedQr.start(
      'folderReader',
      (decoded) => {
        // The same rules the server applies: a code this system issued, and
        // this request's — not the folder next to it.
        const scanned = SpeedQr.extractTracking(decoded);

        if (!scanned) {
          setError(SpeedQr.FOREIGN_CODE_MESSAGE);

          return;
        }

        if (scanned !== detail.trackingNumber.toUpperCase()) {
          setError(`That QR is for ${scanned}. Scan the sticker on this request's folder.`);

          return;
        }

        void SpeedQr.stop();
        setScanning(false);
        setPayload(decoded);
      },
      (cameraError) => {
        setScanning(false);
        setError(SpeedQr.describe(cameraError));
      },
    );
  };

  const needsReason = mode === 'return' || mode === 'deny';

  // The guide: received → (paper) scan the document's QR → choose → (approve
  // without an e-signature) register one → press the button.
  const scanned = payload !== '' || (manualOpen && override.trim().length >= 10);
  const chosen =
    mode === 'approve'
      ? detail.queuedNext !== null || (nextDepartment !== '' && (!detail.isSoftCopy || nextRecipient !== ''))
      : mode === 'deny'
        ? remarks.trim() !== '' && denyAck
        : mode === 'return'
          ? remarks.trim() !== ''
          : true;
  const buttonLabel =
    mode === 'approve'
      ? 'Approve & sign'
      : mode === 'done'
        ? 'Mark as done'
        : mode === 'return'
          ? 'Return for revision'
          : 'Deny request';
  const guide: Array<NextStep & { key: string }> = [
    { key: 'received', label: detail.isSoftCopy ? 'Received' : 'Document received', done: true },
    ...(detail.isSoftCopy
      ? []
      : [
          {
            key: 'scan',
            label: 'Scan the QR on the document to confirm',
            hint: <>Click <b>Select the QR on screen</b> and drag a box around it, or use the camera.</>,
            done: scanned,
          },
        ]),
    {
      key: 'choose',
      label: 'Choose what to do — Approve, Return or Deny',
      hint:
        mode === 'approve' && !detail.queuedNext
          ? 'Pick the office it goes to next.'
          : mode === 'return' || mode === 'deny'
            ? `Write the reason — ${detail.filedByOffice ?? 'the filing office'} sees it.${mode === 'deny' ? ' Then tick the box.' : ''}`
            : undefined,
      done: chosen,
    },
    ...(mode === 'approve' && !detail.hasSignature
      ? [
          {
            key: 'signature',
            label: 'Register your e-signature on your Profile',
            hint: (
              <>
                Approving signs with it.{' '}
                <a href="/profile" className="cr-link">
                  Open your Profile
                </a>
              </>
            ),
            done: false,
          },
        ]
      : []),
    { key: 'submit', label: <>Press <b>{buttonLabel}</b></>, done: false },
  ];
  const nowKey = guide[currentStepIndex(guide)]?.key;

  return (
    <section className="dm-card dm-page-card dm-decision lg:sticky lg:top-24">
      <div className="dm-head">
        <div className="min-w-0">
          <h2 className="dm-section">
            {detail.currentStep?.recipientId !== null && detail.currentStep?.recipient
              ? 'This request was sent to you'
              : 'Your office holds this request'}
          </h2>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            {detail.currentStep?.action}
          </p>
        </div>
      </div>

      <div className="dm-body space-y-4">
        <Feedback error={error} notice={notice} />

        {detail.heldBy ? (
          <div className="dm-callout dm-callout-green" role="status">
            <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 5 5 9-10" />
            </svg>
            <p>
              {detail.isSoftCopy ? 'Received by' : 'Document in hand — held by'}{' '}
              <b>{detail.heldBy.name ?? 'your office'}</b> ({detail.heldBy.how}).
            </p>
          </div>
        ) : null}

        {!detail.hasSignature ? (
          <div className="dm-callout dm-callout-amber" role="alert">
            <p>
              You have no registered e-signature yet, so you cannot approve.{' '}
              <a href="/profile" className="cr-link">
                Register it on your Profile
              </a>{' '}
              first — you can still return or deny.
            </p>
          </div>
        ) : null}

        <NextSteps steps={guide} />

        {/* The decision is confirmed against the folder itself. A soft copy has
            no folder, so there is nothing to scan. */}
        {detail.isSoftCopy ? (
          <p className="dm-note">
            Soft copy only — there is no paper folder to scan. Your decision is recorded under your
            name.
          </p>
        ) : (
          <div className={`rounded-xl p-3 ${stepFocus(nowKey === 'scan')}`}>
            <p className="gate-claim-label">
              Scan this request&apos;s QR <span className="text-status-red">*</span>
            </p>

            {payload ? (
              <p className="mt-2 flex items-center gap-2 text-[length:var(--fs-small)] font-semibold text-green-deep">
                <svg width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2.4" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 5 5 9-10" />
                </svg>
                Request QR scanned — ready to sign
                <button
                  type="button"
                  className="text-[length:var(--fs-caption)] font-normal text-ink-soft underline"
                  onClick={() => setPayload('')}
                >
                  rescan
                </button>
              </p>
            ) : (
              <div className="mt-2 space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <button
                    type="button"
                    className="cr-btn cr-btn-primary cr-btn-sm"
                    disabled={pending || reading}
                    onClick={() => void snipForDecision('page')}
                  >
                    {reading ? 'Reading the QR…' : 'Select the QR on screen'}
                  </button>
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm"
                    disabled={pending || reading}
                    onClick={() =>
                      scanning ? (void SpeedQr.stop(), setScanning(false)) : startCamera()
                    }
                  >
                    {scanning ? 'Cancel scan' : 'Scan with camera'}
                  </button>
                  <label className="cr-btn cr-btn-sm cursor-pointer">
                    <input
                      type="file"
                      accept="image/*"
                      className="sr-only"
                      onChange={(event) => {
                        void readFileForDecision(event.target.files?.[0]);
                        event.target.value = '';
                      }}
                    />
                    Choose picture
                  </label>
                </div>

                <CameraModal
                  open={scanning}
                  readerId="folderReader"
                  title="Scan this folder"
                  hint="Point the camera at the QR sticker on this folder."
                  error={error}
                  onClose={() => {
                    void SpeedQr.stop();
                    setScanning(false);
                  }}
                />

                <div className="mt-2">
                  <button
                    type="button"
                    className="text-[length:var(--fs-caption)] font-semibold text-ink-soft underline"
                    onClick={() => setManualOpen(!manualOpen)}
                  >
                    QR torn, or no camera on this machine? Confirm manually
                  </button>

                  {manualOpen ? (
                    <div className="mt-2">
                      <label className="gate-claim-label" htmlFor="scan-override">
                        Why can&apos;t you scan? <span className="text-status-red">*</span>
                      </label>
                      <textarea
                        id="scan-override"
                        className="st-filter-field mt-1 h-auto py-2"
                        rows={2}
                        minLength={10}
                        maxLength={500}
                        value={override}
                        onChange={(event) => setOverride(event.target.value)}
                        placeholder="e.g. Sticker torn on the folder cover; verified tracking number by hand."
                      />
                      <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                        This is recorded against the request and shown in its history.
                      </p>
                    </div>
                  ) : null}
                </div>
              </div>
            )}

            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              Sticker missing?{' '}
              <a href={detail.stickerUrl} target="_blank" rel="noopener" className="cr-link">
                Print the QR
              </a>
              .
            </p>
          </div>
        )}

        <div className="st-tabs dm-decision-tabs w-full" role="tablist" aria-label="Decision">
          {(
            [
              ['approve', 'Approve'],
              // Done is the only action that completes a request: approving
              // just passes the paper along, and the chain grows as each office
              // scans it. Not while the filed route has offices left to go.
              ...(detail.queuedNext ? [] : ([['done', 'Done']] as const)),
              ['return', 'Return'],
              ['deny', 'Deny'],
            ] as const
          ).map(([key, label]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={mode === key}
              className={`flex-1 justify-center${mode === key ? ' on' : ''}`}
              onClick={() => {
                setMode(key);
                setDenyAck(false);
              }}
            >
              {label}
            </button>
          ))}
        </div>

        <p className="text-[length:var(--fs-small)] text-ink-soft">
          {mode === 'approve'
            ? detail.queuedNext
              ? 'Approving affixes your registered e-signature and passes the request to the next office on its route.'
              : 'Approving affixes your registered e-signature and passes the request to the office you choose below.'
            : mode === 'done'
              ? 'Marking this done completes the request — use it when your office is the last to handle the paper.'
              : mode === 'return'
                ? `Returning sends the request back to ${detail.filedByOffice ?? 'the filing office'} for revision.`
                : 'Denying ends this request permanently. The filing office is notified.'}
        </p>

        {mode === 'approve' ? (
          detail.queuedNext ? (
            <p className="dm-note">
              Next on this request's route:{' '}
              <b>{detail.queuedNext.office}</b> <span className="stamp">{detail.queuedNext.officeCode}</span>
            </p>
          ) : (
            <div className={stepFocus(nowKey === 'choose')}>
              <label className="gate-claim-label" htmlFor="next-department">
                Send to which office next? <span className="text-status-red">*</span>
              </label>
              <select
                id="next-department"
                className="st-filter-field mt-1"
                value={nextDepartment}
                onChange={(event) => {
                  setNextDepartment(event.target.value);
                  setNextRecipient('');
                }}
              >
                <option value="">Select a department…</option>
                {detail.forwardDepartments.map((office) => (
                  <option key={office.id} value={office.id}>
                    {office.name} ({office.code})
                  </option>
                ))}
              </select>
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                If your office is the last to handle this, use <strong>Done</strong> instead.
              </p>

              {/* No paper to hand across, so name who receives it there — as
                  the filing office did for the first hop. */}
              {detail.isSoftCopy ? (
                <div className="mt-3">
                  <label className="gate-claim-label" htmlFor="next-recipient">
                    Who in that office receives it? <span className="text-status-red">*</span>
                  </label>
                  <select
                    id="next-recipient"
                    className="st-filter-field mt-1"
                    value={nextRecipient}
                    disabled={nextDepartment === ''}
                    onChange={(event) => setNextRecipient(event.target.value)}
                  >
                    <option value="">
                      {nextDepartment ? 'Select a person…' : 'Choose the office first'}
                    </option>
                    {people.map((person) => (
                      <option key={person.id} value={person.id}>
                        {person.name} · {person.role}
                      </option>
                    ))}
                  </select>
                  <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                    This request has no paper copy, so it goes straight to the person you choose.
                  </p>
                </div>
              ) : null}
            </div>
          )
        ) : null}

        {mode === 'approve' ? (
          <div>
            <label className="flex items-start gap-2 text-[length:var(--fs-small)] font-semibold text-ink">
              <input
                type="checkbox"
                checked={stampWanted}
                disabled={!detail.stampTarget}
                onChange={(event) => setStampWanted(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-hairline-strong text-green"
              />
              <span>
                Stamp the file RECEIVED <span className="font-normal text-ink-soft">(optional)</span>
              </span>
            </label>
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              {detail.stampTarget
                ? 'Your e-signature, name, and the date and time are filled in when you approve. Drag the stamp to a clear spot; it goes on a new copy, so earlier copies and stamps stay as they are.'
                : 'There is no image or PDF on this request to stamp.'}
            </p>
            {stampWanted && detail.stampTarget ? (
              <StampPlacer
                target={detail.stampTarget}
                office={detail.currentStep?.office ?? 'Your office'}
                stampsBefore={detail.officeStampCount}
                onChange={setStampChoice}
              />
            ) : null}
          </div>
        ) : null}

        <div className={stepFocus(nowKey === 'choose' && needsReason && remarks.trim() === '')}>
          <label className="gate-claim-label" htmlFor="req-remarks">
            Remarks {needsReason ? <span className="text-status-red">*</span> : <span className="text-ink-soft">(optional)</span>}
          </label>
          <textarea
            id="req-remarks"
            className="st-filter-field mt-1 h-auto py-2"
            rows={3}
            maxLength={500}
            value={remarks}
            onChange={(event) => setRemarks(event.target.value)}
            placeholder={
              needsReason
                ? 'Explain the decision so the filing office knows what to fix'
                : 'Optional note for the record'
            }
          />
        </div>

        {mode === 'deny' ? (
          <label
            className={`dm-callout dm-callout-red flex items-start gap-2 ${stepFocus(nowKey === 'choose' && remarks.trim() !== '' && !denyAck)}`}
          >
            <input
              type="checkbox"
              checked={denyAck}
              onChange={(event) => setDenyAck(event.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-hairline-strong text-status-red"
            />
            <span>
              I understand denying <strong>permanently ends</strong> this request and cannot be
              undone.
            </span>
          </label>
        ) : null}

        <button
          type="button"
          className={`cr-btn w-full justify-center ${
            mode === 'deny' ? 'cr-btn-danger' : mode === 'return' ? '' : 'cr-btn-primary'
          } ${nowKey === 'submit' ? 'ns-focus-btn' : ''}`}
          disabled={
            pending ||
            (mode === 'approve' && !detail.hasSignature) ||
            (mode === 'deny' && !denyAck)
          }
          onClick={submit}
        >
          {pending
            ? 'Saving…'
            : mode === 'approve'
              ? 'Approve & sign'
              : mode === 'done'
                ? 'Mark as done — complete the request'
                : mode === 'return'
                  ? 'Return for revision'
                  : 'Deny request'}
        </button>
      </div>
    </section>
  );
}

/**
 * Returned to the office that filed it: revise and send it back to the office
 * that returned it (which receives it again), or close it.
 */
function ReviseCard({ detail }: { detail: InternalRequestDetail }) {
  const { pending, error, notice, run } = useAction();
  const [mode, setMode] = useState<'resend' | 'withdraw'>('resend');
  const [note, setNote] = useState('');
  const [reason, setReason] = useState('');
  const [ack, setAck] = useState(false);
  const [scan, setScan] = useState<File | null>(null);

  const office = detail.returnedStep?.office ?? 'the office that returned it';

  const resend = (): void => {
    const form = new FormData();
    form.set('note', note.trim());

    if (scan) {
      form.set('paper_scan', scan);
    }

    void run(() => resendRequest(detail.id, form), {
      title: `Resend to ${office}?`,
      message: 'They receive it again and pick up where they returned it.',
      confirmLabel: 'Resend',
    });
  };

  return (
    <section className="dm-card dm-page-card lg:sticky lg:top-24">
      <div className="dm-head">
        <div className="min-w-0">
          <h2 className="dm-section">Returned to your office</h2>
          <p className="text-[length:var(--fs-caption)] text-ink-soft">
            Revise it and send it back, or withdraw it
          </p>
        </div>
      </div>

      <div className="dm-body space-y-4">
        <Feedback error={error} notice={notice} />

        {detail.returnedStep ? (
          <div className="dm-note !mt-0">
            <p className="dm-note-label">
              Returned by {detail.returnedStep.actedBy ?? 'the office'} ·{' '}
              {detail.returnedStep.office}
            </p>
            <p className="whitespace-pre-line">
              {detail.returnedStep.remarks || 'No reason given.'}
            </p>
          </div>
        ) : null}

        <NextSteps
          steps={[
            { label: 'Read why it was returned', done: true },
            mode === 'resend'
              ? {
                  label: 'Say what you changed',
                  hint: 'Attach the revised scan too, if there is one.',
                  done: note.trim() !== '',
                }
              : { label: 'Tick the box to confirm', done: ack },
            {
              label: mode === 'resend' ? <>Press <b>Resend to {office}</b></> : <>Press <b>Withdraw request</b></>,
              done: false,
            },
          ]}
        />

        <div className="st-tabs dm-decision-tabs w-full" role="tablist" aria-label="What to do">
          {(
            [
              ['resend', 'Resend'],
              ['withdraw', 'Withdraw'],
            ] as const
          ).map(([key, label]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={mode === key}
              className={`flex-1 justify-center${mode === key ? ' on' : ''}`}
              onClick={() => {
                setMode(key);
                setAck(false);
              }}
            >
              {label}
            </button>
          ))}
        </div>

        {mode === 'resend' ? (
          <>
            <p className="text-[length:var(--fs-small)] text-ink-soft">
              Goes back to <span className="font-semibold text-ink">{office}</span>.{' '}
              {detail.isSoftCopy
                ? 'They receive it again, then decide.'
                : 'Take the revised paper there — they scan it in again, then decide.'}
            </p>

            <div className={stepFocus(note.trim() === '')}>
              <label className="gate-claim-label" htmlFor="resend-note">
                What did you change? <span className="text-status-red">*</span>
              </label>
              <textarea
                id="resend-note"
                className="st-filter-field mt-1 h-auto py-2"
                rows={3}
                maxLength={500}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="e.g. Attached the signed canvass and corrected the amount."
              />
            </div>

            <div>
              <label className="gate-claim-label" htmlFor="resend-scan">
                Revised scan <span className="text-ink-soft">(optional)</span>
              </label>
              <input
                id="resend-scan"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                className="mt-1 block w-full text-[13px] text-ink file:mr-3 file:rounded-full file:border-0 file:bg-green-wash file:px-3 file:py-1.5 file:text-[12.5px] file:font-semibold file:text-green-deep"
                onChange={(event) => setScan(event.target.files?.[0] ?? null)}
              />
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                PDF or picture. Added to the request&apos;s files; the original stays.
              </p>
            </div>

            <button
              type="button"
              className={`cr-btn cr-btn-primary w-full justify-center ${note.trim() !== '' ? 'ns-focus-btn' : ''}`}
              disabled={pending || note.trim() === ''}
              onClick={resend}
            >
              {pending ? 'Sending…' : `Resend to ${office}`}
            </button>
          </>
        ) : (
          <>
            <p className="text-[length:var(--fs-small)] text-ink-soft">
              Withdrawing closes this request for good. Nothing more happens to it; file a new one
              if it is needed later.
            </p>

            <div>
              <label className="gate-claim-label" htmlFor="withdraw-reason">
                Reason <span className="text-ink-soft">(optional)</span>
              </label>
              <textarea
                id="withdraw-reason"
                className="st-filter-field mt-1 h-auto py-2"
                rows={2}
                maxLength={500}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. No longer needed."
              />
            </div>

            <label className={`dm-callout dm-callout-red flex items-start gap-2 ${stepFocus(!ack)}`}>
              <input
                type="checkbox"
                checked={ack}
                onChange={(event) => setAck(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-hairline-strong text-status-red"
              />
              <span>
                I understand withdrawing <strong>closes</strong> this request and cannot be undone.
              </span>
            </label>

            <button
              type="button"
              className={`cr-btn cr-btn-danger w-full justify-center ${ack ? 'ns-focus-btn' : ''}`}
              disabled={pending || !ack}
              onClick={() =>
                void run(() => withdrawRequest(detail.id, reason.trim() || null), {
                  title: 'Withdraw this request?',
                  message: 'It is closed for good and cannot be reopened.',
                  confirmLabel: 'Withdraw request',
                  tone: 'danger',
                })
              }
            >
              {pending ? 'Saving…' : 'Withdraw request'}
            </button>
          </>
        )}
      </div>
    </section>
  );
}
