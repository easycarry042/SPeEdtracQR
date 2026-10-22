'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { InternalRequestDetail } from '@/lib/domain/internal-request';
import { QrCamera } from '@/components/qr-camera';
import * as SpeedQr from '@/lib/client/speed-qr';
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

/**
 * The three panels beside an internal request: receive it, decide on it, or —
 * when it has come back to the office that filed it — revise or withdraw it.
 *
 * Which one shows follows the same order the server enforces: receiving first
 * (anyone at the holding office, for paper), deciding only once the office has
 * custody. A viewer with nothing to do sees none of them.
 */
export function RequestPanels({ detail }: { detail: InternalRequestDetail }) {
  const showReceive = detail.canReceive && detail.currentStep !== null && !detail.hasCustody;
  const showDecision = detail.canAct && detail.currentStep !== null && detail.hasCustody;

  if (showReceive) {
    return <ReceiveCard detail={detail} />;
  }

  if (showDecision) {
    return <DecisionCard detail={detail} />;
  }

  if (detail.canRevise) {
    return <ReviseCard detail={detail} />;
  }

  return null;
}

/** Shared plumbing: run an action, show its refusal where the button is. */
function useAction() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const run = (action: () => Promise<InternalResult>): void => {
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
  const [manualOpen, setManualOpen] = useState(false);
  const [reason, setReason] = useState('');

  const receive = (
    captureMethod: 'scan' | 'image' | 'screen' | 'manual',
    scannedValue: string | null = null,
  ): void =>
    run(() =>
      receiveRequest(detail.id, {
        captureMethod,
        scannedValue,
        overrideReason: captureMethod === 'manual' ? reason.trim() : null,
      }),
    );

  const startCamera = (): void => {
    setError(null);
    setScanning(true);

    SpeedQr.start(
      'custodyReader',
      (decoded) => {
        const scanned = SpeedQr.extractTracking(decoded);

        if (!scanned) {
          setError(SpeedQr.FOREIGN_CODE_MESSAGE);

          return;
        }

        if (scanned !== detail.trackingNumber.toUpperCase()) {
          setError(`That QR is for ${scanned}. Scan the sticker on this request.`);

          return;
        }

        void SpeedQr.stop();
        setScanning(false);
        receive('scan', decoded);
      },
      (cameraError) => {
        setScanning(false);
        setError(SpeedQr.describe(cameraError));
      },
    );
  };

  /**
   * The snipping tool: dims the page and reads the QR out of the page's own
   * images, so a soft copy can be received with no camera and no permission
   * prompt. Paper is camera-only — a picture of a QR proves someone had the
   * file, not that the folder arrived.
   */
  const snip = async (mode: SpeedQr.SnipMode): Promise<void> => {
    setError(null);
    const decoded = await SpeedQr.scanFromScreen(mode);

    if (!decoded) {
      return;
    }

    const scanned = SpeedQr.extractTracking(decoded);

    if (scanned !== detail.trackingNumber.toUpperCase()) {
      setError(
        scanned
          ? `That QR is for ${scanned}. Select this request's QR.`
          : SpeedQr.FOREIGN_CODE_MESSAGE,
      );

      return;
    }

    receive('screen', decoded);
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

        <p className="text-[length:var(--fs-small)] text-ink-soft">
          {detail.isSoftCopy
            ? 'Scan its QR to receive it: open the QR and select it on screen, or use the camera. Then you can approve, return or deny it.'
            : detail.canAct
              ? "Your office holds this hop, but you must scan the document's QR code to confirm the paper is physically here before you can approve, return, or deny it."
              : 'This folder was sent to your office. If you have it, scan its QR code to record that it arrived. Your supervisor then approves, returns or denies it.'}
        </p>

        <div className="gate-actions">
          <button
            type="button"
            className="cr-btn cr-btn-primary cr-btn-sm"
            disabled={pending}
            onClick={() => (scanning ? (void SpeedQr.stop(), setScanning(false)) : startCamera())}
          >
            {scanning ? 'Cancel scan' : 'Scan with camera'}
          </button>

          {/* Soft copies only: there is no paper to point a camera at. */}
          {detail.isSoftCopy ? (
            <>
              <button
                type="button"
                className="cr-btn cr-btn-ghost cr-btn-sm"
                disabled={pending}
                onClick={() => void snip('page')}
              >
                Select the QR on screen
              </button>
              <button
                type="button"
                className="cr-btn cr-btn-ghost cr-btn-sm"
                disabled={pending}
                onClick={() => void snip('any')}
              >
                QR open in another window?
              </button>
            </>
          ) : null}
        </div>

        {scanning ? (
          <div>
            <QrCamera id="custodyReader" />
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              Point the camera at the QR sticker on this folder.
            </p>
          </div>
        ) : null}

        {/* Audited fallback. Without it a machine with no working camera cannot
            receive anything, which halts the office — worse than a logged
            manual confirmation. */}
        <div>
          <button
            type="button"
            className="text-[length:var(--fs-caption)] font-semibold text-ink-soft underline"
            onClick={() => setManualOpen(!manualOpen)}
          >
            QR torn, or no camera on this machine? Record it manually
          </button>

          {manualOpen ? (
            <div className="mt-2">
              <label className="gate-claim-label" htmlFor="custody-reason">
                Why can&apos;t you scan?
              </label>
              <textarea
                id="custody-reason"
                className="field"
                rows={2}
                maxLength={500}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. Sticker torn on the folder cover; verified the tracking number by hand."
              />
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                This is recorded against the request and shown in its history.
              </p>
              <button
                type="button"
                className="cr-btn cr-btn-sm mt-2"
                disabled={pending || reason.trim() === ''}
                onClick={() => receive('manual')}
              >
                Record custody manually
              </button>
            </div>
          ) : null}
        </div>

        <p className="text-[length:var(--fs-caption)] text-ink-soft">
          Sticker missing?{' '}
          <a href={detail.stickerUrl} target="_blank" rel="noopener" className="cr-link">
            Print the QR
          </a>
          .
        </p>
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
  const [payload, setPayload] = useState('');
  const [manualOpen, setManualOpen] = useState(false);
  const [override, setOverride] = useState('');

  const people = nextDepartment ? (detail.forwardRecipients[Number(nextDepartment)] ?? []) : [];

  const input = {
    remarks: remarks.trim() || null,
    documentScan: payload || null,
    scanOverrideReason: override.trim() || null,
    nextDepartmentId: nextDepartment ? Number(nextDepartment) : null,
    nextRecipientId: nextRecipient ? Number(nextRecipient) : null,
  };

  const submit = (): void => {
    if (mode === 'approve') {
      run(() => approveStep(detail.id, input));
    } else if (mode === 'done') {
      run(() => completeStep(detail.id, input));
    } else if (mode === 'return') {
      run(() => returnStep(detail.id, input));
    } else {
      run(() => denyStep(detail.id, input));
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

        <div className="st-tabs dm-decision-tabs w-full" role="tablist" aria-label="Decision">
          {(
            [
              ['approve', 'Approve'],
              // Done is the only action that completes a request: approving
              // just passes the paper along, and the chain grows as each office
              // scans it.
              ['done', 'Done'],
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
            ? 'Approving affixes your registered e-signature and passes the request to the office you choose below.'
            : mode === 'done'
              ? 'Marking this done completes the request — use it when your office is the last to handle the paper.'
              : mode === 'return'
                ? `Returning sends the request back to ${detail.filedByOffice ?? 'the filing office'} for revision.`
                : 'Denying ends this request permanently. The filing office is notified.'}
        </p>

        {mode === 'approve' ? (
          detail.queuedNext ? (
            <p className="dm-note">
              Next office (set when this request was filed):{' '}
              <b>{detail.queuedNext.office}</b> <span className="stamp">{detail.queuedNext.officeCode}</span>
            </p>
          ) : (
            <div>
              <label className="gate-claim-label" htmlFor="next-department">
                Send to which office next?
              </label>
              <select
                id="next-department"
                className="field"
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
                    Who in that office receives it?
                  </label>
                  <select
                    id="next-recipient"
                    className="field"
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
                </div>
              ) : null}
            </div>
          )
        ) : null}

        <div>
          <label className="gate-claim-label" htmlFor="req-remarks">
            Remarks {needsReason ? null : <span className="text-ink-soft">(optional)</span>}
          </label>
          <textarea
            id="req-remarks"
            className="field"
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
          <label className="dm-callout dm-callout-red flex items-start gap-2">
            <input
              type="checkbox"
              checked={denyAck}
              onChange={(event) => setDenyAck(event.target.checked)}
            />
            <span>
              I understand denying <strong>permanently ends</strong> this request and cannot be
              undone.
            </span>
          </label>
        ) : null}

        {/* The decision is confirmed against the folder itself. A soft copy has
            no folder, so there is nothing to scan. */}
        {detail.isSoftCopy ? (
          <p className="dm-note">
            Soft copy only — there is no paper folder to scan. Your decision is recorded under your
            name.
          </p>
        ) : (
          <div>
            <p className="gate-claim-label">Scan this request&apos;s QR</p>

            {payload ? (
              <p className="mt-2 flex items-center gap-2 text-[length:var(--fs-small)] font-semibold text-green-deep">
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
              <div className="mt-2">
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  onClick={() =>
                    scanning ? (void SpeedQr.stop(), setScanning(false)) : startCamera()
                  }
                >
                  {scanning ? 'Cancel scan' : 'Scan request QR'}
                </button>

                {scanning ? (
                  <div className="mt-3">
                    <QrCamera id="folderReader" />
                    <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                      Point the camera at the QR sticker on this folder.
                    </p>
                  </div>
                ) : null}

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
                        Why can&apos;t you scan?
                      </label>
                      <textarea
                        id="scan-override"
                        className="field"
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

        <button
          type="button"
          className={`cr-btn w-full justify-center ${
            mode === 'deny' ? 'cr-btn-danger' : mode === 'return' ? '' : 'cr-btn-primary'
          }`}
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

    run(() => resendRequest(detail.id, form));
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
          <div className="dm-note">
            <p className="dm-note-label">
              Returned by {detail.returnedStep.actedBy ?? 'the office'} ·{' '}
              {detail.returnedStep.office}
            </p>
            <p className="whitespace-pre-line">
              {detail.returnedStep.remarks || 'No reason given.'}
            </p>
          </div>
        ) : null}

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
              Goes back to <b>{office}</b>.{' '}
              {detail.isSoftCopy
                ? 'They receive it again, then decide.'
                : 'Take the revised paper there — they scan it in again, then decide.'}
            </p>

            <div>
              <label className="gate-claim-label" htmlFor="resend-note">
                What did you change?
              </label>
              <textarea
                id="resend-note"
                className="field"
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
                className="field"
                onChange={(event) => setScan(event.target.files?.[0] ?? null)}
              />
              <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
                PDF or picture. Added to the request&apos;s files; the original stays.
              </p>
            </div>

            <button
              type="button"
              className="cr-btn cr-btn-primary w-full justify-center"
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
                className="field"
                rows={2}
                maxLength={500}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                placeholder="e.g. No longer needed."
              />
            </div>

            <label className="dm-callout dm-callout-red flex items-start gap-2">
              <input
                type="checkbox"
                checked={ack}
                onChange={(event) => setAck(event.target.checked)}
              />
              <span>
                I understand withdrawing <strong>closes</strong> this request and cannot be undone.
              </span>
            </label>

            <button
              type="button"
              className="cr-btn cr-btn-danger w-full justify-center"
              disabled={pending || !ack}
              onClick={() => run(() => withdrawRequest(detail.id, reason.trim() || null))}
            >
              {pending ? 'Saving…' : 'Withdraw request'}
            </button>
          </>
        )}
      </div>
    </section>
  );
}
