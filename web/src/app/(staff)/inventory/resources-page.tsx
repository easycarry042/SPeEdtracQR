'use client';

import { useEffect, useState, useTransition } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import type { Desk, EquipmentRow, FacilityRow, LoanRow, Tiles } from '@/lib/domain/inventory-desk';
import { routes } from '@/lib/routes';
import { StaffStatTile } from '@/components/staff-stat-tile';
import {
  approveLoan,
  cancelLoan,
  extendLoan,
  finishLoan,
  receiveLoan,
  releaseLoan,
  remindBorrower,
  updateStock,
} from '@/app/actions/lending';
import { removeResource, toggleResourceActive } from '@/app/actions/admin';

type DeskTab = 'overdue' | 'approve' | 'pickup' | 'on_loan' | 'returned';

const DESK_TABS: ReadonlyArray< readonly [DeskTab, string]> = [
  ['overdue', 'Overdue'],
  ['approve', 'To approve'],
  ['pickup', 'For pickup'],
  ['on_loan', 'On loan'],
  ['returned', 'Returned'],
] as const;

function preventNonWholeNumber(event: React.KeyboardEvent<HTMLInputElement>): void {
  if (['.', ',', 'e', 'E', '-', '+'].includes(event.key)) {
    event.preventDefault();
  }
}

export function ResourcesPage({
  desk,
  facilities,
  equipment,
  tiles,
  history,
  canEditCatalog,
  canManageBookings = false,
  asOf,
}: {
  desk: Desk;
  facilities: FacilityRow[];
  equipment: EquipmentRow[];
  tiles: Tiles;
  history: Record<number, Array<{ line: string; who: string | null; when: string }>>;
  canEditCatalog: boolean;
  canManageBookings?: boolean;
  asOf: string;
}) {
  const initialTab: DeskTab =
    desk.overdue.length > 0
      ? 'overdue'
      : desk.approve.length > 0
        ? 'approve'
        : desk.pickup.length > 0
          ? 'pickup'
          : desk.onLoan.length > 0
            ? 'on_loan'
            : 'approve';

  const [tab, setTab] = useState<DeskTab>(initialTab);
  const [query, setQuery] = useState('');
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(null);

  useEffect(() => {
    try {
      const kept = sessionStorage.getItem('inv-desk-tab') as DeskTab | null;
      if (kept && DESK_TABS.some(([k]) => k === kept)) {
        setTab(kept);
      }
    } catch {
      // Ignore storage access issues
    }
  }, []);

  const selectTab = (next: DeskTab): void => {
    setTab(next);
    try {
      sessionStorage.setItem('inv-desk-tab', next);
    } catch {
      // Ignore storage access issues
    }
  };

  const countFor = (key: DeskTab): number => {
    switch (key) {
      case 'overdue':
        return desk.overdue.length;
      case 'approve':
        return desk.approve.length;
      case 'pickup':
        return desk.pickup.length;
      case 'on_loan':
        return desk.onLoan.length;
      case 'returned':
        return desk.returned.length;
    }
  };

  const rowsFor = (key: DeskTab): LoanRow[] => {
    const raw =
      key === 'overdue'
        ? desk.overdue
        : key === 'approve'
          ? desk.approve
          : key === 'pickup'
            ? desk.pickup
            : key === 'on_loan'
              ? desk.onLoan
              : desk.returned;

    const q = query.trim().toLowerCase();
    if (!q) {
      return raw;
    }

    return raw.filter((loan) => {
      const hay = [loan.borrower, loan.item, loan.tracking, loan.handler]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      return hay.includes(q);
    });
  };

  const activeRows = rowsFor(tab);

  return (
    <div className="page-shell page-shell-loose">
      {notice ? (
        <div className="panel" style={!notice.ok ? { borderColor: 'var(--red)' } : undefined}>
          <div
            className={`pb text-[13px] font-medium ${
              notice.ok ? 'text-green-deep' : 'text-status-red'
            }`}
            role={notice.ok ? 'status' : 'alert'}
          >
            {notice.message}
          </div>
        </div>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-[13px] text-ink-soft">
          As of {asOf}.{' '}
          {canManageBookings ? (
            <>
              Reservations are managed on the{' '}
              <Link href={routes.bookingsIndex()} className="text-green underline">
                Booking
              </Link>{' '}
              calendar.
            </>
          ) : (
            'Reservations are managed by staff on the Booking calendar.'
          )}
        </p>
        {canEditCatalog ? (
          <Link href={routes.adminResourcesCreate()} className="cr-btn cr-btn-primary">
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add item
          </Link>
        ) : null}
      </div>

      {/* The numbers someone at the counter is actually asked for. */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <StaffStatTile
          label="Available to lend"
          value={tiles.unitsAvailable.toLocaleString('en-US')}
          tone="deep"
          icon="check"
          note={
            tiles.unitsReserved > 0
              ? `${tiles.unitsReserved.toLocaleString('en-US')} more promised to pickups`
              : null
          }
        />
        <StaffStatTile
          label="Out on loan"
          value={tiles.unitsOnLoan.toLocaleString('en-US')}
          tone={tiles.overdue > 0 ? 'amber' : 'mid'}
          icon="clock"
          note={
            tiles.overdue > 0
              ? `${tiles.overdue} ${tiles.overdue === 1 ? 'loan' : 'loans'} overdue`
              : null
          }
        />
        <StaffStatTile
          label="To approve"
          value={tiles.toApprove.toLocaleString('en-US')}
          tone="bright"
          icon="hourglass"
          note={
            tiles.pickupsToday > 0
              ? `${tiles.pickupsToday} pickup${tiles.pickupsToday === 1 ? '' : 's'} today`
              : null
          }
        />
        <StaffStatTile
          label="Facilities free now"
          value={`${tiles.facilitiesFree} of ${tiles.facilitiesTotal}`}
          tone="brightest"
          icon="none"
        />
      </div>

      {/* ── Lending desk ────────────────────────────────────────────────── */}
      <section>
        <h2 className="st-section-title">Lending desk</h2>
        <p className="mt-1 text-[13px] text-ink-soft">
          Approve a borrowing with the number you are lending, hand it over, and mark it returned
          when it comes back. Nothing goes back into stock until someone records the return — a late
          borrower shows up under Overdue.
        </p>

        <div className="inv-desk-bar mt-3">
          <div className="inv-desk-tabs">
            <div className="st-tabs" role="tablist" aria-label="Lending queues">
              {DESK_TABS.map(([key, label]) => {
                const count = countFor(key);
                return (
                  <button
                    key={key}
                    type="button"
                    role="tab"
                    aria-selected={tab === key}
                    className={tab === key ? 'on' : ''}
                    onClick={() => selectTab(key)}
                  >
                    {label}
                    <span
                      className={`st-tab-count${
                        key === 'overdue' && count > 0 ? ' inv-tab-alert' : ''
                      }`}
                    >
                      {count}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          <label htmlFor="lendingSearch" className="sr-only">
            Search the lending desk
          </label>
          <div className="st-search">
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="7" />
              <path strokeLinecap="round" d="m20 20-3.5-3.5" />
            </svg>
            <input
              id="lendingSearch"
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search borrower, item or tracking number"
            />
          </div>
        </div>

        <div className="panel mt-3">
          <div className="table-wrap relative">
            <table className="reg">
              <thead>
                {tab === 'overdue' ? (
                  <tr>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Still out</th>
                    <th>Was due</th>
                    <th>Reminded</th>
                    <th />
                  </tr>
                ) : tab === 'approve' ? (
                  <tr>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Dates</th>
                    <th>Asked for</th>
                    <th>Lend</th>
                    <th />
                  </tr>
                ) : tab === 'pickup' ? (
                  <tr>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Lending</th>
                    <th>Pickup</th>
                    <th>Return by</th>
                    <th />
                  </tr>
                ) : tab === 'on_loan' ? (
                  <tr>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Still out</th>
                    <th>Handed over</th>
                    <th>Due back</th>
                    <th />
                  </tr>
                ) : (
                  <tr>
                    <th>Borrower</th>
                    <th>Item</th>
                    <th>Lent</th>
                    <th>Came back</th>
                    <th>Closed</th>
                    <th />
                  </tr>
                )}
              </thead>

              {activeRows.length === 0 ? (
                <tbody>
                  <tr>
                    <td colSpan={6} className="inv-empty">
                      {tab === 'overdue'
                        ? 'Nothing overdue. Every loan past its return date has been brought back.'
                        : tab === 'approve'
                          ? 'No borrowing or reservation requests are waiting.'
                          : tab === 'pickup'
                            ? 'No approved borrowings are waiting to be picked up.'
                            : tab === 'on_loan'
                              ? 'Nothing is out on loan right now.'
                              : 'No loans were closed in the last 30 days.'}
                    </td>
                  </tr>
                </tbody>
              ) : (
                activeRows.map((loan) => (
                  <LoanRowBody key={loan.bookingId} loan={loan} onNotice={setNotice} />
                ))
              )}
            </table>
          </div>
        </div>
      </section>

      {/* ── Equipment ───────────────────────────────────────────────────── */}
      <section>
        <h2 className="st-section-title">Equipment</h2>
        <p className="mt-1 text-[13px] text-ink-soft">
          Counted stock. <strong>Available</strong> is what you can still promise: owned, less out
          of service, less what is out on loan, less what is promised to approved pickups. Approving
          checks the request&apos;s own dates.
        </p>

        <div className="panel mt-3">
          <div className="table-wrap">
            <table className="reg">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Owned</th>
                  <th>Out of service</th>
                  <th>On loan</th>
                  <th>Promised</th>
                  <th>Available</th>
                  <th />
                </tr>
              </thead>

              {equipment.length === 0 ? (
                <tbody>
                  <tr>
                    <td colSpan={7} className="inv-empty">
                      No equipment yet. Add it under Admin → Resources.
                    </td>
                  </tr>
                </tbody>
              ) : (
                equipment.map((item) => (
                  <EquipmentRowView
                    key={item.id}
                    item={item}
                    history={history[item.id] ?? []}
                    canEditCatalog={canEditCatalog}
                    onNotice={setNotice}
                  />
                ))
              )}
            </table>
          </div>
        </div>
      </section>

      {/* ── Facilities ──────────────────────────────────────────────────── */}
      <section>
        <h2 className="st-section-title">Facilities</h2>
        <p className="mt-1 text-[13px] text-ink-soft">
          One of each — a venue is either free or held by an approved reservation. Mark it done when
          people leave early, and it is free again straight away.
        </p>

        <div className="panel mt-3">
          <div className="table-wrap">
            <table className="reg">
              <thead>
                <tr>
                  <th>Facility</th>
                  <th>Holds</th>
                  <th>Right now</th>
                  <th>Next reserved</th>
                  <th />
                </tr>
              </thead>

              {facilities.length === 0 ? (
                <tbody>
                  <tr>
                    <td colSpan={5} className="inv-empty">
                      No facilities yet. Add one under Admin → Resources.
                    </td>
                  </tr>
                </tbody>
              ) : (
                facilities.map((facility) => (
                  <FacilityRowView
                    key={facility.id}
                    facility={facility}
                    history={history[facility.id] ?? []}
                    canEditCatalog={canEditCatalog}
                    onNotice={setNotice}
                  />
                ))
              )}
            </table>
          </div>
        </div>
      </section>
    </div>
  );
}

/**
 * Port of `inventory/partials/loan-row.blade.php`.
 * One `<tbody>` per borrowing row so its expandable return/extension rows stay
 * grouped in the table.
 */
function LoanRowBody({
  loan,
  onNotice,
}: {
  loan: LoanRow;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);
  const [extending, setExtending] = useState(false);

  const [qty, setQty] = useState<number>(Math.max(1, loan.requested || 1));
  const [back, setBack] = useState<number>(loan.outstanding);
  const [damaged, setDamaged] = useState<number>(0);
  const [lost, setLost] = useState<number>(0);
  const [notes, setNotes] = useState('');
  const [returnBy, setReturnBy] = useState(loan.defaultReturnDate ?? '');

  const run = (action: () => Promise<{ ok: boolean; message: string }>): void => {
    startTransition(async () => {
      const result = await action();
      onNotice(result);

      if (result.ok) {
        setOpen(false);
        setExtending(false);
        router.refresh();
      }
    });
  };

  const state = loan.state;
  const free = loan.free ?? 0;
  const owned = loan.serviceable ?? 0;
  const out = loan.outstanding;

  const canApproveQty = Number.isInteger(qty) && qty >= 1 && qty <= free;
  const canSaveReturn =
    [back, damaged, lost].every(Number.isInteger) &&
    back + lost >= 1 &&
    back + lost <= out &&
    damaged <= back &&
    damaged >= 0;

  return (
    <tbody>
      <tr>
        <td>
          <p className="nm text-ink">{loan.borrower}</p>
          <p className="text-[12px] text-ink-soft">
            {loan.tracking ? (
              <Link
                href={routes.trackShow(loan.tracking)}
                className="mono text-green hover:underline"
              >
                {loan.tracking}
              </Link>
            ) : null}
            {(state === 'overdue' || state === 'on_loan') && loan.contact
              ? ` · ${loan.contact}`
              : ''}
          </p>
          {state === 'pending' ? (
            <p className="text-[12px] text-ink-soft">
              {loan.handler ? `Handled by ${loan.handler}` : 'Not assigned yet'}
            </p>
          ) : null}
        </td>

        <td className="text-ink">{loan.item}</td>

        {state === 'overdue' ? (
          <>
            <td className="font-semibold text-ink">
              {loan.outstanding.toLocaleString('en-US')}
              {loan.outstanding !== loan.units ? (
                <span className="text-[12px] font-normal text-ink-soft">
                  {' '}
                  of {loan.units.toLocaleString('en-US')}
                </span>
              ) : null}
            </td>
            <td>
              <p className="text-ink">{loan.dueOn}</p>
              <span className="pill p-red mt-1">
                {loan.daysOverdue} {loan.daysOverdue === 1 ? 'day' : 'days'} overdue
              </span>
            </td>
            <td className="muted">{loan.remindedOn ?? 'Not yet'}</td>
            <td>
              <div className="flex flex-wrap justify-end gap-2">
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  disabled={pending}
                  title={loan.email ? `Email ${loan.email}` : 'No email on file'}
                  onClick={() => run(() => remindBorrower(loan.bookingId))}
                >
                  Send reminder
                </button>
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  aria-expanded={extending}
                  onClick={() => {
                    setExtending(!extending);
                    setOpen(false);
                  }}
                >
                  Change return date
                </button>
                <button
                  type="button"
                  className="cr-btn cr-btn-sm cr-btn-primary"
                  aria-expanded={open}
                  onClick={() => {
                    setOpen(!open);
                    setExtending(false);
                  }}
                >
                  Record return
                </button>
              </div>
            </td>
          </>
        ) : state === 'pending' ? (
          <>
            <td className="muted">{loan.window}</td>
            <td className="muted">
              {loan.isEquipment ? loan.requested.toLocaleString('en-US') : '—'}
            </td>
            {loan.notReady ? (
              <td colSpan={2}>
                <div className="flex flex-wrap items-center justify-end gap-2">
                  <span className="text-right text-[12px] text-ink-soft">{loan.notReady}</span>
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm cr-btn-danger"
                    disabled={pending}
                    onClick={() => {
                      if (window.confirm('Decline this request? The borrower is emailed.')) {
                        run(() => cancelLoan(loan.bookingId));
                      }
                    }}
                  >
                    Decline
                  </button>
                </div>
              </td>
            ) : loan.isEquipment ? (
              <td colSpan={2}>
                <div className="flex flex-wrap items-center justify-end gap-2">
                  <label className="sr-only" htmlFor={`lend-${loan.bookingId}`}>
                    How many {loan.item} to lend
                  </label>
                  <input
                    id={`lend-${loan.bookingId}`}
                    type="number"
                    min={1}
                    step={1}
                    required
                    inputMode="numeric"
                    onKeyDown={preventNonWholeNumber}
                    value={Number.isNaN(qty) ? '' : qty}
                    onChange={(event) => setQty(Number.parseInt(event.target.value, 10))}
                    className="st-filter-field inv-num"
                  />
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm cr-btn-primary"
                    disabled={pending || !canApproveQty}
                    onClick={() => run(() => approveLoan(loan.bookingId, qty))}
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm cr-btn-danger"
                    disabled={pending}
                    onClick={() => {
                      if (window.confirm('Decline this request? The borrower is emailed.')) {
                        run(() => cancelLoan(loan.bookingId));
                      }
                    }}
                  >
                    Decline
                  </button>
                </div>
                <p
                  className={`mt-1 text-right text-[12px] ${
                    qty > free ? 'font-semibold text-status-red' : 'text-ink-soft'
                  }`}
                  role="status"
                >
                  {owned === 0
                    ? 'No count set for this item yet — set how many the office owns below.'
                    : qty > free
                      ? `Not enough — only ${free} free for these dates. Lend fewer or reschedule.`
                      : `${free} free for these dates · ${Math.max(
                          0,
                          free - (Number.isInteger(qty) ? qty : 0),
                        )} left after this`}
                </p>
              </td>
            ) : (
              <td colSpan={2}>
                <div className="flex flex-wrap items-center justify-end gap-2">
                  {loan.clash ? (
                    <span className="pill p-red">Overlaps an approved reservation</span>
                  ) : null}
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm cr-btn-primary"
                    disabled={pending || loan.clash}
                    onClick={() => run(() => approveLoan(loan.bookingId, null))}
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    className="cr-btn cr-btn-sm cr-btn-danger"
                    disabled={pending}
                    onClick={() => {
                      if (window.confirm('Decline this request? The borrower is emailed.')) {
                        run(() => cancelLoan(loan.bookingId));
                      }
                    }}
                  >
                    Decline
                  </button>
                </div>
              </td>
            )}
          </>
        ) : state === 'reserved' || state === 'missed' ? (
          <>
            <td className="font-semibold text-ink">{loan.units.toLocaleString('en-US')}</td>
            <td>
              <p className="text-ink">{loan.pickupOn}</p>
              {state === 'missed' ? (
                <span className="pill p-amber mt-1">Not picked up</span>
              ) : loan.pickupToday ? (
                <span className="pill p-green mt-1">Today</span>
              ) : null}
            </td>
            <td className="muted">{loan.dueOn}</td>
            <td>
              <div className="flex flex-wrap justify-end gap-2">
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  aria-expanded={extending}
                  onClick={() => {
                    setExtending(!extending);
                    setOpen(false);
                  }}
                >
                  Change return date
                </button>
                <button
                  type="button"
                  className="cr-btn cr-btn-sm cr-btn-primary"
                  disabled={pending}
                  onClick={() => run(() => releaseLoan(loan.bookingId))}
                >
                  Hand over
                </button>
                <button
                  type="button"
                  className="cr-btn cr-btn-sm cr-btn-danger"
                  disabled={pending}
                  onClick={() => {
                    if (window.confirm('Cancel this borrowing? The items are freed for others.')) {
                      run(() => cancelLoan(loan.bookingId));
                    }
                  }}
                >
                  Cancel
                </button>
              </div>
            </td>
          </>
        ) : state === 'on_loan' ? (
          <>
            <td className="font-semibold text-ink">
              {loan.outstanding.toLocaleString('en-US')}
              {loan.outstanding !== loan.units ? (
                <span className="text-[12px] font-normal text-ink-soft">
                  {' '}
                  of {loan.units.toLocaleString('en-US')}
                </span>
              ) : null}
            </td>
            <td className="muted">
              {loan.releasedOn}
              {loan.releasedBy ? (
                <>
                  <br />
                  <span className="text-[12px]">by {loan.releasedBy}</span>
                </>
              ) : null}
            </td>
            <td>
              <p className="text-ink">{loan.dueOn}</p>
              {loan.dueToday ? <span className="pill p-amber mt-1">Due today</span> : null}
            </td>
            <td>
              <div className="flex flex-wrap justify-end gap-2">
                <button
                  type="button"
                  className="cr-btn cr-btn-sm"
                  aria-expanded={extending}
                  onClick={() => {
                    setExtending(!extending);
                    setOpen(false);
                  }}
                >
                  Change return date
                </button>
                <button
                  type="button"
                  className="cr-btn cr-btn-sm cr-btn-primary"
                  aria-expanded={open}
                  onClick={() => {
                    setOpen(!open);
                    setExtending(false);
                  }}
                >
                  Record return
                </button>
              </div>
            </td>
          </>
        ) : (
          <>
            <td className="muted">{loan.units.toLocaleString('en-US')}</td>
            <td>
              <div className="flex flex-wrap gap-1">
                {loan.returned - loan.damaged > 0 ? (
                  <span className="pill p-green">
                    {(loan.returned - loan.damaged).toLocaleString('en-US')} good
                  </span>
                ) : null}
                {loan.damaged > 0 ? (
                  <span className="pill p-amber">{loan.damaged.toLocaleString('en-US')} damaged</span>
                ) : null}
                {loan.lost > 0 ? (
                  <span className="pill p-red">{loan.lost.toLocaleString('en-US')} missing</span>
                ) : null}
              </div>
              {loan.notes ? <p className="mt-1 text-[12px] text-ink-soft">{loan.notes}</p> : null}
            </td>
            <td className="muted">
              {loan.returnedOn}
              {loan.returnedBy ? (
                <>
                  <br />
                  <span className="text-[12px]">by {loan.returnedBy}</span>
                </>
              ) : null}
            </td>
            <td />
          </>
        )}
      </tr>

      {(state === 'overdue' || state === 'on_loan') && open ? (
        <tr>
          <td colSpan={6} className="inv-edit">
            <div className="flex flex-wrap items-end gap-3">
              <label className="block">
                <span className="inv-field-label">Came back</span>
                <input
                  type="number"
                  min={0}
                  step={1}
                  required
                  inputMode="numeric"
                  onKeyDown={preventNonWholeNumber}
                  value={Number.isNaN(back) ? '' : back}
                  onChange={(event) => setBack(Number.parseInt(event.target.value, 10) || 0)}
                  className="st-filter-field inv-num"
                />
              </label>
              <label className="block">
                <span className="inv-field-label">Of those, damaged</span>
                <input
                  type="number"
                  min={0}
                  step={1}
                  inputMode="numeric"
                  onKeyDown={preventNonWholeNumber}
                  value={Number.isNaN(damaged) ? '' : damaged}
                  onChange={(event) => setDamaged(Number.parseInt(event.target.value, 10) || 0)}
                  className="st-filter-field inv-num"
                />
              </label>
              <label className="block">
                <span className="inv-field-label">Missing</span>
                <input
                  type="number"
                  min={0}
                  step={1}
                  inputMode="numeric"
                  onKeyDown={preventNonWholeNumber}
                  value={Number.isNaN(lost) ? '' : lost}
                  onChange={(event) => setLost(Number.parseInt(event.target.value, 10) || 0)}
                  className="st-filter-field inv-num"
                />
              </label>
              <label className="block min-w-[12rem] flex-1">
                <span className="inv-field-label">Note (optional)</span>
                <input
                  type="text"
                  maxLength={1000}
                  placeholder="e.g. two legs cracked"
                  value={notes}
                  onChange={(event) => setNotes(event.target.value)}
                  className="st-filter-field w-full"
                />
              </label>
              <button
                type="button"
                className="cr-btn cr-btn-primary cr-btn-sm"
                disabled={pending || !canSaveReturn}
                onClick={() =>
                  run(() =>
                    receiveLoan(loan.bookingId, {
                      returned: back,
                      damaged,
                      lost,
                      notes: notes.trim() || null,
                    }),
                  )
                }
              >
                Save return
              </button>
              <p
                className={`w-full text-[12px] ${
                  back + lost > out || damaged > back
                    ? 'font-semibold text-status-red'
                    : 'text-ink-soft'
                }`}
                role="status"
              >
                {back + lost > out
                  ? `Only ${out} are out on this loan.`
                  : damaged > back
                    ? 'Damaged ones are part of what came back.'
                    : out - back - lost > 0
                      ? `${out - back - lost} will stay on loan until they come back.`
                      : 'This closes the loan. Damaged ones go out of service; missing ones come off the count.'}
              </p>
            </div>
          </td>
        </tr>
      ) : null}

      {(state === 'overdue' || state === 'on_loan' || state === 'reserved' || state === 'missed') &&
      extending ? (
        <tr>
          <td colSpan={6} className="inv-edit">
            <div className="flex flex-wrap items-end gap-3">
              <label className="block">
                <span className="inv-field-label">New return date</span>
                <input
                  type="date"
                  required
                  min={loan.minReturnDate}
                  value={returnBy}
                  onChange={(event) => setReturnBy(event.target.value)}
                  className="st-filter-field inv-text"
                />
              </label>
              <button
                type="button"
                className="cr-btn cr-btn-primary cr-btn-sm"
                disabled={pending || returnBy === ''}
                onClick={() => run(() => extendLoan(loan.bookingId, returnBy))}
              >
                Save return date
              </button>
              <p className="w-full text-[12px] text-ink-soft">
                Due back {loan.dueOn} now. Keeping {loan.isEquipment ? 'them' : 'it'} longer is
                checked against what is already promised for those days
                {loan.email ? ', and the borrower is emailed the new date' : ''}.
              </p>
            </div>
          </td>
        </tr>
      ) : null}
    </tbody>
  );
}

function EquipmentRowView({
  item,
  history,
  canEditCatalog,
  onNotice,
}: {
  item: EquipmentRow;
  history: Array<{ line: string; who: string | null; when: string }>;
  canEditCatalog: boolean;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState(false);
  const [location, setLocation] = useState(item.location ?? '');
  const [owned, setOwned] = useState(String(item.owned));
  const [damaged, setDamaged] = useState(String(item.damaged));

  const serviceable = Math.max(0, item.owned - item.damaged);
  const pct =
    item.pct ?? (serviceable > 0 ? Math.min(100, Math.round((item.available / serviceable) * 100)) : 0);

  return (
    <tbody>
      <tr>
        <td>
          <p className="nm text-ink">{item.name}</p>
          <p className="text-[12px] text-ink-soft">{item.location || 'No location set'}</p>
          {item.description ? (
            <p className="max-w-xs truncate text-[11.5px] text-ink-soft" title={item.description}>
              {item.description}
            </p>
          ) : null}
          {!item.isActive ? <span className="pill p-muted mt-1">Inactive</span> : null}
        </td>
        <td className="muted">
          {item.owned ? item.owned.toLocaleString('en-US') : 'Not counted'}
        </td>
        <td className="muted">
          {item.damaged ? item.damaged.toLocaleString('en-US') : '—'}
        </td>
        <td className="muted">
          {item.onLoan ? item.onLoan.toLocaleString('en-US') : '—'}
        </td>
        <td className="muted">
          {item.reserved ? item.reserved.toLocaleString('en-US') : '—'}
        </td>
        <td>
          {item.owned === 0 ? (
            <span className="pill p-muted">Set a count</span>
          ) : (
            <>
              <p className={`inv-free ${item.available === 0 ? 'is-none' : ''}`}>
                {item.available.toLocaleString('en-US')}
              </p>
              <div
                className="inv-bar"
                role="img"
                aria-label={`${item.available} of ${serviceable} available`}
              >
                <div
                  className={`inv-bar-fill ${item.available === 0 ? 'is-none' : ''}`}
                  style={{ width: `${pct}%` }}
                />
              </div>
            </>
          )}
        </td>
        <td>
          <div className="flex justify-end">
            <button
              type="button"
              className="cr-btn cr-btn-sm"
              aria-expanded={editing}
              onClick={() => setEditing(!editing)}
            >
              Count &amp; history
            </button>
          </div>
        </td>
      </tr>

      {editing ? (
        <tr>
          <td colSpan={7} className="inv-edit">
            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
              <div className="flex flex-wrap items-end gap-3">
                <label className="block">
                  <span className="inv-field-label">Where it is kept</span>
                  <input
                    type="text"
                    value={location}
                    onChange={(event) => setLocation(event.target.value)}
                    placeholder="e.g. GSO stockroom"
                    className="st-filter-field inv-text"
                  />
                </label>
                <label className="block">
                  <span className="inv-field-label">How many owned</span>
                  <input
                    type="number"
                    min={0}
                    step={1}
                    inputMode="numeric"
                    onKeyDown={preventNonWholeNumber}
                    value={owned}
                    onChange={(event) => setOwned(event.target.value)}
                    className="st-filter-field inv-num"
                  />
                </label>
                <label className="block">
                  <span className="inv-field-label">Out of service</span>
                  <input
                    type="number"
                    min={0}
                    step={1}
                    inputMode="numeric"
                    onKeyDown={preventNonWholeNumber}
                    value={damaged}
                    onChange={(event) => setDamaged(event.target.value)}
                    className="st-filter-field inv-num"
                  />
                </label>
                <button
                  type="button"
                  className="cr-btn cr-btn-primary cr-btn-sm"
                  disabled={pending}
                  onClick={() =>
                    startTransition(async () => {
                      const result = await updateStock(item.id, {
                        totalQuantity: owned === '' ? null : Number(owned),
                        damagedQuantity: damaged === '' ? 0 : Number(damaged),
                        capacity: null,
                        location: location.trim() || null,
                      });
                      onNotice(result);
                      if (result.ok) {
                        router.refresh();
                      }
                    })
                  }
                >
                  Save
                </button>
                <p className="w-full text-[12px] text-ink-soft">
                  Bought more, or repaired some? Change the counts here. Items lost by a borrower
                  are written off from their loan instead, so the record shows who had them.
                </p>
              </div>

              <div>
                <p className="inv-field-label">Recent history</p>
                <HistoryList entries={history} />
              </div>
            </div>

            {canEditCatalog ? (
              <CatalogActions item={item} what="This item" onNotice={onNotice} />
            ) : null}
          </td>
        </tr>
      ) : null}
    </tbody>
  );
}

function FacilityRowView({
  facility,
  history,
  canEditCatalog,
  onNotice,
}: {
  facility: FacilityRow;
  history: Array<{ line: string; who: string | null; when: string }>;
  canEditCatalog: boolean;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState(false);
  const [location, setLocation] = useState(facility.location ?? '');
  const [capacity, setCapacity] = useState(
    facility.capacity !== null ? String(facility.capacity) : '',
  );

  const detail =
    facility.tone === 'busy' && facility.busyWith
      ? `${facility.busyWith}${facility.busyUntil ? ` · until ${facility.busyUntil}` : ''}`
      : null;

  return (
    <tbody>
      <tr>
        <td>
          <p className="nm text-ink">{facility.name}</p>
          <p className="text-[12px] text-ink-soft">{facility.location || 'No location set'}</p>
          {facility.description ? (
            <p
              className="max-w-xs truncate text-[11.5px] text-ink-soft"
              title={facility.description}
            >
              {facility.description}
            </p>
          ) : null}
          {!facility.isActive ? <span className="pill p-muted mt-1">Inactive</span> : null}
        </td>
        <td className="muted">
          {facility.capacity ? `${facility.capacity.toLocaleString('en-US')} people` : '—'}
        </td>
        <td>
          <span className={`pill ${facility.tone === 'free' ? 'p-green' : 'p-amber'}`}>
            {facility.tone === 'free' ? 'Free' : 'In use'}
          </span>
          {detail ? <span className="ml-1 text-[12px] text-ink-soft">{detail}</span> : null}
        </td>
        <td className="muted">{facility.nextAt ?? 'Nothing booked'}</td>
        <td>
          <div className="flex flex-wrap justify-end gap-2">
            {facility.bookingId ? (
              <button
                type="button"
                className="cr-btn cr-btn-sm cr-btn-primary"
                disabled={pending}
                onClick={() => {
                  if (
                    window.confirm(
                      `Mark ${facility.name} done? It will show as free straight away.`,
                    )
                  ) {
                    startTransition(async () => {
                      const result = await finishLoan(facility.bookingId!);
                      onNotice(result);
                      if (result.ok) {
                        router.refresh();
                      }
                    });
                  }
                }}
              >
                Done
              </button>
            ) : null}
            <button
              type="button"
              className="cr-btn cr-btn-sm"
              aria-expanded={editing}
              onClick={() => setEditing(!editing)}
            >
              Details
            </button>
          </div>
        </td>
      </tr>

      {editing ? (
        <tr>
          <td colSpan={5} className="inv-edit">
            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
              <div className="flex flex-wrap items-end gap-3">
                <label className="block">
                  <span className="inv-field-label">Where it is</span>
                  <input
                    type="text"
                    value={location}
                    onChange={(event) => setLocation(event.target.value)}
                    placeholder="e.g. Beside the Municipal Hall"
                    className="st-filter-field inv-text"
                  />
                </label>
                <label className="block">
                  <span className="inv-field-label">Holds (people)</span>
                  <input
                    type="number"
                    min={0}
                    step={1}
                    inputMode="numeric"
                    onKeyDown={preventNonWholeNumber}
                    value={capacity}
                    onChange={(event) => setCapacity(event.target.value)}
                    className="st-filter-field inv-num"
                  />
                </label>
                <button
                  type="button"
                  className="cr-btn cr-btn-primary cr-btn-sm"
                  disabled={pending}
                  onClick={() =>
                    startTransition(async () => {
                      const result = await updateStock(facility.id, {
                        totalQuantity: null,
                        damagedQuantity: 0,
                        capacity: capacity === '' ? null : Number(capacity),
                        location: location.trim() || null,
                      });
                      onNotice(result);
                      if (result.ok) {
                        router.refresh();
                      }
                    })
                  }
                >
                  Save
                </button>
              </div>

              <div>
                <p className="inv-field-label">Recent history</p>
                <HistoryList entries={history} />
              </div>
            </div>

            {canEditCatalog ? (
              <CatalogActions item={facility} what="This venue" onNotice={onNotice} />
            ) : null}
          </td>
        </tr>
      ) : null}
    </tbody>
  );
}

function HistoryList({
  entries,
}: {
  entries: Array<{ line: string; who: string | null; when: string }>;
}) {
  if (entries.length === 0) {
    return <p className="text-[12px] text-ink-soft">Nothing recorded yet.</p>;
  }

  return (
    <ol className="inv-history">
      {entries.map((entry, index) => (
        <li key={index}>
          <p className="text-[12.5px] text-ink">{entry.line}</p>
          <p className="text-[11.5px] text-ink-soft">
            {entry.when}
            {entry.who ? ` · ${entry.who}` : ''}
          </p>
        </li>
      ))}
    </ol>
  );
}

function CatalogActions({
  item,
  what,
  onNotice,
}: {
  item: { id: number; name: string; isActive: boolean };
  what: string;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [confirming, setConfirming] = useState(false);

  const run = (action: () => Promise<{ ok: boolean; message: string }>): void => {
    setConfirming(false);

    startTransition(async () => {
      const result = await action();
      onNotice(result);

      if (result.ok) {
        router.refresh();
      }
    });
  };

  return (
    <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-hairline pt-3">
      <span className="inv-field-label !mb-0 mr-1">Catalog</span>

      <Link href={routes.adminResourcesEdit(item.id)} className="cr-btn cr-btn-sm">
        Edit details
      </Link>

      <button
        type="button"
        className="cr-btn cr-btn-sm"
        disabled={pending}
        onClick={() => run(() => toggleResourceActive(item.id))}
      >
        {item.isActive ? 'Deactivate' : 'Activate'}
      </button>

      {confirming ? (
        <>
          <button
            type="button"
            className="cr-btn cr-btn-sm cr-btn-danger"
            disabled={pending}
            onClick={() => run(() => removeResource(item.id))}
          >
            Delete {item.name}
          </button>
          <button type="button" className="cr-btn cr-btn-sm" onClick={() => setConfirming(false)}>
            Keep it
          </button>
          <span className="text-[12px] text-ink-soft">
            Its booking history goes too. Deactivating keeps the history.
          </span>
        </>
      ) : (
        <>
          <button
            type="button"
            className="cr-btn cr-btn-sm cr-btn-danger"
            disabled={pending}
            onClick={() => setConfirming(true)}
          >
            Delete
          </button>
          <span className="text-[12px] text-ink-soft">
            {item.isActive
              ? 'Deactivating stops new requests for it; its history stays.'
              : `Inactive — citizens cannot ask for it. ${what} keeps its history.`}
          </span>
        </>
      )}
    </div>
  );
}
