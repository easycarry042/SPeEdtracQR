'use client';

import { useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { approveBooking, cancelBooking, rescheduleBooking } from '@/app/actions/bookings';
import type { CalendarBooking, CalendarData } from '@/lib/domain/booking-calendar';

/** Port of the `bookingCalendar` Alpine component. */
const DOWS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const iso = (d: Date): string =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const fromIso = (value: string): Date => new Date(`${value}T00:00:00`);

type Notice = { ok: boolean; message: string } | null;

export function BookingCalendar({ data }: { data: CalendarData }) {
  const [selected, setSelected] = useState(data.defaultDate);
  const [view, setView] = useState(() => {
    const base = fromIso(data.defaultDate);

    return new Date(base.getFullYear(), base.getMonth(), 1);
  });
  const [picker, setPicker] = useState(false);
  const [pickerYear, setPickerYear] = useState(view.getFullYear());
  const [notice, setNotice] = useState<Notice>(null);
  const pickerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!picker) {
      return;
    }

    const onDown = (event: MouseEvent): void => {
      if (pickerRef.current && !pickerRef.current.contains(event.target as Node)) {
        setPicker(false);
      }
    };
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setPicker(false);
      }
    };

    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [picker]);

  const monthNames = useMemo(
    () => Array.from({ length: 12 }, (_, m) => new Date(2000, m, 1).toLocaleDateString(undefined, { month: 'short' })),
    [],
  );

  /**
   * Whole weeks, Sunday-first, including the days either side of the month —
   * drawn muted rather than leaving the first and last rows ragged.
   */
  const weeks = useMemo(() => {
    const year = view.getFullYear();
    const month = view.getMonth();
    const leading = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const rows = Math.ceil((leading + daysInMonth) / 7);

    return Array.from({ length: rows }, (_, w) =>
      Array.from({ length: 7 }, (_, d) => {
        const date = new Date(year, month, 1 - leading + w * 7 + d);
        const key = iso(date);

        return {
          day: date.getDate(),
          iso: key,
          outside: date.getMonth() !== month,
          meta: data.meta[key] ?? null,
          label: date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }),
        };
      }),
    );
  }, [view, data.meta]);

  const select = (day: string): void => {
    setSelected(day);

    // Picking a day in a neighbouring month moves the month with it.
    const picked = fromIso(day);

    if (picked.getMonth() !== view.getMonth() || picked.getFullYear() !== view.getFullYear()) {
      setView(new Date(picked.getFullYear(), picked.getMonth(), 1));
    }
  };

  const shortLabel = fromIso(selected).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  const monthLabel = view.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
  const dayBookings = data.byDate[selected] ?? [];

  return (
    <div className="page-shell">
      {notice ? (
        <div
          className={`bk-card shrink-0 px-4 py-3 text-[length:var(--fs-small)] font-medium ${notice.ok ? 'text-green-deep' : 'text-status-red'}`}
          style={notice.ok ? undefined : { borderColor: 'var(--red)' }}
          role={notice.ok ? 'status' : 'alert'}
        >
          {notice.message}
        </div>
      ) : null}

      <div className="grid min-h-0 flex-1 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section className="bk-card min-h-[520px] lg:min-h-0" aria-label="Reservation calendar">
          <div className="bk-card-head">
            <div className="relative" ref={pickerRef}>
              <h2>
                <button
                  type="button"
                  onClick={() => {
                    setPickerYear(view.getFullYear());
                    setPicker(!picker);
                  }}
                  className="bk-month bk-month-btn"
                  aria-expanded={picker}
                  aria-haspopup="dialog"
                  aria-label={`Choose month, currently ${monthLabel}`}
                >
                  <span>{monthLabel}</span>
                  <svg
                    className={`h-4 w-4 shrink-0 text-ink-soft transition-transform${picker ? ' rotate-180' : ''}`}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.4"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
              </h2>
              {picker ? (
                <div className="bk-picker" role="dialog" aria-label="Choose month and year">
                  <div className="flex items-center justify-between">
                    <button type="button" onClick={() => setPickerYear(pickerYear - 1)} aria-label="Previous year" className="bk-picker-step">
                      <Chevron d="M15 19l-7-7 7-7" />
                    </button>
                    <label className="sr-only" htmlFor="bk-picker-year">
                      Year
                    </label>
                    <input
                      id="bk-picker-year"
                      type="number"
                      inputMode="numeric"
                      min={1900}
                      max={2999}
                      value={pickerYear}
                      onChange={(event) => setPickerYear(Number(event.target.value))}
                      className="bk-picker-year"
                    />
                    <button type="button" onClick={() => setPickerYear(pickerYear + 1)} aria-label="Next year" className="bk-picker-step">
                      <Chevron d="M9 5l7 7-7 7" />
                    </button>
                  </div>
                  <div className="mt-3 grid grid-cols-3 gap-1.5">
                    {monthNames.map((name, month) => {
                      const on = month === view.getMonth() && pickerYear === view.getFullYear();

                      return (
                        <button
                          key={name}
                          type="button"
                          onClick={() => {
                            if (Number.isFinite(pickerYear) && pickerYear >= 1900 && pickerYear <= 2999) {
                              setView(new Date(pickerYear, month, 1));
                              setPicker(false);
                            }
                          }}
                          className={`bk-picker-month${on ? ' on' : ''}`}
                          aria-pressed={on}
                        >
                          {name}
                        </button>
                      );
                    })}
                  </div>
                </div>
              ) : null}
            </div>

            <div className="bk-nav" role="group" aria-label="Change month">
              <button type="button" onClick={() => setView(new Date(view.getFullYear(), view.getMonth() - 1, 1))} aria-label="Previous month">
                <Chevron d="M15 19l-7-7 7-7" />
              </button>
              <button
                type="button"
                onClick={() => {
                  const t = new Date();
                  setView(new Date(t.getFullYear(), t.getMonth(), 1));
                  setSelected(iso(t));
                }}
              >
                Today
              </button>
              <button type="button" onClick={() => setView(new Date(view.getFullYear(), view.getMonth() + 1, 1))} aria-label="Next month">
                <Chevron d="M9 5l7 7-7 7" />
              </button>
            </div>
          </div>

          {/* Sunday in red, the non-working day. */}
          <div className="bk-dows" aria-hidden="true">
            {DOWS.map((dow, index) => (
              <div key={dow} className={`bk-dow${index === 0 ? ' bk-dow-sun' : ''}`}>
                {dow}
              </div>
            ))}
          </div>

          <div className="bk-weeks">
            {weeks.map((week, wi) => (
              <div key={wi} className="bk-week">
                {week.map((cell, di) => {
                  const classes = [
                    'bk-day',
                    cell.iso === selected ? 'bk-day-on' : '',
                    cell.iso === data.today ? 'bk-day-today' : '',
                    cell.outside ? 'bk-day-out' : '',
                    di === 0 ? 'bk-day-sun' : '',
                  ]
                    .filter(Boolean)
                    .join(' ');
                  const count = cell.meta && !cell.outside ? cell.meta.count : 0;

                  return (
                    <div key={cell.iso} className="h-full">
                      <button
                        type="button"
                        onClick={() => select(cell.iso)}
                        className={classes}
                        aria-pressed={cell.iso === selected}
                        aria-current={cell.iso === data.today ? 'date' : undefined}
                        aria-label={
                          cell.label +
                          (count > 0
                            ? `, ${count} ${count === 1 ? 'booking' : 'bookings'}${cell.meta?.conflict ? ', with an overlap to resolve' : ''}`
                            : '')
                        }
                      >
                        <span className="bk-day-n">{cell.day}</span>
                        {count > 0 ? (
                          <span className={`bk-count${cell.meta?.conflict ? ' is-clash' : ''}`}>
                            {count} {count === 1 ? 'booking' : 'bookings'}
                          </span>
                        ) : null}
                      </button>
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </section>

        <div className="flex min-h-0 flex-col gap-5">
          <section className="bk-card min-h-[260px] flex-1" aria-label="Reservations on the selected day">
            <div className="bk-card-head">
              <h2 className="bk-title">{selected === data.today ? 'Booked today' : `Booked ${shortLabel}`}</h2>
              <span className="bk-head-count">{dayBookings.length}</span>
            </div>
            <div className="bk-card-body">
              {dayBookings.length === 0 ? (
                <Empty icon="calendar">
                  {selected === data.today ? 'There are no reservations today' : 'There are no reservations on this day'}
                </Empty>
              ) : (
                dayBookings.map((booking) => <DayItem key={booking.id} booking={booking} onNotice={setNotice} />)
              )}
            </div>
          </section>

          <section className="bk-card min-h-[260px] flex-1" aria-label="Upcoming reservations">
            <div className="bk-card-head">
              <h2 className="bk-title">Upcoming</h2>
              <span className="bk-head-count">{data.upcoming.length}</span>
            </div>
            <div className="bk-card-body">
              {data.upcoming.length === 0 ? (
                <Empty icon="clock">There are no upcoming reservations</Empty>
              ) : (
                data.upcoming.map((booking) => (
                  // Each row jumps the calendar to that day.
                  <button key={booking.id} type="button" onClick={() => select(booking.day)} className="bk-item">
                    <div className="flex items-start justify-between gap-3">
                      <p className="bk-item-title">{booking.resource}</p>
                      <span className="shrink-0 text-[length:var(--fs-small)] font-semibold text-ink-soft">{booking.upcomingLabel}</span>
                    </div>
                    <p className="bk-item-line">{booking.upcomingWindow}</p>
                    <div className="mt-1.5 flex items-center justify-between gap-2">
                      <p className="bk-item-meta !mt-0 truncate">Requested by {booking.requester}</p>
                      <StatusPill status={booking.status} />
                    </div>
                  </button>
                ))
              )}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

function DayItem({ booking, onNotice }: { booking: CalendarBooking; onNotice: (notice: Notice) => void }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [resched, setResched] = useState(false);
  const [quantity, setQuantity] = useState(String(Math.max(1, booking.quantity ?? 1)));
  const [starts, setStarts] = useState(booking.startsLocal);
  const [ends, setEnds] = useState(booking.endsLocal);

  const run = (action: () => Promise<{ ok: boolean; message: string }>): void => {
    startTransition(async () => {
      const result = await action();
      onNotice(result);

      if (result.ok) {
        setResched(false);
        router.refresh();
      }
    });
  };

  return (
    <div className={`bk-item${booking.conflict ? ' is-clash' : ''}`}>
      <p className="bk-item-title">{booking.resource}</p>
      <p className="bk-item-line">{booking.window}</p>
      <p className="bk-item-meta">
        Requested by {booking.requester}
        {booking.trackingNumber ? (
          <>
            {' · '}
            <a href={`/track/${encodeURIComponent(booking.trackingNumber)}`} className="mono text-green hover:underline">
              {booking.trackingNumber}
            </a>
          </>
        ) : null}
      </p>
      <div className="mt-2 flex flex-wrap items-center gap-1.5">
        <StatusPill status={booking.status} />
        {booking.quantity ? <span className="pill p-green">Qty: {booking.quantity.toLocaleString()}</span> : null}
        {booking.conflict ? <span className="pill p-red">Overlaps another booking</span> : null}
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        {booking.status !== 'approved' ? (
          <form
            className="flex items-center gap-1.5"
            onSubmit={(event) => {
              event.preventDefault();
              run(() => approveBooking(booking.id, booking.isEquipment ? Number(quantity) : null));
            }}
          >
            {/* Equipment: how many are being lent, defaulting to the citizen's ask. */}
            {booking.isEquipment ? (
              <>
                <label className="sr-only" htmlFor={`bk-qty-${booking.id}`}>
                  How many to lend
                </label>
                <input
                  id={`bk-qty-${booking.id}`}
                  type="number"
                  min={1}
                  step={1}
                  required
                  inputMode="numeric"
                  value={quantity}
                  onChange={(event) => setQuantity(event.target.value)}
                  onKeyDown={(event) => {
                    if (['.', ',', 'e', 'E', '-', '+'].includes(event.key)) {
                      event.preventDefault();
                    }
                  }}
                  className="st-filter-field inv-num !h-[var(--control-h-sm)]"
                />
              </>
            ) : null}
            <button type="submit" disabled={pending} className="cr-btn cr-btn-sm cr-btn-primary">
              Approve
            </button>
          </form>
        ) : null}
        <button type="button" onClick={() => setResched(!resched)} className="cr-btn cr-btn-sm">
          Reschedule
        </button>
        <button
          type="button"
          disabled={pending}
          className="cr-btn cr-btn-sm cr-btn-danger"
          onClick={() => {
            if (window.confirm('Cancel this booking? The slot will be freed.')) {
              run(() => cancelBooking(booking.id));
            }
          }}
        >
          Cancel
        </button>
      </div>

      {resched ? (
        <form
          className="mt-3 space-y-2 border-t border-hairline pt-3"
          onSubmit={(event) => {
            event.preventDefault();
            run(() => rescheduleBooking(booking.id, starts, ends));
          }}
        >
          <div>
            <label className="st-filter-label" htmlFor={`bk-start-${booking.id}`}>
              New start
            </label>
            <input
              id={`bk-start-${booking.id}`}
              type="datetime-local"
              required
              value={starts}
              onChange={(event) => setStarts(event.target.value)}
              className="st-filter-field"
            />
          </div>
          <div>
            <label className="st-filter-label" htmlFor={`bk-end-${booking.id}`}>
              New end
            </label>
            <input
              id={`bk-end-${booking.id}`}
              type="datetime-local"
              required
              value={ends}
              onChange={(event) => setEnds(event.target.value)}
              className="st-filter-field"
            />
          </div>
          <button type="submit" disabled={pending} className="cr-btn cr-btn-sm cr-btn-primary">
            Save
          </button>
        </form>
      ) : null}
    </div>
  );
}

function StatusPill({ status }: { status: string }) {
  return (
    <span className={`pill shrink-0 ${status === 'approved' ? 'p-green' : 'p-amber'}`}>
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </span>
  );
}

function Empty({ icon, children }: { icon: 'calendar' | 'clock'; children: React.ReactNode }) {
  return (
    <div className="bk-empty">
      <span className="bk-empty-icon">
        <svg fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true">
          {icon === 'calendar' ? (
            <>
              <rect x="4" y="5" width="16" height="15" rx="2.5" />
              <path strokeLinecap="round" d="M4 10h16M9 3v4M15 3v4" />
            </>
          ) : (
            <>
              <circle cx="12" cy="12" r="8.5" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5V12l3 2" />
            </>
          )}
        </svg>
      </span>
      <p>{children}</p>
    </div>
  );
}

function Chevron({ d }: { d: string }) {
  return (
    <svg className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2.4" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d={d} />
    </svg>
  );
}
