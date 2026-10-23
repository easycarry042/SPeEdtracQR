'use client';

import { useEffect, useState } from 'react';
import type { ReviewRow } from '@/lib/domain/request-review';
import { StageBar } from '@/components/stage-bar';
import { ReviewActions, type ReviewMode } from './review-actions';
import { ReviewSidePanel } from './review-side-panel';
import { RequirementChecks } from './requirement-checks';

/**
 * The shared Review modal.
 *
 * Two cards, as the Look Up desk laid them out: the request on the left, the
 * claiming-date calendar or the conversation on the right. Three pages render
 * it — the supervisor dashboard, the staff cockpit and the super admin's
 * Requests page — so whatever a request needs can be done from wherever it was
 * found, instead of staff crossing between screens to finish one job.
 *
 * `mode` is what differs between them:
 *   'supervisor' — assign, reassign, deny
 *   'staff'      — triage (accept/decline/revision) and the status cockpit
 *   'oversight'  — read-only with a Notify button (the super admin's view)
 */
export function ReviewModal({
  row,
  mode,
  assignableStaff,
  onClose,
}: {
  row: ReviewRow;
  mode: ReviewMode;
  assignableStaff: Array<{ id: number; name: string }>;
  onClose: () => void;
}) {
  const [side, setSide] = useState<'calendar' | 'messages'>(
    // Open on the conversation when the citizen is waiting on a reply.
    row.unreadMessages > 0 ? 'messages' : 'calendar',
  );

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [onClose]);

  return (
    /* The backdrop is Tailwind utilities, not a dm-* class: `.dm-fixed` only
       sizes the card and scales its type — it never positioned anything. */
    <div
      className="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
      role="dialog"
      aria-modal="true"
      aria-labelledby={`review-${row.id}-title`}
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        className="mx-auto flex w-full max-w-[1335px] flex-col items-start justify-center gap-[15px] lg:flex-row lg:items-stretch"
        onClick={(event) => {
          if (event.target === event.currentTarget) {
            onClose();
          }
        }}
      >
        {/* ── Left: the request ─────────────────────────────────────────── */}
        {/* A fixed size with a pinned header and footer, and a body that
            scrolls between them. */}
        <div className="dm-card dm-card-review dm-fixed flex w-full max-w-[660px] flex-col lg:min-w-0 lg:flex-1">
          <div className="dm-head">
            <span className={`pill ${toneFor(row.status)}`}>{row.statusLabel}</span>
            <button type="button" className="dm-close" onClick={onClose} aria-label="Close">
              <svg fill="none" stroke="currentColor" strokeWidth={2} viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>

          <div className="dm-body">
            <h2 id={`review-${row.id}-title`} className="dm-title">
              {row.documentType}
            </h2>
            <p className="dm-subtitle">
              <span className="id-chip">{row.trackingNumber}</span>
              {row.department ? <span className="dm-chip-info">{row.department}</span> : null}
            </p>

            {/* Progress: the four stages, moving as the actions below run. */}
            <div className="mt-5">
              <StageBar
                status={row.status}
                statusBeforeHold={row.statusBeforeHold}
                size="sm"
                sideState={false}
              />
            </div>

            {/* Off-the-line states, with what the citizen or office is waiting on. */}
            {row.status === 'on_hold' ? (
              <div className="dm-callout dm-callout-amber mt-5">
                <p className="font-bold">On hold</p>
                <p className="mt-1">
                  {row.blockedBy ? `Waiting on ${row.blockedBy}.` : null}
                  {row.holdUntil ? ` Expected to resume by ${row.holdUntil}.` : null}
                </p>
                {row.holdReason ? <p className="mt-1">{row.holdReason}</p> : null}
                {/* A hold pauses the SLA clock, so this is the only thing here
                    that says a parked request has overrun its own deadline. */}
                {row.holdOverdueDays !== null ? (
                  <p className="hold-stalled mt-2">
                    Stalled — {row.holdOverdueDays} day{row.holdOverdueDays === 1 ? '' : 's'} past
                    its own deadline.
                  </p>
                ) : null}
              </div>
            ) : null}

            {row.status === 'returned' && row.remarks ? (
              <div className="dm-callout dm-callout-amber mt-5">
                <p className="font-bold">Returned for revision</p>
                <p className="mt-1 whitespace-pre-line">{row.remarks}</p>
              </div>
            ) : null}

            {row.status === 'denied' && row.remarks ? (
              <div className="dm-callout mt-5">
                <p className="font-bold">Denied</p>
                <p className="mt-1 whitespace-pre-line">{row.remarks}</p>
              </div>
            ) : null}

            {/* Claiming date — the thing the citizen actually asked about. */}
            <div className="dm-date mt-5">
              <span className="dm-date-label">Claim on</span>
              <span className="dm-date-value">{row.claimDate ?? 'Not set'}</span>
              {row.requestedClaimDate ? (
                <span className="dm-chip-info">Citizen asked for {row.requestedClaimDate}</span>
              ) : null}
            </div>

            {/* Reservation: the chairs or venue this request books. */}
            {row.booking ? <BookingCard booking={row.booking} /> : null}

            {/* More Info */}
            <h3 className="dm-section mt-7">More Info</h3>
            <dl className="dm-info">
              {(
                [
                  ['Requested by', row.citizenName],
                  ['Email', row.citizenEmail],
                  ['Contact', row.citizenContact],
                  ['Purpose', row.purpose],
                  ['Office', row.department],
                  ['Office code', row.thedId],
                  ['Assigned to', row.assignedStaff],
                  ['Filed', row.requestDate],
                ] as const
              ).map(([key, value]) => (
                <div key={key} className="dm-info-row">
                  <dt>{key}</dt>
                  <dd>{value || '—'}</dd>
                </div>
              ))}
            </dl>

            {row.description ? (
              <div className="dm-note mt-4">
                <p className="dm-note-label">Description</p>
                <p className="whitespace-pre-line">{row.description}</p>
              </div>
            ) : null}

            {/* Requirement uploads: the citizen's evidence for each listed item.
                They live on their own table, so without this the reviewer sees
                no evidence at all. */}
            <RequirementChecks documentId={row.id} requirements={row.requirements} canAdvance={row.canAdvance} />

            {row.attachments.length > 0 ? (
              <>
                <h3 className="dm-section mt-7">Attachments</h3>
                <div className="thumbs">
                  {row.attachments.map((attachment) => (
                    <a
                      key={attachment.url}
                      href={attachment.url}
                      target="_blank"
                      rel="noopener"
                      className={attachment.isImage ? 'dm-thumb' : 'dm-thumb dm-thumb-doc'}
                    >
                      {attachment.isImage ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={attachment.url} alt="" />
                      ) : (
                        <span>{attachment.ext.toUpperCase() || 'FILE'}</span>
                      )}
                    </a>
                  ))}
                </div>
              </>
            ) : null}

            {/* Logs: the stage history. */}
            {row.logs.length > 0 ? (
              <>
                <h3 className="dm-section mt-7">Logs</h3>
                <ul className="dm-logs">
                  {row.logs.map((log, index) => (
                    <li key={`${log.time}-${index}`} className="dm-log">
                      <span className="dm-log-dot" aria-hidden="true" />
                      <span className="dm-log-event">{log.event}</span>
                      <span className="dm-log-time">{log.time}</span>
                    </li>
                  ))}
                </ul>
              </>
            ) : null}
          </div>

          <ReviewActions row={row} mode={mode} assignableStaff={assignableStaff} onDone={onClose} />
        </div>

        {/* ── Right: the calendar, or the conversation ──────────────────── */}
        <ReviewSidePanel row={row} side={side} onSideChange={setSide} />
      </div>
    </div>
  );
}

function BookingCard({ booking }: { booking: NonNullable<ReviewRow['booking']> }) {
  return (
    <section className="dm-booking mt-5">
      <div className="flex flex-wrap items-center gap-2">
        <span className={`pill p-${booking.tone === 'muted' ? 'muted' : booking.tone}`}>
          {booking.stateLabel}
        </span>
        <span className="font-bold text-ink">{booking.item}</span>
        {booking.isEquipment ? (
          <span className="dm-chip-info">
            {booking.units} of {booking.requested} asked for
          </span>
        ) : null}
      </div>

      <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">{booking.window}</p>

      {/* Why it cannot be approved yet, in the place where someone would try. */}
      {booking.notReady ? <p className="dm-callout dm-callout-amber mt-2">{booking.notReady}</p> : null}
      {booking.clash ? (
        <p className="dm-callout mt-2">
          {booking.item} is already reserved during that time.
        </p>
      ) : null}
      {booking.free !== null ? (
        <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
          {booking.free} free for those dates{booking.serviceable !== null ? ` of ${booking.serviceable} in service` : ''}.
        </p>
      ) : null}

      <a href={booking.deskUrl} className="cr-link mt-2 inline-block">
        Open the lending desk
      </a>
    </section>
  );
}

/** Status → pill tone, the same mapping the citizen page uses. */
function toneFor(status: string): string {
  switch (status) {
    case 'completed':
    case 'approved':
      return 'p-green';
    case 'pending':
      return 'p-muted';
    case 'returned':
    case 'denied':
      return 'p-red';
    case 'on_hold':
      return 'p-hold';
    default:
      return 'p-amber';
  }
}
