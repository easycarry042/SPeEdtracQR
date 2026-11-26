'use client';

import { useMemo, useState } from 'react';

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
] as const;

const SHORT_MONTHS = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
] as const;

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

function toIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
    d.getDate(),
  ).padStart(2, '0')}`;
}

/** Formats `YYYY-MM-DD` as `"Nov 28, 2026"` for summaries and inputs. */
export function formatCalendarIso(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  if (!y || !m || !d) return iso;
  return `${SHORT_MONTHS[m - 1]} ${d}, ${y}`;
}

export type CalendarLegendItem = {
  id: string;
  label: string;
  value?: string;
  variant: 'requested' | 'set' | 'booked';
};

export type CalendarDateHighlight = {
  iso: string;
  variant: 'requested' | 'set';
  ariaNote?: string;
};

export interface UnifiedCalendarProps {
  value: string;
  onChange: (iso: string) => void;
  disabledDates?: ReadonlyArray<string> | ReadonlySet<string> | ((iso: string) => boolean);
  minDate?: string;
  legendItems?: CalendarLegendItem[];
  highlights?: CalendarDateHighlight[];
  onApply?: () => void;
  onClear?: () => void;
  applyDisabled?: boolean;
  applyLabel?: string;
  footerSummary?: React.ReactNode;
  error?: string | null;
  loading?: boolean;
  onMonthChange?: (year: number, monthIndex: number) => void;
  ariaLabel?: string;
  todayIso?: string;
}

/**
 * Single shared calendar component used identically by Staff "Adjust Claiming
 * Date" and Citizen facility booking workflows.
 */
export function UnifiedCalendar({
  value,
  onChange,
  disabledDates,
  minDate,
  legendItems,
  highlights,
  onApply,
  onClear,
  applyDisabled = false,
  applyLabel = 'Apply',
  footerSummary,
  error,
  loading = false,
  onMonthChange,
  ariaLabel = 'Calendar',
  todayIso: todayIsoProp,
}: UnifiedCalendarProps) {
  const browserTodayIso = useMemo(() => toIso(new Date()), []);
  const todayIso = todayIsoProp || browserTodayIso;
  const effectiveMinDate = minDate ?? todayIso;

  const [cursor, setCursor] = useState<{ year: number; month: number }>(() => {
    const seed = value || todayIso;
    const [y, m] = seed.split('-').map(Number);
    if (y && m) {
      return { year: y, month: m - 1 };
    }
    const now = new Date();
    return { year: now.getFullYear(), month: now.getMonth() };
  });

  const isBookedDisabled = useMemo(() => {
    if (!disabledDates) {
      return () => false;
    }
    if (typeof disabledDates === 'function') {
      return disabledDates;
    }
    const set = disabledDates instanceof Set ? disabledDates : new Set(disabledDates);
    return (iso: string) => set.has(iso);
  }, [disabledDates]);

  const highlightByIso = useMemo(() => {
    const map = new Map<string, CalendarDateHighlight[]>();
    for (const item of highlights ?? []) {
      const list = map.get(item.iso);
      if (list) {
        list.push(item);
      } else {
        map.set(item.iso, [item]);
      }
    }
    return map;
  }, [highlights]);

  const shiftMonth = (delta: number) => {
    const next = new Date(cursor.year, cursor.month + delta, 1);
    const nextCursor = { year: next.getFullYear(), month: next.getMonth() };
    setCursor(nextCursor);
    onMonthChange?.(nextCursor.year, nextCursor.month);
  };

  const calendarToToday = () => {
    const [y, m] = todayIso.split('-').map(Number);
    const nextCursor =
      y && m
        ? { year: y, month: m - 1 }
        : { year: new Date().getFullYear(), month: new Date().getMonth() };
    setCursor(nextCursor);
    onMonthChange?.(nextCursor.year, nextCursor.month);

    if (todayIso >= effectiveMinDate && !isBookedDisabled(todayIso)) {
      onChange(todayIso);
    }
  };

  const weeks = useMemo(() => {
    const first = new Date(cursor.year, cursor.month, 1);
    const startOffset = first.getDay();
    const gridStart = new Date(cursor.year, cursor.month, 1 - startOffset);

    const out: Array<
      Array<{
        iso: string;
        day: number;
        inMonth: boolean;
        today: boolean;
        past: boolean;
        booked: boolean;
        sunday: boolean;
        label: string;
      }>
    > = [];

    for (let w = 0; w < 6; w++) {
      const week = [];
      for (let d = 0; d < 7; d++) {
        const cellDate = new Date(
          gridStart.getFullYear(),
          gridStart.getMonth(),
          gridStart.getDate() + w * 7 + d,
        );
        const iso = toIso(cellDate);
        week.push({
          iso,
          day: cellDate.getDate(),
          inMonth: cellDate.getMonth() === cursor.month,
          today: iso === todayIso,
          past: iso < effectiveMinDate,
          booked: isBookedDisabled(iso),
          sunday: cellDate.getDay() === 0,
          label: `${MONTHS[cellDate.getMonth()]} ${cellDate.getDate()}, ${cellDate.getFullYear()}`,
        });
      }
      out.push(week);
    }
    return out;
  }, [cursor, todayIso, effectiveMinDate, isBookedDisabled]);

  const showClear = Boolean(onClear || value);

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      {legendItems && legendItems.length > 0 ? (
        <div className="dp-context">
          {legendItems.map((item) => (
            <div key={item.id} className="dp-context-item text-sm">
              <span
                className={`dp-key ${
                  item.variant === 'requested'
                    ? 'dp-key-requested'
                    : item.variant === 'set'
                      ? 'dp-key-set'
                      : 'dp-key-booked'
                }`}
                aria-hidden="true"
              />
              <span>{item.label}</span>
              {item.value !== undefined ? <strong>{item.value}</strong> : null}
            </div>
          ))}
        </div>
      ) : null}

      <div className="dp-head">
        <div className="flex min-w-0 items-center gap-2.5">
          <p className="dp-month text-xl font-semibold" aria-live="polite">
            {MONTHS[cursor.month]} {cursor.year}
          </p>
          {loading ? (
            <span className="text-sm font-medium text-ink-soft" role="status">
              Updating…
            </span>
          ) : null}
        </div>
        <div className="bk-nav" role="group" aria-label="Change month">
          <button
            type="button"
            onClick={() => shiftMonth(-1)}
            aria-label="Previous month"
            className="min-h-[40px] text-base"
          >
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 18l-6-6 6-6" />
            </svg>
          </button>
          <button
            type="button"
            onClick={calendarToToday}
            className="min-h-[40px] text-base"
          >
            Today
          </button>
          <button
            type="button"
            onClick={() => shiftMonth(1)}
            aria-label="Next month"
            className="min-h-[40px] text-base"
          >
            <svg
              fill="none"
              stroke="currentColor"
              strokeWidth="2.2"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 6l6 6-6 6" />
            </svg>
          </button>
        </div>
      </div>

      <div className="dp-dows" aria-hidden="true">
        {WEEKDAYS.map((name, i) => (
          <span key={name} className={`text-sm font-bold ${i === 0 ? 'is-sunday' : ''}`}>
            {name}
          </span>
        ))}
      </div>

      <div className="dp-grid" role="grid" aria-label={ariaLabel}>
        {weeks.map((week, w) => (
          <div key={`w-${w}`} className="dp-week" role="row">
            {week.map((cell) => {
              const cellHighlights = highlightByIso.get(cell.iso) ?? [];
              const isRequested = cellHighlights.some((h) => h.variant === 'requested');
              const isSet = cellHighlights.some((h) => h.variant === 'set');
              const disabled = cell.past || cell.booked;
              const notes = cellHighlights
                .map((h) => h.ariaNote)
                .filter(Boolean)
                .join(', ');

              const classes = [
                'dp-day',
                'min-h-[44px] min-w-[44px] text-base',
                value === cell.iso ? 'is-picked' : '',
                cell.today ? 'is-today' : '',
                !cell.inMonth ? 'is-outside' : '',
                cell.sunday ? 'is-sunday' : '',
                isRequested ? 'is-requested' : '',
                isSet ? 'is-set' : '',
                cell.booked ? 'opacity-40 cursor-not-allowed line-through bg-gray-100' : '',
              ]
                .filter(Boolean)
                .join(' ');

              return (
                <div key={cell.iso} className="dp-cell" role="gridcell">
                  <button
                    type="button"
                    disabled={disabled}
                    aria-disabled={disabled || undefined}
                    onClick={() => onChange(cell.iso)}
                    title={cell.booked ? 'Unavailable / Booked' : undefined}
                    className={classes}
                    aria-pressed={value === cell.iso}
                    aria-current={cell.today ? 'date' : undefined}
                    aria-label={`${cell.label}${notes ? `, ${notes}` : ''}${
                      cell.booked ? ', unavailable, already booked' : cell.past ? ', unavailable' : ''
                    }`}
                  >
                    <span>{cell.day}</span>
                  </button>
                </div>
              );
            })}
          </div>
        ))}
      </div>

      {error ? (
        <p className="px-6 pt-2 text-sm font-semibold text-status-red" role="alert">
          {error}
        </p>
      ) : null}

      <div className="dp-foot">
        <div className="dp-picked text-sm">
          {footerSummary ?? (
            value ? (
              <span>
                Selected date <strong>{formatCalendarIso(value)}</strong>
              </span>
            ) : (
              <span>Pick a date.</span>
            )
          )}
        </div>

        <div className="flex shrink-0 items-center gap-2">
          {showClear ? (
            <button
              type="button"
              disabled={!value}
              onClick={() => (onClear ? onClear() : onChange(''))}
              className="cr-btn min-h-[40px] text-base disabled:opacity-40"
            >
              Clear
            </button>
          ) : null}
          {onApply ? (
            <button
              type="button"
              disabled={applyDisabled}
              onClick={onApply}
              className="cr-btn cr-btn-primary min-h-[40px] text-base disabled:opacity-40"
            >
              {applyLabel}
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
}
