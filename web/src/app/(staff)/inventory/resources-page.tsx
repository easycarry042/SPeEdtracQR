'use client';

import { useState, useTransition } from 'react';
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

/**
 * The Resources page.
 *
 * The desk leads — what needs a decision today — and the stock follows. A
 * borrowing runs pending → approved → handed over → returned, and nothing
 * rejoins the stock on its return date: a handed-over loan holds its units
 * until someone records the return, however late that is.
 */
type DeskTab = 'approve' | 'overdue' | 'pickup' | 'on_loan' | 'returned';

export function ResourcesPage({
  desk,
  facilities,
  equipment,
  tiles,
  history,
  canEditCatalog,
}: {
  desk: Desk;
  facilities: FacilityRow[];
  equipment: EquipmentRow[];
  tiles: Tiles;
  history: Record<number, Array<{ line: string; who: string | null; when: string }>>;
  canEditCatalog: boolean;
  asOf: string;
}) {
  const [tab, setTab] = useState<DeskTab>(
    desk.overdue.length > 0 ? 'overdue' : desk.approve.length > 0 ? 'approve' : 'pickup',
  );
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(null);

  const rows =
    tab === 'approve'
      ? desk.approve
      : tab === 'overdue'
        ? desk.overdue
        : tab === 'pickup'
          ? desk.pickup
          : tab === 'on_loan'
            ? desk.onLoan
            : desk.returned;

  return (
    <>
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

      {notice ? (
        <p className={notice.ok ? 'callout p-green mt-4' : 'callout p-amber mt-4'} role="status">
          {notice.message}
        </p>
      ) : null}

      {/* ── Lending desk ─────────────────────────────────────────────────── */}
      <section className="panel mt-5">
        <div className="inv-desk-tabs" role="tablist" aria-label="Lending desk">
          {(
            [
              ['approve', 'To approve', desk.approve.length],
              ['overdue', 'Overdue', desk.overdue.length],
              ['pickup', 'For pickup', desk.pickup.length],
              ['on_loan', 'On loan', desk.onLoan.length],
              ['returned', 'Returned', desk.returned.length],
            ] as const
          ).map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={tab === key}
              className={tab === key ? 'on' : undefined}
              onClick={() => setTab(key)}
            >
              {label}
              <span className="lookup-tab-count">{count}</span>
            </button>
          ))}
        </div>

        {rows.length === 0 ? (
          <div className="inv-empty">
            <p className="cr-empty-title">Nothing here</p>
            <p className="cr-empty-msg">
              {tab === 'approve'
                ? 'Borrowing and reservation requests appear here once their request is assigned and accepted.'
                : tab === 'overdue'
                  ? 'Nothing is past its return date.'
                  : 'Nothing in this queue right now.'}
            </p>
          </div>
        ) : (
          <ul className="mt-3 space-y-3">
            {rows.map((row) => (
              <LoanCard key={row.bookingId} row={row} tab={tab} onNotice={setNotice} />
            ))}
          </ul>
        )}
      </section>

      {/* ── Stock ────────────────────────────────────────────────────────── */}
      <section className="panel mt-5">
        <h2 className="dm-section">Equipment</h2>
        <p className="cc-panel-sub">
          Available = owned − out of service − on loan − promised. Approval checks the request&apos;s
          own dates; hand-over checks the shelf.
        </p>

        <div className="table-wrap mt-3">
          <table className="st-table">
            <thead>
              <tr>
                <th scope="col">Item</th>
                <th scope="col">Owned</th>
                <th scope="col">Out of service</th>
                <th scope="col">On loan</th>
                <th scope="col">Promised</th>
                <th scope="col">Available</th>
                <th scope="col" />
              </tr>
            </thead>
            <tbody>
              {equipment.map((item) => (
                <EquipmentRowView key={item.id} item={item} history={history[item.id] ?? []} onNotice={setNotice} />
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="panel mt-5">
        <h2 className="dm-section">Facilities</h2>

        <div className="deptcards mt-3">
          {facilities.map((facility) => (
            <article key={facility.id} className="deptcard">
              <div className="flex items-center justify-between gap-2">
                <h3 className="font-bold text-ink">{facility.name}</h3>
                <span className={facility.tone === 'free' ? 'pill p-green' : 'pill p-amber'}>
                  {facility.tone === 'free' ? 'Free now' : 'In use'}
                </span>
              </div>

              {facility.location ? <p className="dir-sub">{facility.location}</p> : null}
              {facility.capacity ? <p className="dir-sub">Seats {facility.capacity}</p> : null}

              {facility.tone === 'busy' ? (
                <p className="mt-2 text-[length:var(--fs-small)]">
                  {facility.busyWith} · until {facility.busyUntil}
                </p>
              ) : facility.nextAt ? (
                <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
                  Next booked {facility.nextAt}
                </p>
              ) : (
                <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">Nothing booked.</p>
              )}
            </article>
          ))}
        </div>
      </section>

      {canEditCatalog ? (
        <p className="mt-4 text-[length:var(--fs-small)] text-ink-soft">
          Adding, renaming and removing items is on the super admin&apos;s Resources setup.
        </p>
      ) : null}
    </>
  );
}


/** One borrowing on the desk, with the actions its state allows. */
function LoanCard({
  row,
  tab,
  onNotice,
}: {
  row: LoanRow;
  tab: DeskTab;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [grant, setGrant] = useState(String(row.requested || row.units || 1));
  const [returning, setReturning] = useState(false);
  const [extending, setExtending] = useState(false);

  const run = (action: () => Promise<{ ok: boolean; message: string }>): void => {
    startTransition(async () => {
      const result = await action();
      onNotice(result);

      if (result.ok) {
        router.refresh();
      }
    });
  };

  return (
    <li className="inv-history">
      <div className="flex flex-wrap items-baseline gap-2">
        <strong className="text-ink">{row.item}</strong>
        {row.isEquipment ? <span className="chip">{row.units} units</span> : null}
        {row.tracking ? <span className="id-chip">{row.tracking}</span> : null}
        {row.daysOverdue > 0 ? (
          <span className="cc-tag cc-tag-late">{row.daysOverdue} day(s) overdue</span>
        ) : null}
      </div>

      <p className="dir-sub">
        {row.borrower}
        {row.handler ? ` · handled by ${row.handler}` : ''} · {row.window}
      </p>

      {/* Why it cannot be approved yet, where someone would try. */}
      {row.notReady ? <p className="callout p-amber mt-2">{row.notReady}</p> : null}
      {row.clash ? (
        <p className="callout p-amber mt-2">
          {row.item} is already reserved during that time.
        </p>
      ) : null}
      {row.free !== null ? (
        <p className="inv-free">
          {row.free} free for those dates{row.serviceable !== null ? ` of ${row.serviceable} in service` : ''}.
        </p>
      ) : null}

      {row.state === 'on_loan' || row.state === 'overdue' ? (
        <p className="dir-sub">
          Handed over {row.releasedOn}
          {row.releasedBy ? ` by ${row.releasedBy}` : ''} · {row.outstanding} still out
        </p>
      ) : null}

      {row.state === 'returned' ? (
        <p className="dir-sub">
          Returned {row.returnedOn}
          {row.returnedBy ? ` by ${row.returnedBy}` : ''}
          {row.damaged > 0 ? ` · ${row.damaged} damaged` : ''}
          {row.lost > 0 ? ` · ${row.lost} written off` : ''}
        </p>
      ) : null}

      <div className="gate-actions mt-3">
        {tab === 'approve' ? (
          <>
            {row.isEquipment ? (
              <input
                type="number"
                min={1}
                className="field w-[90px]"
                value={grant}
                onChange={(event) => setGrant(event.target.value)}
                aria-label="How many to lend"
              />
            ) : null}
            <button
              type="button"
              className="cr-btn cr-btn-primary cr-btn-sm"
              disabled={pending || row.notReady !== null}
              onClick={() => run(() => approveLoan(row.bookingId, row.isEquipment ? Number(grant) : null))}
            >
              Approve
            </button>
            <button
              type="button"
              className="cr-btn cr-btn-ghost cr-btn-sm"
              disabled={pending}
              onClick={() => run(() => cancelLoan(row.bookingId))}
            >
              Cancel
            </button>
          </>
        ) : null}

        {row.state === 'reserved' || row.state === 'missed' ? (
          <button
            type="button"
            className="cr-btn cr-btn-primary cr-btn-sm"
            disabled={pending}
            onClick={() => run(() => releaseLoan(row.bookingId))}
          >
            Hand over
          </button>
        ) : null}

        {row.state === 'on_loan' || row.state === 'overdue' ? (
          <>
            <button
              type="button"
              className="cr-btn cr-btn-primary cr-btn-sm"
              disabled={pending}
              onClick={() => setReturning((open) => !open)}
            >
              Record return
            </button>
            {row.isEquipment ? null : (
              <button
                type="button"
                className="cr-btn cr-btn-ghost cr-btn-sm"
                disabled={pending}
                onClick={() => run(() => finishLoan(row.bookingId))}
              >
                Mark done
              </button>
            )}
          </>
        ) : null}

        {['reserved', 'missed', 'on_loan', 'overdue'].includes(row.state) ? (
          <button
            type="button"
            className="cr-btn cr-btn-ghost cr-btn-sm"
            disabled={pending}
            onClick={() => setExtending((open) => !open)}
          >
            Change return date
          </button>
        ) : null}

        {row.state === 'overdue' ? (
          <button
            type="button"
            className="cr-btn cr-btn-ghost cr-btn-sm"
            disabled={pending}
            onClick={() => run(() => remindBorrower(row.bookingId))}
          >
            {row.remindedOn ? `Remind again (last ${row.remindedOn})` : 'Remind borrower'}
          </button>
        ) : null}

        {row.tracking ? (
          <a href={routes.trackShow(row.tracking)} className="cr-link" target="_blank" rel="noopener">
            Open request
          </a>
        ) : null}
      </div>

      {returning ? (
        <ReturnForm
          row={row}
          pending={pending}
          onCancel={() => setReturning(false)}
          onSubmit={(input) => {
            run(() => receiveLoan(row.bookingId, input));
            setReturning(false);
          }}
        />
      ) : null}

      {extending ? (
        <ExtendForm
          pending={pending}
          onCancel={() => setExtending(false)}
          onSubmit={(returnBy) => {
            run(() => extendLoan(row.bookingId, returnBy));
            setExtending(false);
          }}
        />
      ) : null}
    </li>
  );
}

/**
 * Returns can be partial: damaged units move out of service, missing ones come
 * off what the office owns, and whatever is left stays on loan.
 */
function ReturnForm({
  row,
  pending,
  onCancel,
  onSubmit,
}: {
  row: LoanRow;
  pending: boolean;
  onCancel: () => void;
  onSubmit: (input: { returned: number; damaged: number; lost: number; notes: string | null }) => void;
}) {
  const [returned, setReturned] = useState(String(row.outstanding));
  const [damaged, setDamaged] = useState('0');
  const [lost, setLost] = useState('0');
  const [notes, setNotes] = useState('');

  return (
    <div className="dm-panel mt-3">
      <p className="req-label">{row.outstanding} still out on this loan</p>

      <div className="mt-2 flex flex-wrap gap-3">
        <label className="inv-field-label">
          Came back
          <input type="number" min={0} className="field w-[90px]" value={returned} onChange={(e) => setReturned(e.target.value)} />
        </label>
        <label className="inv-field-label">
          Of those, damaged
          <input type="number" min={0} className="field w-[90px]" value={damaged} onChange={(e) => setDamaged(e.target.value)} />
        </label>
        <label className="inv-field-label">
          Missing
          <input type="number" min={0} className="field w-[90px]" value={lost} onChange={(e) => setLost(e.target.value)} />
        </label>
      </div>

      <textarea
        className="field mt-2"
        rows={2}
        placeholder="Notes (optional)"
        value={notes}
        onChange={(event) => setNotes(event.target.value)}
      />

      <div className="gate-actions mt-2">
        <button
          type="button"
          className="cr-btn cr-btn-primary cr-btn-sm"
          disabled={pending}
          onClick={() =>
            onSubmit({
              returned: Number(returned) || 0,
              damaged: Number(damaged) || 0,
              lost: Number(lost) || 0,
              notes: notes.trim() || null,
            })
          }
        >
          Save return
        </button>
        <button type="button" className="cr-btn cr-btn-ghost cr-btn-sm" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </div>
  );
}

/** The pickup stays put; only the return date moves, stock-checked. */
function ExtendForm({
  pending,
  onCancel,
  onSubmit,
}: {
  pending: boolean;
  onCancel: () => void;
  onSubmit: (returnBy: string) => void;
}) {
  const [returnBy, setReturnBy] = useState('');

  return (
    <div className="dm-panel mt-3">
      <label className="inv-field-label">
        New return date
        <input type="date" className="field" value={returnBy} onChange={(e) => setReturnBy(e.target.value)} />
      </label>
      <p className="gate-claim-hint">
        The pickup does not move. The extra days are checked against what is still out.
      </p>
      <div className="gate-actions mt-2">
        <button
          type="button"
          className="cr-btn cr-btn-primary cr-btn-sm"
          disabled={pending || returnBy === ''}
          onClick={() => onSubmit(returnBy)}
        >
          Save
        </button>
        <button type="button" className="cr-btn cr-btn-ghost cr-btn-sm" onClick={onCancel}>
          Cancel
        </button>
      </div>
    </div>
  );
}

function EquipmentRowView({
  item,
  history,
  onNotice,
}: {
  item: EquipmentRow;
  history: Array<{ line: string; who: string | null; when: string }>;
  onNotice: (notice: { ok: boolean; message: string }) => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [editing, setEditing] = useState(false);
  const [owned, setOwned] = useState(String(item.owned));
  const [damaged, setDamaged] = useState(String(item.damaged));
  const [showHistory, setShowHistory] = useState(false);

  return (
    <>
      <tr>
        <td>
          <strong>{item.name}</strong>
          {item.location ? <span className="dir-sub"> · {item.location}</span> : null}
        </td>
        <td>{item.owned}</td>
        <td>{item.damaged}</td>
        <td>{item.onLoan}</td>
        <td>{item.reserved}</td>
        <td>
          <strong>{item.available}</strong>
        </td>
        <td>
          <button type="button" className="inv-edit" onClick={() => setEditing((open) => !open)}>
            Edit counts
          </button>
          {history.length > 0 ? (
            <button type="button" className="cr-link ml-2" onClick={() => setShowHistory((open) => !open)}>
              History
            </button>
          ) : null}
        </td>
      </tr>

      {editing ? (
        <tr>
          <td colSpan={7}>
            <div className="dm-panel">
              <div className="flex flex-wrap items-end gap-3">
                <label className="inv-field-label">
                  Owned
                  <input type="number" min={0} className="field w-[110px]" value={owned} onChange={(e) => setOwned(e.target.value)} />
                </label>
                <label className="inv-field-label">
                  Out of service
                  <input type="number" min={0} className="field w-[110px]" value={damaged} onChange={(e) => setDamaged(e.target.value)} />
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
                        location: item.location,
                      });

                      onNotice(result);

                      if (result.ok) {
                        setEditing(false);
                        router.refresh();
                      }
                    })
                  }
                >
                  Save
                </button>
                <button type="button" className="cr-btn cr-btn-ghost cr-btn-sm" onClick={() => setEditing(false)}>
                  Cancel
                </button>
              </div>
              <p className="gate-claim-hint">
                Owned cannot go below what is on loan plus what is out of service. If some are
                gone for good, record a loss on the loan instead.
              </p>
            </div>
          </td>
        </tr>
      ) : null}

      {showHistory ? (
        <tr>
          <td colSpan={7}>
            <ul className="dm-logs">
              {history.map((entry, index) => (
                <li key={index} className="dm-log">
                  <span className="dm-log-dot" aria-hidden="true" />
                  <span className="dm-log-event">{entry.line}</span>
                  <span className="dm-log-time">
                    {entry.who ? `${entry.who} · ` : ''}
                    {entry.when}
                  </span>
                </li>
              ))}
            </ul>
          </td>
        </tr>
      ) : null}
    </>
  );
}
