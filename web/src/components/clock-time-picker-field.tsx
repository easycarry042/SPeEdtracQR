'use client';

import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
  degreesToHour12,
  degreesToMinute,
  formatTime12,
  parseTime24,
  pointerToClockDegrees,
  timeToMinutes,
  toTime24,
  type Meridiem,
} from '@/lib/client/clock-time';

type DialMode = 'hour' | 'minute';

const HOURS_12 = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] as const;
const MINUTES_5 = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55] as const;

const DIAL_SIZE = 272;
const DIAL_CENTER = DIAL_SIZE / 2;
const NUMBER_RADIUS = 104;
const HAND_LENGTH = 84;

function dialPosition(index: number): { left: number; top: number } {
  const angleRad = ((index * 30 - 90) * Math.PI) / 180;
  return {
    left: DIAL_CENTER + NUMBER_RADIUS * Math.cos(angleRad),
    top: DIAL_CENTER + NUMBER_RADIUS * Math.sin(angleRad),
  };
}

/**
 * Interactive analog clock time picker matching SPeED TraQR's modal & calendar
 * design system. Emits canonical 24-hour `HH:MM` via hidden input for server
 * compatibility while presenting a friendly 12-hour clock dial to citizens.
 */
export function ClockTimePickerField({
  id,
  name,
  value,
  onChange,
  modalTitle = 'Select Time',
  placeholder = 'Select time…',
  triggerClassName = 'req-field cz-date-trigger mt-2 text-base',
  ariaInvalid = false,
  disabled = false,
  minTime,
  minTimeLabel,
}: {
  id: string;
  name?: string;
  value: string;
  onChange: (time24: string) => void;
  modalTitle?: string;
  placeholder?: string;
  triggerClassName?: string;
  ariaInvalid?: boolean;
  disabled?: boolean;
  /** Optional exclusive lower bound (`HH:MM`) — e.g. End time must be strictly after Start time. */
  minTime?: string;
  minTimeLabel?: string;
}) {
  const [mounted, setMounted] = useState(false);
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState<DialMode>('hour');

  const initialParsed = parseTime24(value) ?? { hour12: 9, minute: 0, meridiem: 'AM' as Meridiem };
  const [hour12, setHour12] = useState<number>(initialParsed.hour12);
  const [minute, setMinute] = useState<number>(initialParsed.minute);
  const [meridiem, setMeridiem] = useState<Meridiem>(initialParsed.meridiem);

  const dialRef = useRef<HTMLDivElement | null>(null);
  const draggingRef = useRef(false);

  useEffect(() => {
    setMounted(true);
  }, []);

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

  const openModal = () => {
    if (disabled) return;
    const parsed = parseTime24(value) ?? { hour12: 9, minute: 0, meridiem: 'AM' as Meridiem };
    setHour12(parsed.hour12);
    setMinute(parsed.minute);
    setMeridiem(parsed.meridiem);
    setMode('hour');
    setOpen(true);
  };

  const draftTime24 = useMemo(
    () => toTime24(hour12, minute, meridiem),
    [hour12, minute, meridiem],
  );
  const draftDisplay = useMemo(() => formatTime12(draftTime24), [draftTime24]);
  const triggerDisplay = useMemo(() => formatTime12(value), [value]);

  const validationError = useMemo(() => {
    if (!minTime) return null;
    const minTotal = timeToMinutes(minTime);
    const draftTotal = timeToMinutes(draftTime24);
    if (minTotal !== null && draftTotal !== null && draftTotal <= minTotal) {
      const boundText = minTimeLabel ? `${minTimeLabel} (${formatTime12(minTime)})` : formatTime12(minTime);
      return `Please choose a time after ${boundText}.`;
    }
    return null;
  }, [minTime, minTimeLabel, draftTime24]);

  const handDegrees = mode === 'hour' ? (hour12 % 12) * 30 : minute * 6;
  const isFiveMinuteMark = minute % 5 === 0;

  const updateFromPointer = (clientX: number, clientY: number) => {
    const el = dialRef.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const deg = pointerToClockDegrees(clientX - cx, clientY - cy);

    if (mode === 'hour') {
      setHour12(degreesToHour12(deg));
    } else {
      setMinute(degreesToMinute(deg));
    }
  };

  const handleDialPointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
    // Allow direct button clicks to run their own onClick cleanly unless dragging
    if ((event.target as HTMLElement).closest('button')) {
      return;
    }
    draggingRef.current = true;
    event.currentTarget.setPointerCapture(event.pointerId);
    updateFromPointer(event.clientX, event.clientY);
  };

  const handleDialPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!draggingRef.current) return;
    updateFromPointer(event.clientX, event.clientY);
  };

  const handleDialPointerUp = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!draggingRef.current) return;
    draggingRef.current = false;
    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }
  };

  const handleApply = () => {
    if (validationError) return;
    onChange(draftTime24);
    setOpen(false);
  };

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
        <span className={triggerDisplay ? 'font-semibold text-ink' : 'is-empty'}>
          {triggerDisplay || placeholder}
        </span>
        <svg
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <circle cx="12" cy="12" r="9" />
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 7v5l3.5 2" />
        </svg>
      </button>

      {mounted && open
        ? createPortal(
            <div
              className="fixed inset-0 z-[90] flex items-center justify-center bg-black/45 p-4 backdrop-blur-sm"
              role="dialog"
              aria-modal="true"
              aria-labelledby={`${id}-clock-title`}
              onClick={(event) => {
                if (event.target === event.currentTarget) {
                  setOpen(false);
                }
              }}
            >
              <div className="flex max-h-[calc(92vh/var(--ui-zoom,1))] w-full max-w-[440px] flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_19px_48px_-16px_rgba(0,64,4,0.45)]">
                {/* Header */}
                <div className="dm-head relative z-30">
                  <h2
                    id={`${id}-clock-title`}
                    className="min-w-0 truncate text-xl font-semibold text-green-deep"
                  >
                    {modalTitle}
                  </h2>
                  <button
                    type="button"
                    onClick={() => setOpen(false)}
                    aria-label="Close clock picker"
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

                {/* Digital time readout & AM/PM toggle */}
                <div className="border-b border-hairline bg-[#fafcfb] px-6 py-4">
                  <div className="flex items-center justify-center gap-3">
                    <div className="flex items-center gap-1" role="group" aria-label="Time selection mode">
                      <button
                        type="button"
                        onClick={() => setMode('hour')}
                        aria-pressed={mode === 'hour'}
                        aria-label="Select hour"
                        className={`min-h-[54px] min-w-[66px] rounded-[12px] border px-3 text-3xl font-bold tabular-nums transition ${
                          mode === 'hour'
                            ? 'border-green bg-green-wash text-green-deep shadow-sm'
                            : 'border-hairline bg-white text-ink hover:bg-green-wash/50'
                        }`}
                      >
                        {String(hour12).padStart(2, '0')}
                      </button>
                      <span className="px-0.5 text-3xl font-bold text-ink-soft" aria-hidden="true">
                        :
                      </span>
                      <button
                        type="button"
                        onClick={() => setMode('minute')}
                        aria-pressed={mode === 'minute'}
                        aria-label="Select minute"
                        className={`min-h-[54px] min-w-[66px] rounded-[12px] border px-3 text-3xl font-bold tabular-nums transition ${
                          mode === 'minute'
                            ? 'border-green bg-green-wash text-green-deep shadow-sm'
                            : 'border-hairline bg-white text-ink hover:bg-green-wash/50'
                        }`}
                      >
                        {String(minute).padStart(2, '0')}
                      </button>
                    </div>

                    <div
                      className="flex overflow-hidden rounded-[12px] border border-hairline-strong bg-white"
                      role="group"
                      aria-label="AM or PM"
                    >
                      {(['AM', 'PM'] as const).map((period) => (
                        <button
                          key={period}
                          type="button"
                          onClick={() => setMeridiem(period)}
                          aria-pressed={meridiem === period}
                          className={`min-h-[54px] min-w-[52px] px-3.5 text-base font-bold transition ${
                            meridiem === period
                              ? 'bg-green-deep text-white'
                              : 'bg-white text-ink-soft hover:bg-green-wash/60'
                          }`}
                        >
                          {period}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="mt-3 flex flex-col items-center gap-1.5">
                    <div
                      className="inline-flex rounded-lg border border-hairline bg-[#eef3ef] p-1"
                      role="tablist"
                      aria-label="Clock dial unit"
                    >
                      <button
                        type="button"
                        role="tab"
                        aria-selected={mode === 'hour'}
                        onClick={() => setMode('hour')}
                        className={`min-h-[40px] rounded-md px-4 text-sm font-semibold transition ${
                          mode === 'hour'
                            ? 'bg-white text-green-deep shadow-sm'
                            : 'text-ink-soft hover:text-ink'
                        }`}
                      >
                        Hour
                      </button>
                      <button
                        type="button"
                        role="tab"
                        aria-selected={mode === 'minute'}
                        onClick={() => setMode('minute')}
                        className={`min-h-[40px] rounded-md px-4 text-sm font-semibold transition ${
                          mode === 'minute'
                            ? 'bg-white text-green-deep shadow-sm'
                            : 'text-ink-soft hover:text-ink'
                        }`}
                      >
                        Minute
                      </button>
                    </div>
                    <p className="text-center text-sm font-medium text-ink-soft">
                      {mode === 'hour'
                        ? 'Pick or drag the hour hand. Switch to Minute when needed.'
                        : 'Pick or drag the minute hand.'}
                    </p>
                  </div>
                </div>

                {/* Analog Clock Dial */}
                <div className="flex flex-col items-center justify-center px-6 py-5">
                  <div
                    ref={dialRef}
                    role="application"
                    aria-label={mode === 'hour' ? 'Hour clock dial' : 'Minute clock dial'}
                    onPointerDown={handleDialPointerDown}
                    onPointerMove={handleDialPointerMove}
                    onPointerUp={handleDialPointerUp}
                    className="relative select-none touch-none rounded-full border-2 border-hairline-strong bg-[#f4f7f5] shadow-inner"
                    style={{ width: DIAL_SIZE, height: DIAL_SIZE }}
                  >
                    {/* Subtle tick marks around inner rim */}
                    {HOURS_12.map((h, i) => (
                      <span
                        key={`tick-${h}`}
                        aria-hidden="true"
                        className="pointer-events-none absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-hairline-strong/70"
                        style={{
                          transform: `translate(-50%, -50%) rotate(${i * 30}deg) translateY(-126px)`,
                        }}
                      />
                    ))}

                    {/* Rotating clock hand */}
                    <div
                      aria-hidden="true"
                      className="pointer-events-none absolute left-1/2 top-1/2 origin-bottom rounded-full bg-green transition-transform duration-150"
                      style={{
                        width: 3,
                        height: HAND_LENGTH,
                        transform: `translate(-50%, -100%) rotate(${handDegrees}deg)`,
                      }}
                    >
                      {/* Hand tip indicator (especially visible for non-5-minute selections) */}
                      <span
                        className={`absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-green-deep bg-green-deep transition-all ${
                          mode === 'minute' && !isFiveMinuteMark
                            ? 'h-4 w-4 ring-4 ring-green/25'
                            : 'h-3 w-3'
                        }`}
                      />
                    </div>

                    {/* Center pivot pin */}
                    <span
                      aria-hidden="true"
                      className="pointer-events-none absolute left-1/2 top-1/2 z-10 h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-green-deep shadow-sm"
                    />

                    {/* Dial numbers */}
                    {mode === 'hour'
                      ? HOURS_12.map((h, idx) => {
                          const pos = dialPosition(idx);
                          const selected = hour12 === h;
                          return (
                            <button
                              key={`h-${h}`}
                              type="button"
                              aria-label={`${h} o'clock`}
                              aria-pressed={selected}
                              onClick={() => setHour12(h)}
                              className={`absolute z-20 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-base font-semibold transition ${
                                selected
                                  ? 'bg-green-deep text-white shadow-md'
                                  : 'text-ink hover:bg-green-wash hover:text-green-deep'
                              }`}
                              style={{ left: pos.left, top: pos.top }}
                            >
                              {h}
                            </button>
                          );
                        })
                      : MINUTES_5.map((m, idx) => {
                          const pos = dialPosition(idx);
                          const selected = minute === m;
                          return (
                            <button
                              key={`m-${m}`}
                              type="button"
                              aria-label={`${String(m).padStart(2, '0')} minutes`}
                              aria-pressed={selected}
                              onClick={() => setMinute(m)}
                              className={`absolute z-20 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-base font-semibold tabular-nums transition ${
                                selected
                                  ? 'bg-green-deep text-white shadow-md'
                                  : 'text-ink hover:bg-green-wash hover:text-green-deep'
                              }`}
                              style={{ left: pos.left, top: pos.top }}
                            >
                              {String(m).padStart(2, '0')}
                            </button>
                          );
                        })}
                  </div>

                  {validationError ? (
                    <p
                      className="mt-4 text-center text-sm font-semibold text-status-red"
                      role="alert"
                    >
                      {validationError}
                    </p>
                  ) : null}
                </div>

                {/* Footer matching UnifiedCalendar (.dp-foot) */}
                <div className="dp-foot">
                  <div className="dp-picked text-sm">
                    <span>
                      Selected time <strong>{draftDisplay}</strong>
                    </span>
                  </div>

                  <div className="flex shrink-0 items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setOpen(false)}
                      className="cr-btn min-h-[40px] text-base"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      disabled={Boolean(validationError)}
                      onClick={handleApply}
                      className="cr-btn cr-btn-primary min-h-[40px] text-base disabled:opacity-40"
                    >
                      Apply
                    </button>
                  </div>
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}
