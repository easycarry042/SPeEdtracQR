'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { claimRequest, postStaffHighlight } from '@/app/actions/staff';

/** The interactive bits of the staff profile. */
type TimelineRow = { tone: string; headline: string; detail: string | null; time: string; title: string };

/** Four rows show the shape of the day; the rest stay one click away. */
export function FoldedTimeline({ rows }: { rows: TimelineRow[] }) {
  const [open, setOpen] = useState(false);
  const shown = open ? rows : rows.slice(0, 4);
  const rest = rows.length - 4;

  return (
    <>
      <ol className="gs-tl">
        {shown.map((row, index) => (
          <li key={index} className={`gs-tl-row t-${row.tone}`}>
            <time className="gs-tl-time" title={row.title || undefined}>
              {row.time}
            </time>
            <p className="gs-tl-headline">{row.headline}</p>
            {row.detail ? <p className="gs-tl-detail">{row.detail}</p> : null}
          </li>
        ))}
      </ol>
      {rest > 0 ? (
        <button type="button" className="gs-tl-more" onClick={() => setOpen(!open)} aria-expanded={open}>
          {open ? 'Show fewer' : `Show ${rest} earlier update${rest === 1 ? '' : 's'}…`}
        </button>
      ) : null}
    </>
  );
}

export function ClaimButton({ documentId, label }: { documentId: number; label: string }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);

  return (
    <span className="gs-row-side">
      <button
        type="button"
        disabled={pending}
        className="gs-btn gs-btn-primary"
        onClick={() =>
          startTransition(async () => {
            const result = await claimRequest(documentId);

            if (result.ok) {
              router.refresh();
            } else {
              setError(result.message);
            }
          })
        }
      >
        Accept<span className="sr-only"> {label}</span>
      </button>
      {error ? (
        <span className="text-xs text-status-red" role="alert">
          {error}
        </span>
      ) : null}
    </span>
  );
}

/**
 * The highlight composer — own profile only. A highlight may reference one of
 * the author's own requests (a link, not an upload).
 */
export function Composer({ documents }: { documents: Array<{ id: number; label: string }> }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);
  const [body, setBody] = useState('');
  const [type, setType] = useState('note');
  const [documentId, setDocumentId] = useState('');
  const [notice, setNotice] = useState<{ ok: boolean; message: string } | null>(null);
  const bodyRef = useRef<HTMLTextAreaElement>(null);
  const dirty = body.trim().length > 0;

  useEffect(() => {
    const warn = (event: BeforeUnloadEvent): void => {
      if (dirty && !pending) {
        event.preventDefault();
      }
    };

    window.addEventListener('beforeunload', warn);

    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty, pending]);

  const submit = (): void => {
    startTransition(async () => {
      const result = await postStaffHighlight({ body, type, documentId: documentId ? Number(documentId) : null });
      setNotice(result);

      if (result.ok) {
        setBody('');
        setDocumentId('');
        setOpen(false);
        router.refresh();
      }
    });
  };

  return (
    <section className="gs-surface">
      {notice ? (
        <p className={notice.ok ? 'gs-notice' : 'gs-surface-body text-sm text-status-red'} role="status">
          {notice.message}
        </p>
      ) : null}

      {!open ? (
        <button
          type="button"
          className="gs-composer-prompt"
          onClick={() => {
            setOpen(true);
            requestAnimationFrame(() => bodyRef.current?.focus());
          }}
        >
          <span>Share an accomplishment…</span>
        </button>
      ) : (
        <form
          className="gs-surface-body"
          style={{ display: 'flex', flexDirection: 'column', gap: 12 }}
          onSubmit={(event) => {
            event.preventDefault();
            submit();
          }}
        >
          <div>
            <label htmlFor="gs-highlight-body" className="sr-only">
              Highlight
            </label>
            {/* ⌘/Ctrl+Enter posts; plain Enter keeps inserting newlines. */}
            <textarea
              id="gs-highlight-body"
              ref={bodyRef}
              value={body}
              onChange={(event) => setBody(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
                  event.preventDefault();
                  submit();
                }
              }}
              rows={3}
              maxLength={2000}
              required
              placeholder="Share an accomplishment, milestone, or note…"
              autoComplete="off"
              className="gs-field"
              style={{ resize: 'vertical' }}
            />
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
            <div className="gs-seg" role="group" aria-label="Highlight type">
              {[
                ['note', 'Note'],
                ['completed', 'Completed'],
                ['milestone', 'Milestone'],
              ].map(([value, label]) => (
                <button key={value} type="button" className="gs-seg-btn" aria-pressed={type === value} onClick={() => setType(value)}>
                  {label}
                </button>
              ))}
            </div>
            <span className="gs-num" style={{ fontSize: 12, color: 'var(--gs-mute)' }} aria-live="polite">
              {body.length} / 2000
            </span>
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
            <div>
              <label htmlFor="gs-highlight-doc" className="sr-only">
                Attach a request
              </label>
              <select
                id="gs-highlight-doc"
                value={documentId}
                onChange={(event) => setDocumentId(event.target.value)}
                className="gs-field"
                style={{ maxWidth: 320 }}
              >
                <option value="">Attach a request (optional)…</option>
                {documents.map((doc) => (
                  <option key={doc.id} value={doc.id}>
                    {doc.label}
                  </option>
                ))}
              </select>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <span style={{ fontSize: 12, color: 'var(--gs-mute)' }}>Visible to staff</span>
              <button type="button" className="gs-btn" onClick={() => setOpen(false)}>
                Cancel
              </button>
              <button type="submit" className="gs-btn gs-btn-primary" disabled={pending}>
                {pending ? 'Posting…' : 'Post'}
              </button>
            </div>
          </div>
        </form>
      )}
    </section>
  );
}
