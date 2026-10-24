'use client';

import { useMemo, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { setClaimDate } from '@/app/actions/document';
import { postMessage } from '@/app/actions/messages';

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
];

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
];

function toIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
    d.getDate(),
  ).padStart(2, '0')}`;
}

function formatPickedIso(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  if (!y || !m || !d) return iso;
  return `${SHORT_MONTHS[m - 1]} ${d}, ${y}`;
}

/**
 * The modal's right-hand card (`partials/review-modal.blade.php` lines 721–928):
 * the claiming-date calendar, or the conversation with the citizen.
 */
export function ReviewSidePanel({
  row,
  side,
  onClose,
}: {
  row: ReviewRow;
  side: 'date' | 'messages';
  onClose: () => void;
}) {
  return (
    <div
      className="dm-fixed flex w-full max-w-[660px] flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_19px_48px_-16px_rgba(0,64,4,0.45)] lg:min-w-0 lg:flex-1"
      aria-label={side === 'date' ? 'Adjust claiming date' : 'Messages'}
      role="region"
    >
      <div className="flex h-full min-h-0 flex-col">
        <div className="dm-head">
          <h2 className="dm-section min-w-0 truncate">
            {side === 'date' ? (
              'Adjust Claiming Date'
            ) : (
              <>
                Messages ·{' '}
                <span className="font-semibold text-ink">{row.citizenName || 'Citizen'}</span>
              </>
            )}
          </h2>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close panel"
            className="dm-close shrink-0"
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

        {side === 'date' ? <ClaimCalendar row={row} /> : <Conversation row={row} />}
      </div>
    </div>
  );
}

function ClaimCalendar({ row }: { row: ReviewRow }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const initialIso = row.claimOn ?? row.requestedClaimOn ?? '';
  const [dateValue, setDateValue] = useState(initialIso);
  const [error, setError] = useState('');

  const today = useMemo(() => new Date(), []);
  const todayIso = useMemo(() => toIso(today), [today]);

  const initialCursor = useMemo(() => {
    if (initialIso) {
      const [y, m] = initialIso.split('-').map(Number);
      if (y && m) return { year: y, month: m - 1 };
    }
    return { year: today.getFullYear(), month: today.getMonth() };
  }, [initialIso, today]);

  const [cursor, setCursor] = useState(initialCursor);

  const shiftMonth = (delta: number) => {
    setCursor((prev) => {
      const next = new Date(prev.year, prev.month + delta, 1);
      return { year: next.getFullYear(), month: next.getMonth() };
    });
  };

  const calendarToToday = () => {
    setCursor({ year: today.getFullYear(), month: today.getMonth() });
    setDateValue(todayIso);
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
          past: iso < todayIso,
          sunday: cellDate.getDay() === 0,
          label: `${MONTHS[cellDate.getMonth()]} ${cellDate.getDate()}, ${cellDate.getFullYear()}`,
        });
      }
      out.push(week);
    }
    return out;
  }, [cursor, todayIso]);

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="dp-context">
        <div className="dp-context-item">
          <span className="dp-key dp-key-requested" aria-hidden="true" />
          <span>Citizen asked for</span>
          <strong>{row.requestedClaimDate || 'no date'}</strong>
        </div>
        {row.claimDate ? (
          <div className="dp-context-item">
            <span className="dp-key dp-key-set" aria-hidden="true" />
            <span>Currently set</span>
            <strong>{row.claimDate}</strong>
          </div>
        ) : null}
      </div>

      <div className="dp-head">
        <p className="dp-month" aria-live="polite">
          {MONTHS[cursor.month]} {cursor.year}
        </p>
        <div className="bk-nav" role="group" aria-label="Change month">
          <button type="button" onClick={() => shiftMonth(-1)} aria-label="Previous month">
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
          <button type="button" onClick={calendarToToday}>
            Today
          </button>
          <button type="button" onClick={() => shiftMonth(1)} aria-label="Next month">
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
        {(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const).map((name, i) => (
          <span key={name} className={i === 0 ? 'is-sunday' : undefined}>
            {name}
          </span>
        ))}
      </div>

      <div className="dp-grid" role="grid" aria-label="Claiming date">
        {weeks.map((week, w) => (
          <div key={`w-${w}`} className="dp-week" role="row">
            {week.map((cell) => {
              const classes = [
                'dp-day',
                dateValue === cell.iso ? 'is-picked' : '',
                cell.today ? 'is-today' : '',
                !cell.inMonth ? 'is-outside' : '',
                cell.sunday ? 'is-sunday' : '',
                cell.iso === row.requestedClaimOn ? 'is-requested' : '',
                cell.iso === row.claimOn ? 'is-set' : '',
              ]
                .filter(Boolean)
                .join(' ');

              return (
                <div key={cell.iso} className="dp-cell" role="gridcell">
                  <button
                    type="button"
                    disabled={cell.past}
                    onClick={() => setDateValue(cell.iso)}
                    className={classes}
                    aria-pressed={dateValue === cell.iso}
                    aria-current={cell.today ? 'date' : undefined}
                    aria-label={`${cell.label}${
                      cell.iso === row.requestedClaimOn ? ', requested by the citizen' : ''
                    }${cell.iso === row.claimOn ? ', currently set' : ''}${
                      cell.past ? ', unavailable' : ''
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
        <p className="px-6 pt-2 text-xs font-semibold text-status-red" role="alert">
          {error}
        </p>
      ) : null}

      <div className="dp-foot">
        <p className="dp-picked">
          {dateValue ? (
            <span>
              New claiming date <strong>{formatPickedIso(dateValue)}</strong>
            </span>
          ) : (
            <span>Pick a day for the citizen to claim. Advance uses it too.</span>
          )}
        </p>

        <div className="flex shrink-0 items-center gap-2">
          {dateValue ? (
            <button type="button" onClick={() => setDateValue('')} className="cr-btn">
              Clear
            </button>
          ) : null}
          <button
            type="button"
            disabled={pending || !dateValue || dateValue === row.claimOn}
            onClick={() =>
              startTransition(async () => {
                setError('');
                const result = await setClaimDate(row.id, dateValue);
                if (result.ok) {
                  router.refresh();
                } else {
                  setError(result.message);
                }
              })
            }
            className="cr-btn cr-btn-primary disabled:opacity-40"
          >
            {pending ? 'Saving…' : 'Apply'}
          </button>
        </div>
      </div>
    </div>
  );
}

function Conversation({ row }: { row: ReviewRow }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [body, setBody] = useState('');
  const [error, setError] = useState('');

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="min-h-[256px] flex-1 space-y-[8px] overflow-y-auto px-[26.5px] py-[19px]">
        {row.messages.length === 0 ? (
          <div className="flex h-full flex-col items-center justify-center text-center">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src="/images/staff/chat-empty.svg"
              alt=""
              style={{ width: 80, height: 78.5, maxWidth: 'none' }}
            />
            <p className="mt-[14.5px] text-[20px] font-extrabold text-[#017209]">
              You are now chatting with the client
            </p>
            <p className="text-[18px] font-medium text-black">Your conversation is secured</p>
          </div>
        ) : (
          row.messages.map((msg, i) => (
            <div key={`${msg.time}-${i}`}>
              {i === 0 || msg.time !== row.messages[i - 1].time ? (
                <p className="py-[8px] text-center text-[14px] font-medium text-black">
                  {msg.time}
                </p>
              ) : null}

              <div
                className={`flex ${
                  msg.from === 'citizen' ? 'justify-start' : 'justify-end'
                }`}
              >
                <div className="max-w-[80%]">
                  <p
                    className={`desk-bubble whitespace-pre-line ${
                      msg.from === 'citizen' ? 'from-citizen' : 'from-staff'
                    }`}
                  >
                    {msg.body}
                  </p>

                  {msg.attachment ? (
                    <a
                      href={msg.attachment.url}
                      target="_blank"
                      rel="noopener"
                      className={`mt-[6px] flex items-center gap-[8px] rounded-[8px] border border-hairline bg-white px-[11px] py-[8px] text-[14px] font-medium text-ink transition hover:bg-green-wash${
                        msg.from === 'citizen' ? '' : ' justify-end'
                      }`}
                    >
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img
                        src="/images/staff/icon-file.svg"
                        alt=""
                        style={{ width: 16, height: 16, maxWidth: 'none' }}
                      />
                      <span className="min-w-0 truncate">{msg.attachment.name}</span>
                    </a>
                  ) : null}

                  {msg.from !== 'citizen' ? (
                    <p className="mt-[4px] flex items-center justify-end gap-[4px] text-[14px] font-medium text-black">
                      <span>{msg.read ? 'Read' : 'Sent'}</span>
                      <span
                        className={`flex items-center${msg.read ? '' : ' opacity-40'}`}
                        aria-hidden="true"
                      >
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src="/images/staff/icon-check.svg"
                          alt=""
                          style={{ width: 14.5, height: 14.5, maxWidth: 'none' }}
                        />
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src="/images/staff/icon-check.svg"
                          alt=""
                          className="-ml-[7px]"
                          style={{ width: 14.5, height: 14.5, maxWidth: 'none' }}
                        />
                      </span>
                    </p>
                  ) : null}
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      <div className="h-[2px] w-full bg-[#d9d9d9]" />

      {row.canMessage ? (
        <form
          onSubmit={(event) => {
            event.preventDefault();
            if (!body.trim()) return;

            startTransition(async () => {
              setError('');
              const result = await postMessage(row.id, body.trim());
              if (result.ok) {
                setBody('');
                router.refresh();
              } else {
                setError(result.message);
              }
            });
          }}
          className="px-[23px] py-[14.5px]"
        >
          {error ? (
            <p className="mb-2 text-xs font-semibold text-status-red" role="alert">
              {error}
            </p>
          ) : null}
          <div className="flex items-center gap-[16px]">
            <label htmlFor="reviewMessage" className="sr-only">
              Write your message
            </label>
            <input
              id="reviewMessage"
              type="text"
              value={body}
              onChange={(event) => setBody(event.target.value)}
              maxLength={5000}
              placeholder="Write your message..."
              className="desk-composer"
            />
            <button
              type="submit"
              disabled={pending || !body.trim()}
              className="shrink-0 rounded-lg p-1 transition hover:bg-green-wash disabled:opacity-40"
              aria-label="Send message"
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src="/images/staff/icon-send.svg"
                alt=""
                style={{ width: 24, height: 24, maxWidth: 'none' }}
              />
            </button>
          </div>
        </form>
      ) : (
        <p className="px-[23px] py-[14.5px] text-[length:var(--fs-small)] text-ink-soft">
          This thread is read-only for you — only the assigned staff member and supervisors reply
          to the citizen.
        </p>
      )}
    </div>
  );
}
