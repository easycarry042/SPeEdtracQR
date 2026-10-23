import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, redirect } from 'next/navigation';
import { db, toId } from '@/lib/db';
import { formatNaive } from '@/lib/time';
import { routes } from '@/lib/routes';
import { currentActor } from '@/lib/auth/actor';
import { PortalHeader } from '@/components/citizen/portal-header';
import { SiteFooter } from '@/components/citizen/site-footer';
import { Speedy } from '@/components/citizen/speedy';
import { PageIntro } from '@/components/citizen/page-intro';
import { StageBar } from '@/components/stage-bar';
import { EtaEstimate } from '@/components/eta-estimate';
import { LiveStatus } from './live-status';
import {
  DocumentStatus,
  citizenCases,
  description as statusDescription,
  label as statusLabel,
} from '@/lib/domain/document-status';
import { isInternal, statusOf } from '@/lib/domain/document';
import { maskEmail, maskPhone } from '@/lib/domain/contact-mask';
import { predict } from '@/lib/domain/completion-predictor';
import { url as sealUrl } from '@/lib/domain/document-seal';
import { ORIGIN_INTERNAL } from '@/lib/domain/scope';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ trackingNumber: string }>;
}): Promise<Metadata> {
  const { trackingNumber } = await params;

  return { title: `Tracking ${decodeURIComponent(trackingNumber).toUpperCase()}` };
}

/**
 * Same status → pill-class mapping the shared status badge uses.
 */
const PILL_CLASS: Record<string, string> = {
  completed: 'p-green',
  approved: 'p-green',
  pending: 'p-muted',
  returned: 'p-red',
  rejected: 'p-red',
  denied: 'p-red',
  on_hold: 'p-hold',
};

/**
 * The citizen tracking page.
 *
 * Anyone holding the tracking number can open this, which sets every rule here:
 * contact details are masked, attached files are never shown, and staff stay
 * anonymous — the handling OFFICE is named, never the person (legal advice).
 * Internal (INT-) requests are "not found" on this surface entirely.
 */
export default async function TrackShowPage({
  params,
  searchParams,
}: {
  params: Promise<{ trackingNumber: string }>;
  searchParams: Promise<{ view?: string }>;
}) {
  const { trackingNumber } = await params;
  const { view } = await searchParams;
  const tracking = decodeURIComponent(trackingNumber).toUpperCase();

  const actor = await currentActor();

  // Citizen-facing links (receipt, public lookup, citizen emails) ask for
  // ?view=citizen. Without it, a staff member signed in on the same browser —
  // a shared counter PC — would be taken to the staff desk instead.
  const citizenView = actor === null || view === 'citizen';

  const document = await db.documents.findFirst({
    where: { tracking_number: tracking, deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      status_before_hold: true,
      status_changed_at: true,
      origin: true,
      created_at: true,
      completed_at: true,
      claimed_at: true,
      claim_date: true,
      citizen_name: true,
      citizen_email: true,
      citizen_contact: true,
      remarks: true,
      decided_at: true,
      hold_reason: true,
      hold_until: true,
      blocked_by: true,
      department: { select: { name: true, code: true } },
    },
  });

  // Soft-fail: a typo'd or unknown number lands back on the public track page,
  // which reports "not found" itself, rather than a bare 404.
  if (!document) {
    redirect(routes.citizenTrack() + `?tracking=${encodeURIComponent(tracking)}`);
  }

  // Internal office-to-office requests have their own module and workflow. The
  // public must never see them, so the citizen view 404s (no info leak).
  if (isInternal(document)) {
    if (!citizenView) {
      redirect(routes.requestsShow(toId(document.id)));
    }

    notFound();
  }

  // A super admin reads requests on the Requests page, opened on this one.
  if (!citizenView && actor?.can('manage system')) {
    redirect(routes.adminRequestsIndex({ open: document.tracking_number }));
  }

  // Staff who are not super admins read the request on the staff surfaces.
  if (!citizenView) {
    redirect(routes.staffDashboard({ open: document.tracking_number }));
  }

  const stage = statusOf(document);
  const pillClass = PILL_CLASS[document.status] ?? 'p-amber'; // in_progress / in_review
  const prediction = await predict(document);

  const booking = await db.bookings.findFirst({
    where: { document_id: document.id },
    select: {
      status: true,
      quantity: true,
      starts_at: true,
      ends_at: true,
      released_at: true,
      returned_at: true,
      resource: { select: { name: true, kind: true, location: true } },
    },
  });

  const requirements = await db.document_requirements.findMany({
    where: { document_id: document.id, review_status: { in: ['needs_revision', 'rejected'] } },
    select: { id: true, label: true, review_status: true, review_comment: true },
    orderBy: { id: 'asc' },
  });

  // The decision itself: who closed the request and why. Without this the
  // citizen only sees "Denied" and a generic sentence telling them a reason
  // should exist. The DECIDER is named here — this is the one public exception,
  // because a denial has to be accountable to someone.
  const showsDecision = (
    [DocumentStatus.Denied, DocumentStatus.Returned, DocumentStatus.OnHold] as string[]
  ).includes(document.status);
  const isDenied = document.status === DocumentStatus.Denied;

  const verifyUrl =
    stage === DocumentStatus.Completed
      ? sealUrl({
          tracking_number: document.tracking_number,
          document_type: document.document_type,
          created_at: document.created_at,
        })
      : null;

  return (
    <div className="flex min-h-screen flex-col bg-white antialiased text-ink">
      <PortalHeader />

      <main id="main" className="px-4 py-10 sm:px-6 lg:py-14">
        <div className="mx-auto max-w-[820px] space-y-6">
          {/* What this request is, opened the way every citizen page opens. The
              tracking number and filing date sit right under the title, so the
              details card below no longer repeats them as a header of its own. */}
          <div>
            <PageIntro eyebrow="Request tracking" title={document.document_type} />
            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-[15px] font-medium text-[#4b5a50]">
              <span className="cz-id text-[15px]">{document.tracking_number}</span>
              {document.created_at ? <span>Filed {formatNaive(document.created_at, 'M d, Y')}</span> : null}
            </div>
          </div>

          {/* ── Live status card: the stage line, then what the stage means.
              One card rather than two — the line and the badge under it answer
              the same question. ─────────────────────────────────────────── */}
          <div className="cz-card" id="statusCard">
            <div className="cz-card-head">
              <h2 className="cz-card-title">Current status</h2>
              {/* Live updates arrive over SSE; the indicator and the in-place
                  patching live in this client component. */}
              <LiveStatus trackingNumber={document.tracking_number} />
            </div>

            <div className="cz-card-body">
              <div className="mb-6">
                <StageBar
                  status={document.status}
                  statusBeforeHold={document.status_before_hold}
                  surface="citizen"
                />
              </div>

              {/* A hold is the one state the bar cannot explain on its own: it
                  pauses the SLA clock, and what the citizen needs is who it is
                  waiting on and until when. */}
              {document.status === DocumentStatus.OnHold ? (
                <div className="mb-6 rounded-[12px] border border-status-amber-wash bg-status-amber-wash px-4 py-3 text-[15px] text-status-amber">
                  <p className="font-bold">This request is paused</p>
                  <p className="mt-1">
                    {document.blocked_by ? `Waiting on ${document.blocked_by}. ` : null}
                    {document.hold_until
                      ? `Expected to resume by ${formatNaive(document.hold_until, 'M d, Y')}.`
                      : null}
                  </p>
                  {document.hold_reason ? <p className="mt-1">{document.hold_reason}</p> : null}
                </div>
              ) : null}

              <div className="flex flex-wrap items-center gap-4">
                <span id="statusBadge" className={`pill ${pillClass}`}>
                  <span id="statusLabel">{statusLabel(stage)}</span>
                </span>

                {/* The office, never the person: staff stay anonymous on every
                    public surface (legal advice). */}
                <div>
                  <p className="cz-key">Handled by</p>
                  <p className="mt-0.5 text-[16px] font-semibold text-[#1a1b1a]" id="currentDept">
                    {document.department?.name ?? 'Not yet routed'}
                  </p>
                </div>
              </div>

              {/* Plain-language explanation of the current status. */}
              <p className="mt-4 text-[16px] leading-relaxed text-[#353535]" id="statusDescription">
                {statusDescription(stage)}
              </p>

              {showsDecision && document.remarks ? (
                <div
                  className={`mt-4 rounded-[8px] border p-4 ${
                    isDenied ? 'border-status-red/30 bg-red-50/60' : 'border-hairline bg-green-wash/40'
                  }`}
                >
                  <p
                    className={`text-xs font-semibold uppercase tracking-wide ${
                      isDenied ? 'text-status-red' : 'text-ink-soft'
                    }`}
                  >
                    {isDenied ? 'Reason for denial' : 'Message from staff'}
                  </p>
                  {document.decided_at ? (
                    <p className="mt-1 text-sm text-ink-soft">
                      {formatNaive(document.decided_at, 'M j, Y g:i A')}
                    </p>
                  ) : null}
                  <p className="mt-2 whitespace-pre-line text-sm text-ink">{document.remarks}</p>
                </div>
              ) : null}

              <details className="mt-4 text-[15px]">
                <summary className="inline-flex min-h-[44px] cursor-pointer touch-manipulation items-center gap-1.5 font-bold text-[#01721a] hover:underline">
                  <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M12 9v2m0 4h.01M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z"
                    />
                  </svg>
                  What do the statuses mean?
                </summary>
                <dl className="mt-2 space-y-1.5 border-t border-hairline pt-2">
                  {citizenCases().map((status) => (
                    <div key={status} className="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                      <dt className="shrink-0 font-semibold text-ink sm:w-40">{statusLabel(status)}</dt>
                      <dd className="text-ink-soft">{statusDescription(status)}</dd>
                    </div>
                  ))}
                </dl>
              </details>

              {/* QR-gated pickup: the emailed/printed QR is the claim stub. */}
              {stage === DocumentStatus.Completed ? (
                <>
                  {document.claimed_at ? (
                    <div className="mt-4 rounded-xl border border-hairline bg-green-wash px-4 py-3 text-sm text-green-deep">
                      <b>Released.</b> This document was claimed at the counter on{' '}
                      <span className="id-chip">{formatNaive(document.claimed_at, 'M d, Y')}</span>.
                    </div>
                  ) : (
                    <div className="mt-4 rounded-xl border border-hairline-strong bg-green-wash px-4 py-3 text-sm text-green-deep">
                      <b>Ready for pickup.</b> Bring your QR code (from your confirmation email or
                      printed slip) to the counter — staff will scan it to release your document. No
                      QR means slower manual verification.
                    </div>
                  )}

                  {verifyUrl ? (
                    <p className="mt-3 text-xs text-ink-soft">
                      Third parties can confirm this document is genuine at any time via its{' '}
                      <a href={verifyUrl} className="font-semibold text-green underline">
                        official verification page
                      </a>
                      .
                    </p>
                  ) : null}
                </>
              ) : null}
            </div>
          </div>

          {/* ── Predicted completion (self-hosted analytics) ────────────────── */}
          <EtaEstimate prediction={prediction} status={document.status} citizen />

          {/* ── Borrowing / reservation ─────────────────────────────────────── */}
          {booking?.resource ? (
            <div className="cz-card">
              <div className="cz-card-head">
                <h2 className="cz-card-title">Your reservation</h2>
                <p className="cz-card-sub">
                  {booking.status === 'approved'
                    ? booking.released_at
                      ? 'Collected — please return it by the date below.'
                      : 'Approved. Collect it at the office on the pickup date.'
                    : booking.status === 'cancelled'
                      ? 'This reservation was cancelled.'
                      : 'Waiting for the office to confirm this reservation.'}
                </p>
              </div>
              <div className="cz-card-body">
                <dl className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                  <div>
                    <dt className="cz-key">Item</dt>
                    <dd className="mt-1 text-[16px] text-[#1a1b1a]">{booking.resource.name}</dd>
                  </div>
                  {booking.quantity ? (
                    <div>
                      <dt className="cz-key">How many</dt>
                      <dd className="mt-1 text-[16px] text-[#1a1b1a]">{booking.quantity}</dd>
                    </div>
                  ) : null}
                  <div>
                    <dt className="cz-key">Pickup</dt>
                    <dd className="mt-1 text-[16px] text-[#1a1b1a]">
                      {formatNaive(booking.starts_at, 'D, M j, Y · g:i A')}
                    </dd>
                  </div>
                  <div>
                    <dt className="cz-key">Return by</dt>
                    <dd className="mt-1 text-[16px] text-[#1a1b1a]">
                      {formatNaive(booking.ends_at, 'D, M j, Y · g:i A')}
                    </dd>
                  </div>
                  {booking.resource.location ? (
                    <div>
                      <dt className="cz-key">Where</dt>
                      <dd className="mt-1 text-[16px] text-[#1a1b1a]">{booking.resource.location}</dd>
                    </div>
                  ) : null}
                </dl>
              </div>
            </div>
          ) : null}

          {/* ── Request details ─────────────────────────────────────────────── */}
          {/* Internal document images are intentionally NOT shown on the public
              tracking page; they are only visible to authorized staff. */}
          <div className="cz-card">
            <div className="cz-card-head">
              <h2 className="cz-card-title">Request details</h2>
            </div>

            <div className="cz-card-body">
              {/* The claiming day staff set when advancing — the thing the
                  citizen opened this page to find out. */}
              {document.claim_date ? (
                <div className="claim-day mb-5">
                  <svg fill="none" stroke="currentColor" strokeWidth={1.8} viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"
                    />
                  </svg>
                  <div>
                    <p className="k">Claim on</p>
                    <p className="v">{formatNaive(document.claim_date, 'l, M d, Y')}</p>
                  </div>
                </div>
              ) : null}

              {/* Anyone with the tracking number can open this page, so the
                  email and phone are partly hidden: enough for the requester to
                  recognise, too little for anyone else to use — and they are
                  what the message gate asks for. The name stays; the printable
                  claim slip carries it. */}
              <dl className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                {(
                  [
                    ['Office', document.department?.name ?? 'Not yet routed'],
                    ['Office code', document.department?.code ?? '—'],
                    ['Requested by', document.citizen_name ?? '—'],
                    ['Email', maskEmail(document.citizen_email) ?? '—'],
                    ['Contact number', maskPhone(document.citizen_contact) ?? '—'],
                  ] as const
                ).map(([key, value]) => (
                  <div key={key}>
                    <dt className="cz-key">{key}</dt>
                    <dd className="mt-1 break-words text-[16px] text-[#1a1b1a]">{value}</dd>
                  </div>
                ))}
              </dl>

              <div className="mt-5 border-t border-[rgba(0,64,4,0.08)] pt-5">
                <a
                  href={`${routes.trackSlip(document.tracking_number)}?from=tracking`}
                  target="_blank"
                  rel="noopener"
                  className="cz-btn cz-btn-secondary cz-btn-sm"
                >
                  <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"
                    />
                  </svg>
                  Print claim slip
                </a>
              </div>
            </div>
          </div>

          {/* ── Documents needing revision / rejected (per-requirement) ─────── */}
          {requirements.length > 0 ? (
            <div className="cz-card">
              <div className="cz-card-head">
                <div>
                  <h2 className="cz-card-title">Documents to fix</h2>
                  <p className="cz-card-sub">
                    Staff reviewed your uploads. Re-upload{' '}
                    <span className="font-semibold text-green-deep">only</span> the items marked
                    below.
                  </p>
                </div>
              </div>
              <div className="cz-card-body space-y-4">
                {requirements.map((requirement) => (
                  <div
                    key={toId(requirement.id)}
                    className="rounded-[12px] border border-hairline-strong bg-white p-4"
                  >
                    <p className="font-semibold text-ink">{requirement.label}</p>
                    <p className="mt-1 text-sm text-ink-soft">
                      {requirement.review_status === 'rejected' ? 'Rejected' : 'Needs revision'}
                      {requirement.review_comment ? ` · ${requirement.review_comment}` : null}
                    </p>
                  </div>
                ))}
                <p className="text-[15px] text-[#4b5a50]">
                  To re-upload, open the messages with the office below — you will confirm the email
                  or mobile number you filed with first.
                </p>
              </div>
            </div>
          ) : null}

          {/* ── Messages with the office ──────────────────────────────────────
              The conversation itself lives in Speedy's "Office messages" tab —
              one place to talk to the office, on every page, with an unread
              badge on its launcher. Reading it needs the same contact check as
              writing it. ── */}
          <div className="cz-card">
            <div className="cz-card-head">
              <div>
                <h2 className="cz-card-title">Messages with the office</h2>
                <p className="cz-card-sub">
                  {document.citizen_email || document.citizen_contact
                    ? 'Ask the office about this request. You’ll confirm the email or mobile number you filed it with first.'
                    : 'This request has no contact details on file, so messaging is not available for it.'}
                </p>
              </div>
            </div>
            <div className="cz-card-body">
              {document.citizen_email || document.citizen_contact ? (
                <Link
                  href={`${routes.trackShow(document.tracking_number)}&speedy=messages`}
                  className="cz-btn cz-btn-primary cz-btn-sm"
                >
                  <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"
                    />
                  </svg>
                  Open messages
                </Link>
              ) : (
                <p className="text-[15px] text-[#4b5a50]">
                  Please contact the handling office directly.
                </p>
              )}
            </div>
          </div>
        </div>
      </main>

      <SiteFooter />
      <Speedy trackingNumber={document.tracking_number} />
    </div>
  );
}
