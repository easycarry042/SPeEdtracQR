'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  UnifiedCalendar,
  formatCalendarIso,
  type CalendarDateHighlight,
  type CalendarLegendItem,
} from '@/components/unified-calendar';

function localTodayIso(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(
    now.getDate(),
  ).padStart(2, '0')}`;
}

/**
 * Shared accessible button + portaled `<UnifiedCalendar />` modal for every
 * citizen and staff date input in the application.
 */
export function CalendarPickerField({
  id,
  name,
  value,
  onChange,
  todayIso: todayIsoProp,
  minDate,
  modalTitle = 'Select Date',
  placeholder = 'Select a date…',
  triggerClassName = 'req-field cz-date-trigger mt-2 text-base',
  ariaInvalid = false,
  disabled = false,
  allowClear = true,
  facilityResourceId = null,
  disabledDates: externalDisabledDates,
  legendItems: externalLegendItems,
  highlights,
}: {
  id: string;
  name?: string;
  value: string;
  onChange: (iso: string) => void;
  todayIso?: string;
  minDate?: string;
  modalTitle?: string;
  placeholder?: string;
  triggerClassName?: string;
  ariaInvalid?: boolean;
  disabled?: boolean;
  allowClear?: boolean;
  facilityResourceId?: number | null;
  disabledDates?: ReadonlySet<string> | ReadonlyArray<string>;
  legendItems?: CalendarLegendItem[];
  highlights?: CalendarDateHighlight[];
}) {
  const todayIso = useMemo(() => todayIsoProp || localTodayIso(), [todayIsoProp]);
  const effectiveMinDate = minDate ?? todayIso;

  const [mounted, setMounted] = useState(false);
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState(value);
  const [bookedByMonth, setBookedByMonth] = useState<Record<string, string[]>>({});
  const [loadingAvailability, setLoadingAvailability] = useState(false);
  const [availabilityError, setAvailabilityError] = useState<string | null>(null);
  const fetchedKeys = useRef<Set<string>>(new Set());
  const inFlight = useRef(0);

  useEffect(() => {
    setMounted(true);
  }, []);

  const fetchAvailability = useCallback(
    async (resourceId: number, monthIso: string, signal?: AbortSignal): Promise<void> => {
      const cacheKey = `${resourceId}:${monthIso}`;
      if (fetchedKeys.current.has(cacheKey)) {
        return;
      }

      inFlight.current += 1;
      setLoadingAvailability(true);
      setAvailabilityError(null);

      try {
        const res = await fetch(
          `/api/bookings/availability?resourceId=${encodeURIComponent(String(resourceId))}&month=${encodeURIComponent(monthIso)}`,
          { credentials: 'same-origin', signal },
        );

        if (!res.ok) {
          throw new Error('Could not check booked dates right now.');
        }

        const payload = (await res.json()) as { bookedDates?: string[] };
        const dates = Array.isArray(payload.bookedDates) ? payload.bookedDates : [];
        fetchedKeys.current.add(cacheKey);
        setBookedByMonth((prev) => ({ ...prev, [cacheKey]: dates }));
      } catch (err) {
        if (err instanceof DOMException && err.name === 'AbortError') {
          return;
        }
        setAvailabilityError(
          'Unable to load booked dates. Server verification still checks availability on save.',
        );
      } finally {
        inFlight.current = Math.max(0, inFlight.current - 1);
        if (inFlight.current === 0) {
          setLoadingAvailability(false);
        }
      }
    },
    [],
  );

  useEffect(() => {
    if (!facilityResourceId) {
      return;
    }

    const controller = new AbortController();
    const initialMonth = (value || todayIso).slice(0, 7);
    void fetchAvailability(facilityResourceId, initialMonth, controller.signal);

    if (todayIso.slice(0, 7) !== initialMonth) {
      void fetchAvailability(facilityResourceId, todayIso.slice(0, 7), controller.signal);
    }

    return () => controller.abort();
  }, [facilityResourceId, value, todayIso, fetchAvailability]);

  const disabledSet = useMemo(() => {
    const set = new Set<string>(externalDisabledDates ?? []);
    if (facilityResourceId) {
      const prefix = `${facilityResourceId}:`;
      for (const [key, dates] of Object.entries(bookedByMonth)) {
        if (key.startsWith(prefix)) {
          for (const day of dates) {
            set.add(day);
          }
        }
      }
    }
    return set;
  }, [facilityResourceId, bookedByMonth, externalDisabledDates]);

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.stopPropagation();
        setOpen(false);
      }
    };
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey, true);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', onKey, true);
    };
  }, [open]);

  const legendItems = useMemo<CalendarLegendItem[]>(() => {
    const items: CalendarLegendItem[] = [...(externalLegendItems ?? [])];
    if (facilityResourceId && !items.some((item) => item.variant === 'booked')) {
      items.push({ id: 'booked', label: 'Unavailable / Booked', variant: 'booked' });
    }
    return items;
  }, [externalLegendItems, facilityResourceId]);

  const openModal = () => {
    if (disabled) return;
    setDraft(value && !disabledSet.has(value) ? value : '');
    setOpen(true);
    if (facilityResourceId) {
      const monthIso = (value || todayIso).slice(0, 7);
      void fetchAvailability(facilityResourceId, monthIso);
    }
  };

  const canApply =
    draft !== ''
      ? !disabledSet.has(draft) && draft >= effectiveMinDate
      : allowClear && value !== '';

  return (
    <>
      {name ? <input type="hidden" name={name} value={value} /> : null}
      <button
        id={id}
        type="button"
        disabled={disabled}
        onClick={openModal}
        aria-invalid={ariaInvalid || undefined}
        aria-haspopup="dialog"
        aria-expanded={open}
        className={`${triggerClassName}${open ? ' is-open' : ''}`}
      >
        <span className={value ? 'font-semibold text-ink' : 'is-empty'}>
          {value ? formatCalendarIso(value) : placeholder}
        </span>
        <svg
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <rect x="3" y="5" width="18" height="16" rx="2.5" />
          <path strokeLinecap="round" d="M3 10h18M8 3v4M16 3v4" />
        </svg>
      </button>

      {mounted && open
        ? createPortal(
            <div
              className="fixed inset-0 z-[90] flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
              role="dialog"
              aria-modal="true"
              aria-labelledby={`${id}-calendar-title`}
              onClick={(event) => {
                if (event.target === event.currentTarget) {
                  setOpen(false);
                }
              }}
            >
              <div className="flex max-h-[calc(92vh/var(--ui-zoom,1))] w-full max-w-[600px] flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_19px_48px_-16px_rgba(0,64,4,0.45)]">
                <div className="dm-head">
                  <h2
                    id={`${id}-calendar-title`}
                    className="min-w-0 truncate text-xl font-semibold text-green-deep"
                  >
                    {modalTitle}
                  </h2>
                  <button
                    type="button"
                    onClick={() => setOpen(false)}
                    aria-label="Close calendar"
                    className="dm-close min-h-[40px] min-w-[40px] shrink-0"
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

                <UnifiedCalendar
                  value={draft}
                  onChange={setDraft}
                  minDate={effectiveMinDate}
                  todayIso={todayIso}
                  disabledDates={disabledSet.size > 0 ? disabledSet : undefined}
                  legendItems={legendItems.length > 0 ? legendItems : undefined}
                  highlights={highlights}
                  loading={loadingAvailability}
                  error={availabilityError}
                  ariaLabel={modalTitle}
                  onMonthChange={
                    facilityResourceId
                      ? (year, monthIndex) => {
                          const monthIso = `${year}-${String(monthIndex + 1).padStart(2, '0')}`;
                          void fetchAvailability(facilityResourceId, monthIso);
                        }
                      : undefined
                  }
                  onClear={
                    allowClear
                      ? () => {
                          setDraft('');
                          onChange('');
                        }
                      : undefined
                  }
                  onApply={() => {
                    if (draft && !disabledSet.has(draft)) {
                      onChange(draft);
                      setOpen(false);
                    } else if (draft === '' && allowClear) {
                      onChange('');
                      setOpen(false);
                    }
                  }}
                  applyDisabled={!canApply}
                  applyLabel="Apply"
                  footerSummary={
                    draft ? (
                      <span>
                        Selected date <strong>{formatCalendarIso(draft)}</strong>
                      </span>
                    ) : (
                      <span>Pick a date.</span>
                    )
                  }
                />
              </div>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}
