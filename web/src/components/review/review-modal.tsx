'use client';

import { useEffect, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { approveLoan } from '@/app/actions/lending';
import { StageBar } from '@/components/stage-bar';
import { ReviewActions, type ReviewMode } from './review-actions';
import { ReviewSidePanel } from './review-side-panel';
import { RequirementChecks } from './requirement-checks';

/**
 * The shared Review modal (`partials/review-modal.blade.php`).
 *
 * Two cards, as the Look Up desk laid them out: the request on the left, and a
 * second card that slides in beside it holding the claiming-date calendar or
 * the citizen conversation (`side` is which of those is open — the card only
 * exists while one of them is).
 */
export function ReviewModal({
  row,
  mode,
  assignableStaff,
  onClose,
}: {
  row: ReviewRow;
  mode: ReviewMode;
  assignableStaff: Array<{ id: number; name: string; is_available?: boolean }>;
  onClose: () => void;
}) {
  const [side, setSide] = useState<'date' | 'messages' | null>(
    row.unreadMessages > 0 ? 'messages' : null,
  );
  const [qrOpen, setQrOpen] = useState(false);
  const [allLogs, setAllLogs] = useState(false);

  const toggleSide = (which: 'date' | 'messages') => {
    setSide((prev) => (prev === which ? null : which));
  };

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        if (qrOpen) {
          setQrOpen(false);
        } else {
          onClose();
        }
      }
    };

    const onShowQr = (event: Event): void => {
      const detail = (event as CustomEvent<string>).detail;
      if (detail === row.trackingNumber) {
        setQrOpen(true);
      }
    };

    const onHideQr = (event: Event): void => {
      const detail = (event as CustomEvent<string>).detail;
      if (detail === row.trackingNumber) {
        setQrOpen(false);
      }
    };

    document.addEventListener('keydown', onKey);
    window.addEventListener('show-request-qr', onShowQr);
    window.addEventListener('hide-request-qr', onHideQr);
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('show-request-qr', onShowQr);
      window.removeEventListener('hide-request-qr', onHideQr);
      document.body.style.overflow = '';
    };
  }, [onClose, qrOpen, row.trackingNumber]);

  const visibleLogs = allLogs ? row.logs : row.logs.slice(0, 2);

  return (
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
        <div className="dm-card dm-card-review dm-fixed relative flex w-full max-w-[660px] flex-col lg:min-w-0 lg:flex-1">
          <div className="dm-head">
            <div className="flex min-w-0 flex-wrap items-center gap-2">
              <span className={`pill ${row.needsTriage ? 'p-amber' : toneFor(row.status)}`}>
                {row.needsTriage ? 'New assignment' : row.statusLabel}
              </span>
              {row.unreadMessages > 0 ? (
                <span className="dm-chip-info">
                  {row.unreadMessages} new message{row.unreadMessages > 1 ? 's' : ''}
                </span>
              ) : null}
            </div>
            <button type="button" className="dm-close" onClick={onClose} aria-label="Close">
              <svg fill="none" stroke="currentColor" strokeWidth={2.2} viewBox="0 0 24 24">
                <path strokeLinecap="round" d="M6 6l12 12M18 6 6 18" />
              </svg>
            </button>
          </div>

          <div className="dm-body">
            {/* Title + tracking number QR tile */}
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <h2 id={`review-${row.id}-title`} className="dm-title">
                  {row.documentType}
                </h2>
                <p className="dm-subtitle">{row.citizenName || '—'}</p>
              </div>

              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => setQrOpen(true)}
                  className="dm-qr-btn"
                  aria-label={`Show QR code for ${row.trackingNumber}`}
                  title="Show QR code"
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
                  <p className="mono text-[length:var(--fs-small)] font-bold text-ink">
                    {row.trackingNumber}
                  </p>
                </div>
              </div>
            </div>

            {/* Progress: the stages, moving as the actions below run. */}
            <StageBar
              status={row.status}
              statusBeforeHold={row.statusBeforeHold}
              size="md"
              sideState={false}
            />

            {/* Claiming date, and the two side panel toggles */}
            <div className="mt-6 flex flex-wrap items-stretch gap-2">
              <div className="dm-date">
                <p className="dm-date-label">Claiming Date</p>
                <p className="dm-date-value">{row.claimDate || 'Not set yet'}</p>
                {!row.claimDate && row.requestedClaimDate ? (
                  <p className="text-xs text-ink-soft">
                    Citizen asked for <span>{row.requestedClaimDate}</span>
                  </p>
                ) : null}
              </div>

              <button
                type="button"
                onClick={() => toggleSide('date')}
                className={`dm-tile${side === 'date' ? ' on' : ''}`}
                aria-expanded={side === 'date'}
              >
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.9"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="8.5" />
                  <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5V12l3 2" />
                </svg>
                <span>Adjust Date</span>
              </button>

              <button
                type="button"
                onClick={() => toggleSide('messages')}
                className={`dm-tile relative${side === 'messages' ? ' on' : ''}`}
                aria-expanded={side === 'messages'}
              >
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.9"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    strokeLinejoin="round"
                    d="M5 18.5V7a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 7v6.5a2.5 2.5 0 0 1-2.5 2.5H9l-4 2.5Z"
                  />
                  <path strokeLinecap="round" d="M9 10.5h.01M12 10.5h.01M15 10.5h.01" />
                </svg>
                <span>Messages</span>
                {row.unreadMessages > 0 ? (
                  <span className="dm-tile-badge">{row.unreadMessages}</span>
                ) : null}
              </button>
            </div>

            {/* Off-the-line states */}
            {row.status === 'on_hold' ? (
              <div className="dm-callout dm-callout-amber mt-5">
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.2"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M10 9v6M14 9v6M5 5h14v14H5z"
                  />
                </svg>
                <div>
                  <p>
                    <span className="font-bold">On hold</span>
                    {row.blockedBy ? <span> · waiting on {row.blockedBy}</span> : null}
                    {row.holdUntil ? <span> · until {row.holdUntil}</span> : null}
                    {row.holdOverdueDays !== null ? (
                      <span className="hold-stalled"> · {row.holdOverdueDays}d past due</span>
                    ) : null}
                  </p>
                  {row.holdReason ? <p>{row.holdReason}</p> : null}
                </div>
              </div>
            ) : null}

            {row.status === 'returned' && row.remarks ? (
              <div className="dm-callout dm-callout-amber mt-5">
                <svg
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2.2"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M3 7v6h6M3 13a9 9 0 1 0 3-7.7L3 8"
                  />
                </svg>
                <div>
                  <p className="font-bold">Returned for revision</p>
                  <p className="whitespace-pre-line">{row.remarks}</p>
                </div>
              </div>
            ) : null}

            {row.status === 'denied' && row.remarks ? (
              <div className="dm-callout mt-5">
                <div>
                  <p className="font-bold">Denied</p>
                  <p className="mt-1 whitespace-pre-line">{row.remarks}</p>
                </div>
              </div>
            ) : null}

            {/* Reservation: the chairs or venue this request books */}
            {row.booking ? <BookingCard booking={row.booking} mode={mode} /> : null}

            {/* More Info */}
            <section className="mt-7">
              <h3 className="dm-section">More Info</h3>
              <dl className="dm-info">
                {(
                  [
                    ['Request by', row.citizenName],
                    ...(row.requesterOffice
                      ? ([['From (office / organization)', row.requesterOffice]] as const)
                      : []),
                    ['Email', row.citizenEmail],
                    ['Contact No.', row.citizenContact],
                    ['Request Type', row.documentType],
                    ['Submitted', row.submittedAt ?? row.requestDate],
                    ...(row.purpose ? ([['Specifications / purpose', row.purpose]] as const) : []),
                    ...(row.quantity !== null
                      ? ([['Quantity', String(row.quantity)]] as const)
                      : []),
                    ...(row.details
                      ? ([[row.detailsLabel ?? 'Details', row.details]] as const)
                      : []),
                    ...(row.department ? ([['Office', row.department]] as const) : []),
                    ...(row.assignedStaff
                      ? ([['Assigned to', row.assignedStaff]] as const)
                      : []),
                  ] as const
                ).map(([key, value]) => (
                  <div key={key} className="dm-info-row">
                    <dt>{key}</dt>
                    <dd>{value || '—'}</dd>
                  </div>
                ))}
              </dl>

              {row.description ? (
                <div className="dm-note">
                  <p className="dm-note-label">Description</p>
                  <p className="whitespace-pre-line">{row.description}</p>
                </div>
              ) : null}
            </section>

            {/* Requirement uploads */}
            <RequirementChecks
              documentId={row.id}
              requirements={row.requirements}
              canAdvance={row.canAdvance}
            />

            {/* Attachments */}
            {row.attachments.length > 0 ? (
              <section className="mt-7">
                <h3 className="dm-section">Attachments</h3>
                <div className="mt-3 flex flex-wrap gap-2">
                  {row.attachments.map((attachment) => (
                    <a
                      key={attachment.url}
                      href={attachment.url}
                      target="_blank"
                      rel="noopener"
                      className="dm-thumb dm-thumb-lg"
                    >
                      {attachment.isImage ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={attachment.url} alt="Attachment" />
                      ) : (
                        <span
                          className={`dm-thumb-doc${attachment.ext === 'pdf' ? ' is-pdf' : ''}`}
                        >
                          <svg
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                          >
                            <path
                              strokeLinejoin="round"
                              d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"
                            />
                            <path strokeLinejoin="round" d="M14 3.5V8h4.5" />
                          </svg>
                          <span>{attachment.ext}</span>
                        </span>
                      )}
                    </a>
                  ))}
                </div>
              </section>
            ) : null}

            {/* Logs: the stage history */}
            <section className="mt-7">
              <h3 className="dm-section">Logs</h3>
              {row.logs.length === 0 ? (
                <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
                  Nothing recorded yet.
                </p>
              ) : (
                <>
                  <ul className="dm-logs">
                    {visibleLogs.map((log, index) => (
                      <li key={`${log.time}-${index}`} className="dm-log">
                        <span className="dm-log-dot" aria-hidden="true" />
                        <span className="dm-log-event">{log.event}</span>
                        <span className="lookup-leader" aria-hidden="true" />
                        <span className="dm-log-time">{log.time}</span>
                      </li>
                    ))}
                  </ul>
                  {row.logs.length > 2 ? (
                    <button
                      type="button"
                      onClick={() => setAllLogs((prev) => !prev)}
                      className="dm-more"
                    >
                      <span>{allLogs ? 'View less' : 'View more'}</span>
                      <svg
                        className={`h-4 w-4 transition${allLogs ? ' rotate-180' : ''}`}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.4"
                        viewBox="0 0 24 24"
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="m6 9 6 6 6-6" />
                      </svg>
                    </button>
                  ) : null}
                </>
              )}
            </section>
          </div>

          <ReviewActions
            row={row}
            mode={mode}
            assignableStaff={assignableStaff}
            onDone={onClose}
          />

          {/* QR overlay (`partials/qr-tile.blade.php`) */}
          {qrOpen ? (
            <div
              className="dm-qr-layer"
              onClick={(event) => {
                if (event.target === event.currentTarget) {
                  setQrOpen(false);
                }
              }}
              role="dialog"
              aria-modal="true"
              aria-label="QR code"
            >
              <div className="dm-qr-panel">
                <div className="flex items-center justify-between gap-3">
                  <p className="dm-section">QR code</p>
                  <button
                    type="button"
                    onClick={() => setQrOpen(false)}
                    aria-label="Close QR code"
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

                {row.qrUrl ? (
                  <div className="dm-qr-frame">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={row.qrUrl} alt={`QR code for ${row.trackingNumber}`} />
                  </div>
                ) : (
                  <p className="dm-qr-missing">No QR code has been generated for this request.</p>
                )}

                <p className="mono mt-3 text-center text-[length:var(--fs-body)] font-bold tracking-wide text-ink">
                  {row.trackingNumber}
                </p>
                <p className="mt-1 text-center text-[length:var(--fs-caption)] text-ink-soft">
                  {row.trackingNumber.startsWith('INT-')
                    ? 'Any phone camera can scan it to see which office has it — no account needed.'
                    : "Scanning it opens this request's public tracking page."}
                </p>

                <div className="dm-actions mt-4 justify-center">
                  {row.qrUrl ? (
                    <a
                      href={row.qrUrl}
                      download={`${row.trackingNumber}.png`}
                      className="cr-btn"
                    >
                      <svg
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.2"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"
                        />
                      </svg>
                      Download PNG
                    </a>
                  ) : null}
                  <a
                    href={row.slipUrl}
                    target="_blank"
                    rel="noopener"
                    className="cr-btn cr-btn-primary"
                  >
                    <svg
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2.2"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <path
                        strokeLinejoin="round"
                        d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2"
                      />
                      <path strokeLinejoin="round" d="M7 14h10v6H7z" />
                    </svg>
                    Print slip
                  </a>
                </div>
              </div>
            </div>
          ) : null}
        </div>

        {/* ── Right: the calendar, or the conversation ──────────────────── */}
        {side ? (
          <ReviewSidePanel row={row} side={side} onClose={() => setSide(null)} />
        ) : null}
      </div>
    </div>
  );
}

function BookingCard({
  booking,
  mode,
}: {
  booking: NonNullable<ReviewRow['booking']>;
  mode: ReviewMode;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [qty, setQty] = useState<number>(booking.requested || 1);
  const [error, setError] = useState<string>('');

  const free = booking.free ?? 0;
  const canApproveInline =
    mode !== 'oversight' &&
    booking.state === 'pending' &&
    !booking.notReady &&
    booking.canManage &&
    !booking.clash &&
    !(booking.isEquipment && booking.serviceable === 0);

  const qtyValid =
    !booking.isEquipment || (Number.isInteger(qty) && qty >= 1 && qty <= free);

  return (
    <section className="dm-booking mt-5" aria-label="Reservation">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="dm-date-label">
            {booking.isEquipment ? 'Borrowing' : 'Reservation'}
          </p>
          <p className="text-[15px] font-bold text-ink">
            <span>{booking.item}</span>
            {booking.isEquipment ? (
              <span className="font-semibold text-ink-soft">
                {' · '}
                {booking.state === 'pending'
                  ? `asked for ${booking.requested}`
                  : `${booking.units} lent`}
              </span>
            ) : null}
          </p>
          <p className="mt-1 text-[13px] text-ink-soft">
            <span>{booking.isEquipment ? 'Pickup ' : 'From '}</span>
            <span>{booking.pickup}</span>
            <span>{booking.isEquipment ? ' · Return by ' : ' · Until '}</span>
            <span>{booking.returnBy}</span>
          </p>
        </div>
        <span className={`pill p-${booking.tone}`}>
          {booking.stateLabel}
          {booking.daysOverdue ? ` · ${booking.daysOverdue}d` : ''}
        </span>
      </div>

      {booking.state === 'pending' ? (
        <div className="mt-3 border-t border-hairline pt-3">
          {booking.notReady ? (
            <p className="text-[13px] text-ink-soft">{booking.notReady}</p>
          ) : null}
          {!booking.notReady && booking.clash ? (
            <p className="text-[13px] font-semibold text-status-red">
              Another approved reservation already holds these hours. Adjust the date, or settle it
              on the Booking calendar.
            </p>
          ) : null}
          {!booking.notReady && booking.isEquipment && booking.serviceable === 0 ? (
            <p className="text-[13px] font-semibold text-status-red">
              No count is set for this item yet. Set how many the office owns on the Resources
              page.
            </p>
          ) : null}

          {canApproveInline ? (
            <div className="flex flex-wrap items-center gap-2">
              {booking.isEquipment ? (
                <label className="flex items-center gap-2 text-[13px] text-ink">
                  Lend
                  <input
                    type="number"
                    min="1"
                    step="1"
                    inputMode="numeric"
                    value={qty}
                    onChange={(e) => setQty(Number(e.target.value))}
                    className="st-filter-field inv-num"
                    aria-label="How many to lend"
                  />
                </label>
              ) : null}
              <button
                type="button"
                disabled={pending || !qtyValid}
                onClick={() => {
                  setError('');
                  startTransition(async () => {
                    const result = await approveLoan(
                      booking.id,
                      booking.isEquipment ? qty : null,
                    );
                    if (result.ok) {
                      router.refresh();
                    } else {
                      setError(result.message);
                    }
                  });
                }}
                className="cr-btn cr-btn-sm cr-btn-primary"
              >
                {booking.isEquipment ? 'Approve borrowing' : 'Approve reservation'}
              </button>
              {booking.isEquipment ? (
                <span
                  className={`text-[12px] ${
                    qty > free ? 'font-semibold text-status-red' : 'text-ink-soft'
                  }`}
                >
                  {qty > free
                    ? `Not enough: only ${free} free for these dates.`
                    : `${free} free for these dates`}
                </span>
              ) : null}
            </div>
          ) : null}

          {error ? (
            <p className="mt-2 text-[13px] font-semibold text-status-red" role="alert">
              {error}
            </p>
          ) : null}
        </div>
      ) : null}

      <p className="mt-3 text-[12px] text-ink-soft">
        {booking.state === 'pending' ? (
          <span>The request can&rsquo;t move to Approved until this is approved. </span>
        ) : null}
        {booking.state === 'reserved' || booking.state === 'missed' ? (
          <span>Handing the items over completes the request. </span>
        ) : null}
        {booking.canManage ? (
          <a href={booking.deskUrl} className="text-green underline">
            Open the lending desk
          </a>
        ) : null}
      </p>
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
