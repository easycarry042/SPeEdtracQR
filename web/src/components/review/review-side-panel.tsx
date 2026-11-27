'use client';

import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { setClaimDate } from '@/app/actions/document';
import { postMessage } from '@/app/actions/messages';
import {
  UnifiedCalendar,
  formatCalendarIso,
  type CalendarDateHighlight,
  type CalendarLegendItem,
} from '@/components/unified-calendar';
import { FilePreviewModal, type PreviewFileTarget } from '@/components/file-preview-modal';

function toIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(
    d.getDate(),
  ).padStart(2, '0')}`;
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
          <h2 className="min-w-0 truncate text-xl font-semibold text-green-deep">
            {side === 'date' ? (
              row.claimDatePolicy === 'optional' ? (
                'Adjust Claiming Date (Optional)'
              ) : (
                'Adjust Claiming Date'
              )
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
  const [bookedByMonth, setBookedByMonth] = useState<Record<string, string[]>>({});
  const [loadingAvailability, setLoadingAvailability] = useState(false);
  const fetchedKeys = useRef<Set<string>>(new Set());
  const inFlight = useRef(0);

  const todayIso = useMemo(() => toIso(new Date()), []);
  const facilityResourceId =
    row.booking && !row.booking.isEquipment ? row.booking.resourceId : null;

  const fetchAvailability = useCallback(
    async (resourceId: number, monthIso: string, signal?: AbortSignal): Promise<void> => {
      const cacheKey = `${resourceId}:${monthIso}`;
      if (fetchedKeys.current.has(cacheKey)) {
        return;
      }

      inFlight.current += 1;
      setLoadingAvailability(true);

      try {
        const res = await fetch(
          `/api/bookings/availability?resourceId=${encodeURIComponent(String(resourceId))}&month=${encodeURIComponent(monthIso)}`,
          { credentials: 'same-origin', signal },
        );
        if (!res.ok) return;
        const payload = (await res.json()) as { bookedDates?: string[] };
        const dates = Array.isArray(payload.bookedDates) ? payload.bookedDates : [];
        fetchedKeys.current.add(cacheKey);
        setBookedByMonth((prev) => ({ ...prev, [cacheKey]: dates }));
      } catch {
        // Ignore abort / offline error — server validation still enforces conflicts.
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
    const initialMonth = (initialIso || todayIso).slice(0, 7);
    void fetchAvailability(facilityResourceId, initialMonth, controller.signal);

    if (todayIso.slice(0, 7) !== initialMonth) {
      void fetchAvailability(facilityResourceId, todayIso.slice(0, 7), controller.signal);
    }

    return () => controller.abort();
  }, [facilityResourceId, initialIso, todayIso, fetchAvailability]);

  const bookedSet = useMemo(() => {
    const set = new Set<string>();
    if (!facilityResourceId) {
      return set;
    }
    const prefix = `${facilityResourceId}:`;
    for (const [key, dates] of Object.entries(bookedByMonth)) {
      if (key.startsWith(prefix)) {
        for (const day of dates) {
          set.add(day);
        }
      }
    }
    return set;
  }, [facilityResourceId, bookedByMonth]);

  // If the pre-seeded date happens to be booked on a facility, don't leave a disabled date pre-selected.
  useEffect(() => {
    if (facilityResourceId && dateValue && bookedSet.has(dateValue)) {
      setDateValue('');
    }
  }, [facilityResourceId, dateValue, bookedSet]);

  const legendItems = useMemo<CalendarLegendItem[]>(() => {
    const items: CalendarLegendItem[] = [
      {
        id: 'requested',
        label: 'Citizen asked for',
        value: row.requestedClaimDate || 'no date',
        variant: 'requested',
      },
    ];
    if (row.claimDate) {
      items.push({
        id: 'set',
        label: 'Currently set',
        value: row.claimDate,
        variant: 'set',
      });
    }
    if (facilityResourceId !== null) {
      items.push({
        id: 'booked',
        label: 'Unavailable / Booked',
        variant: 'booked',
      });
    }
    return items;
  }, [row.requestedClaimDate, row.claimDate, facilityResourceId]);

  const highlights = useMemo<CalendarDateHighlight[]>(() => {
    const items: CalendarDateHighlight[] = [];
    if (row.requestedClaimOn) {
      items.push({
        iso: row.requestedClaimOn,
        variant: 'requested',
        ariaNote: 'requested by the citizen',
      });
    }
    if (row.claimOn) {
      items.push({
        iso: row.claimOn,
        variant: 'set',
        ariaNote: 'currently set',
      });
    }
    return items;
  }, [row.requestedClaimOn, row.claimOn]);

  const isOptional = row.claimDatePolicy === 'optional';

  const handleApply = () => {
    startTransition(async () => {
      setError('');
      const result = await setClaimDate(row.id, dateValue || null);
      if (result.ok) {
        router.refresh();
      } else {
        setError(result.message);
      }
    });
  };

  const applyDisabled =
    pending ||
    (dateValue ? dateValue === (row.claimOn ?? '') || bookedSet.has(dateValue) : !isOptional || !row.claimOn);

  return (
    <UnifiedCalendar
      value={dateValue}
      onChange={setDateValue}
      minDate={todayIso}
      todayIso={todayIso}
      disabledDates={facilityResourceId !== null ? bookedSet : undefined}
      legendItems={legendItems}
      highlights={highlights}
      loading={loadingAvailability}
      error={error || null}
      ariaLabel="Claiming date"
      onMonthChange={
        facilityResourceId !== null
          ? (year, monthIndex) => {
              const monthIso = `${year}-${String(monthIndex + 1).padStart(2, '0')}`;
              void fetchAvailability(facilityResourceId, monthIso);
            }
          : undefined
      }
      onClear={() => setDateValue('')}
      onApply={handleApply}
      applyDisabled={applyDisabled}
      applyLabel={pending ? 'Saving…' : 'Apply'}
      footerSummary={
        dateValue ? (
          <span>
            New claiming date <strong>{formatCalendarIso(dateValue)}</strong>
          </span>
        ) : isOptional ? (
          <span>Optional for facility reservations — booked dates are greyed out.</span>
        ) : (
          <span>Pick a day for the citizen to claim. Advance uses it too.</span>
        )
      }
    />
  );
}

function Conversation({ row }: { row: ReviewRow }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [body, setBody] = useState('');
  const [error, setError] = useState('');
  const [previewFile, setPreviewFile] = useState<PreviewFileTarget | null>(null);

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
                    <button
                      type="button"
                      onClick={() => {
                        const ext = /\.([^.]+)$/.exec(msg.attachment?.name ?? '')?.[1] ?? '';
                        setPreviewFile({
                          url: msg.attachment!.url,
                          downloadUrl: msg.attachment!.url,
                          title: msg.attachment!.name,
                          ext,
                        });
                      }}
                      className={`mt-[6px] flex w-full cursor-pointer items-center gap-[8px] rounded-[8px] border border-hairline bg-white px-[11px] py-[8px] text-[14px] font-medium text-ink transition hover:bg-green-wash${
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
                    </button>
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
            <p className="mb-2 text-sm font-semibold text-status-red" role="alert">
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

      <FilePreviewModal file={previewFile} onClose={() => setPreviewFile(null)} />
    </div>
  );
}
