'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { nudgeDocument } from '@/app/actions/document';

/**
 * "Notify about this request" — one dialog, two call sites, as the Blade
 * `admin/partials/notify-dialog` was: the command center's Needs Attention rows
 * and the Review modal in oversight mode.
 *
 * The super admin flags a request for whoever owns it — the assignee, or the
 * supervisors of its office — rather than reassigning it. Who it reaches is
 * spelled out before sending, because the one thing this button must never read
 * as is a reassignment.
 */
export type NotifySubject = {
  documentId: number;
  tracking: string;
  type: string;
  citizen?: string | null;
  purpose?: string | null;
  stageLabel?: string | null;
  office?: string | null;
  assignee?: string | null;
  /** How far past its stage's time it is, unsigned ("3d 2h"). */
  over?: string | null;
};

export function NotifyButton({
  subject,
  className = 'cr-btn cr-btn-primary',
  label = 'Notify',
}: {
  subject: NotifySubject;
  className?: string;
  label?: string;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [open, setOpen] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);
  // A nudge changes nothing on screen, so without this there is no sign it
  // worked — this is where the Blade version's flashed banner went.
  const [notice, setNotice] = useState<string | null>(null);

  const send = (note: string | null): void => {
    setErrors([]);

    startTransition(async () => {
      const result = await nudgeDocument(subject.documentId, note);

      if (result.ok) {
        setOpen(false);
        setNotice(result.message);
        router.refresh();

        return;
      }

      setErrors(result.errors?.length ? result.errors : [result.message]);
    });
  };

  return (
    <>
      <button
        type="button"
        className={className}
        disabled={pending}
        onClick={(event) => {
          // The command center's rows are themselves clickable; Notify is its
          // own button, not the whole row.
          event.stopPropagation();
          setNotice(null);
          setOpen(true);
        }}
      >
        {label}
      </button>

      {notice ? (
        <p className="mt-2 text-[length:var(--fs-small)] text-green-deep" role="status">
          {notice}
        </p>
      ) : null}

      {open ? (
        <NotifyDialog
          subject={subject}
          pending={pending}
          errors={errors}
          onCancel={() => setOpen(false)}
          onSubmit={send}
        />
      ) : null}
    </>
  );
}

function NotifyDialog({
  subject,
  pending,
  errors,
  onCancel,
  onSubmit,
}: {
  subject: NotifySubject;
  pending: boolean;
  errors: string[];
  onCancel: () => void;
  onSubmit: (note: string | null) => void;
}) {
  const [note, setNote] = useState('');

  const recipient =
    subject.assignee ?? (subject.office ? `the ${subject.office} supervisors` : 'the supervisors');

  return (
    <div className="gate-modal" role="dialog" aria-modal="true" onClick={(event) => event.stopPropagation()}>
      <div className="gate-modal-card">
        <h3 className="text-base font-bold text-ink">Notify about this request</h3>
        <p className="dm-subtitle mt-1">
          <span className="id-chip">{subject.tracking}</span>
          <span className="dm-chip-info">{subject.type}</span>
        </p>

        {errors.length > 0 ? (
          <ul className="gate-errors" role="alert">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        ) : null}

        <dl className="dm-info mt-3">
          {(
            [
              ['Citizen', subject.citizen],
              ['Purpose', subject.purpose],
              ['Stage', subject.stageLabel],
              ['Office', subject.office ?? 'Reached no office'],
              ['Handled by', subject.assignee ?? 'Not assigned yet'],
              ['Past its stage time', subject.over ? `+${subject.over}` : null],
            ] as const
          )
            .filter(([, value]) => value)
            .map(([key, value]) => (
              <div key={key} className="dm-info-row">
                <dt>{key}</dt>
                <dd>{value}</dd>
              </div>
            ))}
        </dl>

        <p className="dm-note mt-3">
          Sends a bell notification to <strong>{recipient}</strong> asking them to follow up. It
          does not reassign the request.
        </p>

        <label className="gate-claim-label mt-3" htmlFor={`notify-${subject.documentId}`}>
          Note <span className="text-ink-soft">(optional)</span>
        </label>
        <textarea
          id={`notify-${subject.documentId}`}
          className="field"
          rows={3}
          value={note}
          onChange={(event) => setNote(event.target.value)}
          maxLength={280}
          placeholder="Add context for whoever picks this up…"
          autoFocus
        />

        <div className="gate-actions mt-4">
          <button
            type="button"
            className="cr-btn cr-btn-primary"
            disabled={pending}
            onClick={() => onSubmit(note.trim() || null)}
          >
            {pending ? 'Sending…' : 'Send notification'}
          </button>
          <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={onCancel}>
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
}
