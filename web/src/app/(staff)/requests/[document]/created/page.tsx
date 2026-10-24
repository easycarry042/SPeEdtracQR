import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { AuthenticatedPage } from '@/components/shell/authenticated-page';
import { requireActor } from '@/lib/auth/actor';
import { routes } from '@/lib/routes';
import { internalRequestDetail } from '@/lib/domain/internal-request';

export const metadata: Metadata = { title: 'Internal Request Filed' };

export const dynamic = 'force-dynamic';

/**
 * The confirmation screen right after filing: the QR to paste on the folder,
 * the tracking number, and the chain so far.
 *
 * Port of `InternalRequestController@created`. A soft copy has nothing to print
 * or carry — it is already with the person named to receive it — so that page
 * says so instead of offering a sticker.
 */
export default async function InternalRequestCreatedPage({
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

  if (!detail) {
    notFound();
  }

  const firstRecipient = detail.chain.find((step) => step.order === 1)?.recipient ?? null;
  const stamped = detail.attachments.find((file) => file.label === 'QR-stamped copy') ?? null;
  const original = detail.attachments.find((file) => file.label !== 'QR-stamped copy') ?? null;

  return (
    <AuthenticatedPage title="Internal Request Filed">
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section className="panel">
          <div className="pb p-8 text-center">
            <p className="text-[length:var(--fs-small)] text-ink-soft">
              {detail.isSoftCopy
                ? `Soft copy only — nothing to print or carry. It is already with ${
                    firstRecipient ?? 'the first office'
                  }, who has been notified.`
                : 'Paste the QR sticker on the paper request — every office it reaches scans it to confirm custody.'}
            </p>

            {detail.qrUrl ? (
              <div
                className="mx-auto mt-6 flex h-56 w-56 items-center justify-center rounded-[10px] border border-hairline bg-white p-3"
                role="img"
                aria-label={`QR code for tracking number ${detail.trackingNumber}`}
              >
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={detail.qrUrl} alt="" className="h-full w-full object-contain" />
              </div>
            ) : null}

            <p className="mt-6">
              <span className="id-chip text-lg">{detail.trackingNumber}</span>
            </p>
            <p className="mt-3 text-[length:var(--fs-body)] text-ink">{detail.purpose}</p>
            <p className="mt-1 text-[length:var(--fs-small)] text-ink-soft">
              {detail.documentType}
              {detail.filedByOffice ? ` · ${detail.filedByOffice}` : ''}
            </p>

            <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
              {!detail.isSoftCopy ? (
                <a
                  href={detail.stickerUrl}
                  target="_blank"
                  rel="noopener"
                  className="cr-btn cr-btn-primary"
                >
                  Print QR sticker
                </a>
              ) : null}
              {stamped ? (
                <a href={stamped.url} target="_blank" rel="noopener" className="cr-btn">
                  QR-stamped digital copy
                </a>
              ) : null}
              {original ? (
                <a href={original.url} target="_blank" rel="noopener" className="cr-btn">
                  Original scan
                </a>
              ) : null}
              <Link href={routes.requestsIndex()} className="cr-btn">
                Internal Requests
              </Link>
            </div>
          </div>
        </section>

        <section className="panel">
          <div className="ph">
            <h2>Endorsement chain</h2>
            <span className="sub">The highlighted office holds it now</span>
          </div>
          <div className="pb">
            <ol className="mvlist">
              {detail.chain.map((step) => (
                <li key={step.id} className="mvrow">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-bold text-ink">{step.office}</span>
                      <span className="stamp">{step.officeCode}</span>
                      <span className={`pill ${step.band === 'green' ? 'p-green' : 'p-amber'}`}>
                        {step.statusLabel}
                      </span>
                    </div>
                    <p className="dir-sub mt-1">
                      {step.action}
                      {step.recipient ? ` · for ${step.recipient}` : ''}
                    </p>
                  </div>
                </li>
              ))}
            </ol>

            <Link href={routes.requestsShow(detail.id)} className="cr-link mt-3 inline-block">
              Open the full request view →
            </Link>
          </div>
        </section>
      </div>
    </AuthenticatedPage>
  );
}
