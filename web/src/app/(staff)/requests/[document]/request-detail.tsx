import Link from 'next/link';
import { DocumentStatus } from '@/lib/domain/document-status';
import type { InternalRequestDetail } from '@/lib/domain/internal-request';
import { formatNaive } from '@/lib/time';
import { QrTile, RequestPanels } from './panels';

/**
 * One internal request: the endorsement chain, who has held the paper, and —
 * for whoever can act — the receive, decision or revise panel beside it.
 *
 * Shared by the Internal page's request modal (`/requests?open=…`) and the
 * full page at `/requests/[document]`; `closeHref` is where the ✕ goes.
 */
export function InternalRequestView({
  detail,
  closeHref,
}: {
  detail: InternalRequestDetail;
  closeHref: string;
}) {
  const hasPanel =
    (detail.canReceive && detail.currentStep !== null) ||
    (detail.canAct && detail.currentStep !== null) ||
    detail.canRevise;

  const isClosed = (
    [
      DocumentStatus.Completed,
      DocumentStatus.Denied,
      DocumentStatus.Withdrawn,
      DocumentStatus.Returned,
    ] as string[]
  ).includes(detail.status);

  const showOpenEndedNext = !detail.currentStep && !isClosed;

  return (
    <div className="page-shell page-shell-loose">
      <div
        className={`grid grid-cols-1 items-start gap-5${
          hasPanel ? ' lg:grid-cols-[minmax(0,1fr)_420px]' : ''
        }`}
      >
        <section
          className={`dm-card dm-page-card${hasPanel ? '' : ' mx-auto w-full max-w-[760px]'}`}
          aria-label={`Internal request ${detail.trackingNumber}`}
        >
          <div className="dm-head">
            <span className={`pill ${pillFor(detail.band)}`}>{detail.statusLabel}</span>
            <Link
              href={closeHref}
              scroll={false}
              className="dm-close"
              aria-label="Close"
              title="Close"
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
            </Link>
          </div>

          <div className="dm-body">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <h2 id={`internal-${detail.id}-title`} className="dm-title">
                  {detail.purpose}
                </h2>
                <p className="dm-subtitle">
                  Internal Request
                  {detail.filedByOffice ? ` · ${detail.filedByOffice}` : ''}
                </p>
              </div>

              <QrTile
                trackingNumber={detail.trackingNumber}
                qrUrl={detail.qrUrl}
                stickerUrl={detail.stickerUrl}
                stampedAttachment={detail.stampedAttachment}
              />
            </div>

            {/* Where it stands, when this viewer has nothing to do about it. */}
            {!hasPanel ? (
              <p className="dm-callout dm-callout-muted mt-5">
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="9" />
                  <path strokeLinecap="round" d="M12 7.5V12l3 2" />
                </svg>
                <span>{waitingLine(detail)}</span>
              </p>
            ) : null}

            <section className="mt-7">
              <h2 className="dm-section">Endorsement Chain</h2>
              <div className="mt-3">
                <ol className="text-ink">
                  <li className="flex gap-3">
                    <div className="flex flex-col items-center">
                      <span className="flex h-8 w-8 items-center justify-center rounded-full bg-green text-xs font-bold text-white">
                        ✓
                      </span>
                      {detail.chain.length > 0 || showOpenEndedNext ? (
                        <span className="w-px flex-1 bg-green-wash" />
                      ) : null}
                    </div>
                    <div className="pb-5">
                      <p className="text-[13px] font-semibold text-ink">
                        {detail.filedByOffice ?? 'Filing office'}
                      </p>
                      <p className="text-[12px] text-ink-soft">
                        {detail.filedBy ? (
                          <>
                            Filed by <span className="font-medium text-ink">{detail.filedBy}</span>
                          </>
                        ) : (
                          'Filed the request'
                        )}{' '}
                        · {detail.filedOn}
                      </p>
                    </div>
                  </li>

                  {detail.chain.map((step, index) => {
                    const isCurrent = step.status === 'current';
                    const isApproved = step.status === 'approved';
                    const isHalted = step.status === 'denied' || step.status === 'returned';
                    const isForwarded = step.status === 'forwarded';
                    const isLast = index === detail.chain.length - 1 && !showOpenEndedNext;
                    const badgeLabel =
                      isCurrent && !detail.hasCustody ? 'Awaiting receipt' : step.statusLabel;

                    return (
                      <li key={step.id} className="flex gap-3">
                        <div className="flex flex-col items-center">
                          {isCurrent ? (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brass text-xs font-bold text-white ring-4 ring-status-amber-wash">
                              {index + 1}
                            </span>
                          ) : isApproved ? (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-green text-xs font-bold text-white">
                              ✓
                            </span>
                          ) : isForwarded ? (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-green-wash text-xs font-bold text-green-deep ring-1 ring-green/40">
                              →
                            </span>
                          ) : isHalted ? (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-status-red text-xs font-bold text-white">
                              ✕
                            </span>
                          ) : (
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[#eef2ef] text-xs font-bold text-ink-soft ring-1 ring-hairline-strong">
                              {index + 1}
                            </span>
                          )}
                          {!isLast ? (
                            <span
                              className={`w-px flex-1 ${
                                isApproved ? 'bg-green-wash' : 'bg-hairline'
                              }`}
                            />
                          ) : null}
                        </div>

                        <div className="pb-5">
                          <p
                            className={`flex flex-wrap items-center gap-1.5 text-[13px] font-semibold ${
                              isCurrent
                                ? 'text-status-amber'
                                : isHalted
                                  ? 'text-status-red'
                                  : 'text-ink'
                            }`}
                          >
                            {step.office}
                            <span className="rounded bg-[#eef2ef] px-1.5 py-0.5 font-mono text-[10px] text-ink-soft">
                              {step.officeCode}
                            </span>
                            <span
                              className={`pill ${pillFor(step.band)}`}
                              style={{ fontSize: 10, padding: '1px 7px' }}
                            >
                              {badgeLabel}
                            </span>
                          </p>

                          <p className="text-[12px] text-ink-soft">
                            {step.action}
                            {step.recipient ? (
                              <>
                                {' '}
                                · sent to{' '}
                                <span className="font-semibold text-ink">{step.recipient}</span>
                              </>
                            ) : null}
                          </p>

                          {isCurrent ? (
                            <p className="mt-0.5 text-[12px] font-semibold text-status-amber">
                              Awaiting this office
                              {detail.currentStep?.sinceHuman
                                ? ` · here since ${detail.currentStep.sinceHuman}`
                                : ''}
                            </p>
                          ) : step.actedAt ? (
                            <p className="mt-0.5 text-[12px] text-ink-soft">
                              {isApproved
                                ? 'Approved'
                                : step.status === 'denied'
                                  ? 'Denied'
                                  : 'Returned'}{' '}
                              by{' '}
                              <span className="font-semibold text-ink">
                                {step.actedBy ?? '—'}
                              </span>{' '}
                              · {step.actedAt}
                            </p>
                          ) : isForwarded && step.startedAt ? (
                            <p className="mt-0.5 text-[12px] text-ink-soft">
                              Received {step.startedAt} · passed to the next office
                            </p>
                          ) : null}

                          {step.remarks ? (
                            <p className="mt-1 rounded-[8px] bg-[#f6f8f7] px-2.5 py-1.5 text-[12px] italic text-ink">
                              “{step.remarks}”
                            </p>
                          ) : null}

                          {step.signatureUrl ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img
                              src={step.signatureUrl}
                              alt={`e-signature of ${step.actedBy ?? 'the approving supervisor'}`}
                              className="mt-1.5 h-12 rounded-[6px] border border-hairline bg-white px-2 py-1"
                            />
                          ) : null}
                        </div>
                      </li>
                    );
                  })}

                  {showOpenEndedNext ? (
                    <li className="flex gap-3">
                      <div className="flex flex-col items-center">
                        <span className="flex h-8 w-8 items-center justify-center rounded-full border border-dashed border-hairline-strong bg-white text-xs font-bold text-ink-soft">
                          ?
                        </span>
                      </div>
                      <div className="pb-1">
                        <p className="text-[13px] font-semibold text-ink-soft">Next office</p>
                        <p className="text-[12px] text-ink-soft">
                          Joins the chain when it scans the folder. The request completes only
                          when an office marks it done.
                        </p>
                      </div>
                    </li>
                  ) : null}
                </ol>
              </div>
            </section>

            {/* Who scanned this folder (or, for a soft copy, who received it):
                every hand-off, newest first. */}
            <section className="mt-7">
              <h2 className="dm-section">{detail.isSoftCopy ? 'Received by' : 'Scan Trail'}</h2>
              <ul className="dm-logs">
                {detail.custodyTrail.length === 0 ? (
                  <li className="text-[length:var(--fs-small)] text-ink-soft">
                    {detail.isSoftCopy
                      ? 'Nobody has received it yet.'
                      : 'Nobody has scanned this folder yet.'}
                  </li>
                ) : (
                  detail.custodyTrail.map((event) => (
                    <li key={event.id} className="dm-log items-start">
                      <span className="dm-log-dot mt-1.5" aria-hidden="true" />
                      <span className="min-w-0 flex-1">
                        <span className="dm-log-event block">{event.userName}</span>
                        <span className="block text-[length:var(--fs-caption)] text-ink-soft">
                          {event.officeName ?? 'No department'} · {event.methodLabel}
                          {event.method === 'manual' && event.overrideReason
                            ? ` — ${event.overrideReason}`
                            : ''}
                        </span>
                      </span>
                      <span className="dm-log-time">
                        {event.at ? formatNaive(new Date(event.at), 'M d, h:i A') : ''}
                      </span>
                    </li>
                  ))
                )}
              </ul>
            </section>

            <section className="mt-7">
              <h2 className="dm-section">More Info</h2>
              <dl className="dm-info">
                {(
                  [
                    ['What is requested', detail.purpose],
                    ['Route', detail.documentType],
                    [
                      'Copy',
                      detail.isSoftCopy
                        ? 'Soft copy only — no paper folder'
                        : 'Hard copy — paper folder, scanned at each office',
                    ],
                    [
                      'Filed by',
                      `${detail.filedBy ?? '—'}${
                        detail.filedByOffice ? ` · ${detail.filedByOffice}` : ''
                      }`,
                    ],
                    ['Filed on', detail.filedOn],
                  ] as const
                ).map(([key, value]) => (
                  <div key={key} className="dm-info-row">
                    <dt>{key}</dt>
                    <dd>{value}</dd>
                  </div>
                ))}
              </dl>

              {detail.description ? (
                <div className="dm-note">
                  <p className="dm-note-label">Details</p>
                  <p className="whitespace-pre-line">{detail.description}</p>
                </div>
              ) : null}
            </section>

            {detail.attachments.length > 0 ? (
              <section className="mt-7">
                <h2 className="dm-section">Files</h2>
                <ul className="dm-files">
                  {detail.attachments.map((attachment) => (
                    <li key={attachment.id} className="dm-file">
                      <span className="dm-thumb" aria-hidden="true">
                        <span
                          className={`dm-thumb-doc${
                            attachment.ext.toUpperCase() === 'PDF' ? ' is-pdf' : ''
                          }`}
                        >
                          <svg
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinejoin="round"
                              d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"
                            />
                            <path strokeLinejoin="round" d="M14 3.5V8h4.5" />
                          </svg>
                        </span>
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="dm-file-name">{attachment.label}</p>
                        <p className="dm-file-meta">{attachment.ext}</p>
                      </div>
                      <a
                        href={attachment.url}
                        target="_blank"
                        rel="noopener"
                        className="dm-file-open"
                      >
                        View
                      </a>
                    </li>
                  ))}
                </ul>
              </section>
            ) : null}
          </div>
        </section>

        <RequestPanels detail={detail} />
      </div>
    </div>
  );
}

function waitingLine(detail: InternalRequestDetail): React.ReactNode {
  if (detail.currentStep) {
    const who = detail.currentStep.recipient ? ` (${detail.currentStep.recipient})` : '';
    const since = detail.currentStep.sinceHuman ? ` There since ${detail.currentStep.sinceHuman}.` : '';

    return (
      <>
        Awaiting <span className="font-semibold text-ink">{detail.currentStep.office}</span>
        {who} — {detail.currentStep.action}.{since}
      </>
    );
  }

  if (
    (
      [
        DocumentStatus.Completed,
        DocumentStatus.Denied,
        DocumentStatus.Withdrawn,
        DocumentStatus.Returned,
      ] as string[]
    ).includes(detail.status)
  ) {
    return `This request is ${detail.statusLabel.toLowerCase()}. No office action is pending.`;
  }

  return 'No hop is currently open on this request.';
}

/** Internal bands map onto the same pills the rest of the app uses. */
function pillFor(band: string): string {
  switch (band) {
    case 'green':
      return 'p-green';
    case 'red':
      return 'p-red';
    case 'returned':
      return 'p-orange';
    case 'muted':
      return 'p-muted';
    default:
      return 'p-amber';
  }
}
