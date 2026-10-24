'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { setClaimDate } from '@/app/actions/document';
import { postMessage } from '@/app/actions/messages';

/**
 * The modal's right-hand card: the claiming-date calendar, or the conversation
 * with the citizen.
 *
 * Both used to live on the Look Up desk, which is why a request could be
 * answered there but not advanced, and advanced on the dashboard but not
 * answered. They are here now so one screen finishes one request.
 */
export function ReviewSidePanel({
  row,
  side,
  onSideChange,
}: {
  row: ReviewRow;
  side: 'calendar' | 'messages';
  onSideChange: (side: 'calendar' | 'messages') => void;
}) {
  return (
    <div className="dm-card dm-fixed flex w-full max-w-[460px] flex-col lg:min-w-0">
      <div className="dm-head">
        <h2 className="dm-section min-w-0 truncate">
          {side === 'calendar' ? 'Claiming date' : 'Messages'}
        </h2>
        <div className="desk-tabs">
          <button
            type="button"
            className={side === 'calendar' ? 'on' : undefined}
            onClick={() => onSideChange('calendar')}
          >
            Calendar
          </button>
          <button
            type="button"
            className={side === 'messages' ? 'on' : undefined}
            onClick={() => onSideChange('messages')}
          >
            Messages
            {row.unreadMessages > 0 ? <span className="lookup-tab-count">{row.unreadMessages}</span> : null}
          </button>
        </div>
      </div>

      <div className="dm-body">
        {side === 'calendar' ? <ClaimCalendar row={row} /> : <Conversation row={row} />}
      </div>
    </div>
  );
}

function ClaimCalendar({ row }: { row: ReviewRow }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [value, setValue] = useState(row.claimOn ?? '');
  const [error, setError] = useState('');

  return (
    <div>
      {/* Where things stand before anything is picked. */}
      <dl className="dp-context">
        <div className="dp-context-item">
          <dt>Citizen asked for</dt>
          <dd>{row.requestedClaimDate ?? 'No date given'}</dd>
        </div>
        <div className="dp-context-item">
          <dt>Committed to</dt>
          <dd>{row.claimDate ?? 'Not set'}</dd>
        </div>
      </dl>

      <label className="gate-claim-label mt-4" htmlFor={`claim-date-${row.id}`}>
        Set the claiming date
      </label>
      <input
        id={`claim-date-${row.id}`}
        type="date"
        className="field"
        value={value}
        onChange={(event) => setValue(event.target.value)}
      />

      {error ? (
        <p className="form-error mt-2" role="alert">
          {error}
        </p>
      ) : null}

      <div className="gate-actions mt-3">
        <button
          type="button"
          className="cr-btn cr-btn-primary cr-btn-sm"
          disabled={pending || value === '' || value === row.claimOn}
          onClick={() =>
            startTransition(async () => {
              setError('');
              const result = await setClaimDate(row.id, value);

              if (result.ok) {
                router.refresh();
              } else {
                setError(result.message);
              }
            })
          }
        >
          {pending ? 'Saving…' : 'Save date'}
        </button>
      </div>

      <p className="gate-claim-hint mt-3">
        A reservation on this request moves with the claiming date, keeping its length — so the
        calendar and the request never show two different days.
      </p>
    </div>
  );
}

function Conversation({ row }: { row: ReviewRow }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [body, setBody] = useState('');
  const [error, setError] = useState('');

  return (
    <div className="desk-composer-wrap">
      {row.messages.length === 0 ? (
        <div className="gs-empty">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src="/images/staff/chat-empty.svg" alt="" aria-hidden="true" />
          <p className="gs-empty-title">No messages yet</p>
          <p className="gs-empty-body">
            Anything you send here reaches the citizen on their tracking page.
          </p>
        </div>
      ) : (
        <ul className="desk-thread">
          {row.messages.map((message, index) => (
            <li
              key={`${message.time}-${index}`}
              className={`desk-bubble ${message.from === 'citizen' ? 'is-citizen' : 'is-staff'}`}
            >
              <p className="speedy-who">
                {message.author}
                <span className="speedy-time">{message.time}</span>
              </p>
              <p className="whitespace-pre-line">{message.body}</p>
              {message.attachment ? (
                <a href={message.attachment.url} target="_blank" rel="noopener" className="speedy-attachment">
                  {message.attachment.name}
                </a>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {row.canMessage ? (
        <form
          className="desk-composer"
          onSubmit={(event) => {
            event.preventDefault();

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
        >
          <textarea
            className="field"
            rows={3}
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Reply to the citizen…"
            maxLength={2000}
          />
          {error ? (
            <p className="form-error" role="alert">
              {error}
            </p>
          ) : null}
          <button type="submit" className="cr-btn cr-btn-primary cr-btn-sm" disabled={pending || body.trim() === ''}>
            {pending ? 'Sending…' : 'Send'}
          </button>
        </form>
      ) : (
        <p className="gate-claim-hint">
          This thread is read-only for you — only the assigned staff member and supervisors reply
          to the citizen.
        </p>
      )}
    </div>
  );
}
