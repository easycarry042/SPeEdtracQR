import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { DocumentStatus } from '@/lib/domain/document-status';
import { internalRequestDetail, type InternalRequestDetail } from '@/lib/domain/internal-request';
import { formatNaive } from '@/lib/time';
import { RequestPanels } from './panels';

export const metadata: Metadata = { title: 'Internal Request' };

/** The chain and the custody trail are live, so nothing here caches. */
export const dynamic = 'force-dynamic';

/**
 * One internal request: the endorsement chain, who has held the paper, and —
 * for whoever can act — the receive, decision or revise panel beside it.
 *
 * Port of `InternalRequestController@show`. Every staff member may read a
 * request (the Internal page links here from every queue); what differs is
 * which panel they get, and that follows the same gates the server enforces.
 */
export default async function InternalRequestPage({
  params,
}: {
  params: Promise<{ document: string }>;
}) {
  const actor = await requireActor();
  const { document } = await params;
  const documentId = Number(document);

  if (!Number.isInteger(documentId) || documentId <= 0) {
    notFound();
  }

  const detail = await internalRequestDetail(documentId, actor);

  // A citizen request is not an internal one — its own page is the tracker.
  if (!detail) {
    notFound();
  }

  const hasPanel =
    (detail.canReceive && detail.currentStep !== null && !detail.hasCustody) ||
    (detail.canAct && detail.currentStep !== null && detail.hasCustody) ||
    detail.canRevise;

  return (
    <AuthenticatedPage title="Internal Request">
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
            <Link href={routes.requestsIndex()} className="dm-close" aria-label="Back to Internal">
              ×
            </Link>
          </div>

          <div className="dm-body">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <p className="dm-title">{detail.purpose}</p>
                <p className="dm-subtitle">
                  <span className="id-chip">{detail.trackingNumber}</span>
                  {detail.filedByOffice ? (
                    <span className="dm-chip-info">{detail.filedByOffice}</span>
                  ) : null}
                  {detail.isSoftCopy ? <span className="chip">Soft copy</span> : null}
                </p>
              </div>

              {detail.qrUrl ? (
                <a href={detail.stickerUrl} target="_blank" rel="noopener" className="dm-qr-tile">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={detail.qrUrl} alt={`QR code for ${detail.trackingNumber}`} />
                  <span>Print sticker</span>
                </a>
              ) : null}
            </div>

            {/* Where it stands, when this viewer has nothing to do about it. */}
            {!hasPanel ? (
              <p className="dm-callout dm-callout-muted mt-5">
                <span>{waitingLine(detail)}</span>
              </p>
            ) : null}

            <section className="mt-7">
              <h2 className="dm-section">Endorsement Chain</h2>
              <ol className="mvlist mt-3">
                {detail.chain.length === 0 ? (
                  <li className="text-[length:var(--fs-small)] text-ink-soft">
                    No hops recorded yet.
                  </li>
                ) : (
                  detail.chain.map((step) => (
                    <li key={step.id} className="mvrow">
                      <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-bold text-ink">{step.office}</span>
                          <span className="stamp">{step.officeCode}</span>
                          <span className={`pill ${pillFor(step.band)}`}>{step.statusLabel}</span>
                        </div>
                        <p className="dir-sub mt-1">{step.action}</p>
                        <p className="dir-sub">
                          {step.actedBy ? `${step.actedBy} · ` : ''}
                          {step.actedAt ?? step.startedAt ?? '—'}
                          {step.recipient ? ` · for ${step.recipient}` : ''}
                        </p>
                        {step.remarks ? (
                          <p className="dm-note mt-2 whitespace-pre-line">{step.remarks}</p>
                        ) : null}
                      </div>

                      {/* The signature frozen at approval — staff-only, and
                          never rewritten when someone re-registers theirs. */}
                      {step.signatureUrl ? (
                        <a
                          href={step.signatureUrl}
                          target="_blank"
                          rel="noopener"
                          className="cr-btn cr-btn-ghost cr-btn-sm"
                        >
                          Signature
                        </a>
                      ) : null}
                    </li>
                  ))
                )}
              </ol>
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
                <div className="dm-note mt-3">
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
                      <span className="dm-file-name">{attachment.label}</span>
                      <span className="dm-file-meta">{attachment.ext}</span>
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
    </AuthenticatedPage>
  );
}

function waitingLine(detail: InternalRequestDetail): string {
  if (detail.currentStep) {
    const who = detail.currentStep.recipient ? ` (${detail.currentStep.recipient})` : '';
    const since = detail.currentStep.sinceHuman ? ` There since ${detail.currentStep.sinceHuman}.` : '';

    return `Awaiting ${detail.currentStep.office}${who} — ${detail.currentStep.action}.${since}`;
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
      return 'p-amber';
    case 'muted':
      return 'p-muted';
    default:
      return 'p-amber';
  }
}
