import type { Metadata } from 'next';
import Link from 'next/link';
import { db } from '@/lib/db';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { verify } from '@/lib/domain/document-seal';
import { DocumentStatus, label as statusLabel } from '@/lib/domain/document-status';
import { statusOf } from '@/lib/domain/document';
import { routes } from '@/lib/routes';
import { formatNaive } from '@/lib/time';

export const metadata: Metadata = { title: 'Verify a document' };

/**
 * Port of `VerificationController@show` — the public authenticity check. The
 * QR on an issued document encodes a signed URL; anyone scanning it sees
 * whether the record is genuine, with nothing beyond what the paper shows.
 *
 * Three verdicts: unknown record, bad signature (forged or altered), genuine.
 */
export default async function VerifyPage({
  params,
  searchParams,
}: {
  params: Promise<{ trackingNumber: string }>;
  searchParams: Promise<{ sig?: string }>;
}) {
  const trackingNumber = decodeURIComponent((await params).trackingNumber);
  const { sig } = await searchParams;

  const document = await db.documents.findFirst({
    where: { tracking_number: trackingNumber, deleted_at: null },
    select: {
      tracking_number: true,
      document_type: true,
      created_at: true,
      status: true,
      completed_at: true,
      claimed_at: true,
    },
  });

  const verdict = !document ? 'unknown' : verify(document, sig) ? 'genuine' : 'invalid';
  const genuine = verdict === 'genuine' ? document : null;
  const isIssued = genuine !== null && statusOf(genuine) === DocumentStatus.Completed;

  return (
    <div className="flex min-h-screen flex-col bg-white antialiased text-ink">
      <PortalHeader />

      <main id="main" className="px-4 py-10 sm:px-6 lg:py-14">
        <div className="mx-auto max-w-lg">
          <p className="mb-4 text-xs font-semibold uppercase tracking-wide text-ink-soft">Document authenticity check</p>

          {genuine ? (
            <div className="panel">
              <div className="verify-head verify-genuine">
                <svg width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2.4" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 5 5 9-10" />
                </svg>
                <div>
                  <p className="verify-verdict">Genuine record</p>
                  <p className="verify-sub">This document is on official record at the San Pedro records office.</p>
                </div>
              </div>
              <div className="pb">
                <dl className="verify-rows">
                  <div>
                    <dt>Tracking number</dt>
                    <dd>
                      <span className="id-chip">{genuine.tracking_number}</span>
                    </dd>
                  </div>
                  <div>
                    <dt>Document type</dt>
                    <dd>{genuine.document_type}</dd>
                  </div>
                  <div>
                    <dt>Current status</dt>
                    <dd>{statusLabel(statusOf(genuine))}</dd>
                  </div>
                  {isIssued && genuine.completed_at ? (
                    <div>
                      <dt>Issued</dt>
                      <dd>
                        <span className="id-chip">{formatNaive(genuine.completed_at, 'M d, Y')}</span>
                      </dd>
                    </div>
                  ) : null}
                  {genuine.claimed_at ? (
                    <div>
                      <dt>Released to citizen</dt>
                      <dd>
                        <span className="id-chip">{formatNaive(genuine.claimed_at, 'M d, Y')}</span>
                      </dd>
                    </div>
                  ) : null}
                </dl>
                {!isIssued ? (
                  <p className="mt-4 text-sm text-ink-soft">
                    Note: this record exists but has <b>not been issued yet</b> — it is still being processed.
                  </p>
                ) : null}
              </div>
            </div>
          ) : verdict === 'invalid' ? (
            <div className="panel">
              <div className="verify-head verify-invalid">
                <svg width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2.4" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
                <div>
                  <p className="verify-verdict">Verification failed</p>
                  <p className="verify-sub">The security signature on this code is missing or does not match the record.</p>
                </div>
              </div>
              <div className="pb">
                <p className="text-sm text-ink">
                  A document presenting this code may be <b>altered or counterfeit</b>. Please contact the San Pedro
                  records office to confirm before accepting it.
                </p>
                <p className="mt-3 text-xs text-ink-soft">
                  Reference presented: <span className="id-chip">{trackingNumber}</span>
                </p>
              </div>
            </div>
          ) : (
            <div className="panel">
              <div className="verify-head verify-unknown">
                <svg width="22" height="22" fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                  <circle cx="12" cy="12" r="9" />
                  <path strokeLinecap="round" d="M12 8v5m0 3h.01" />
                </svg>
                <div>
                  <p className="verify-verdict">Not on record</p>
                  <p className="verify-sub">No document with this tracking number exists in the system.</p>
                </div>
              </div>
              <div className="pb">
                <p className="text-sm text-ink">
                  Check the number for typing mistakes. If it was scanned from a printed document, that document is{' '}
                  <b>not from this office</b>.
                </p>
                <p className="mt-3 text-xs text-ink-soft">
                  Reference presented: <span className="id-chip">{trackingNumber}</span>
                </p>
              </div>
            </div>
          )}

          <p className="mt-4 text-xs text-ink-soft">
            This check is provided by SPeED TraQR, the document tracking system of the San Pedro records office.
            Verification links are cryptographically signed — a matching tracking number alone is not proof of
            authenticity.
          </p>

          <p className="mt-4 text-center text-sm">
            <Link href="/" className="font-semibold text-green underline">
              Go to homepage
            </Link>
            <span className="mx-2 text-ink-soft">·</span>
            <Link href={routes.citizenTrack()} className="font-semibold text-green underline">
              Track a document
            </Link>
          </p>
        </div>
      </main>

      <SiteFooter />
    </div>
  );
}
