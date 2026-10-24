'use client';

import { useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import type { ReviewRow } from '@/lib/domain/request-review';
import { BLOCKED_BY } from '@/lib/domain/document';
import { DocumentStatus, FINISHABLE } from '@/lib/domain/document-status';
import {
  type ActionResult,
  acceptAssignment,
  advanceDocument,
  assignDocument,
  finishDocument,
  declineAssignment,
  denyDocument,
  holdDocument,
  requestRevision,
  revertDocument,
  setDocumentStatus,
  unholdDocument,
} from '@/app/actions/document';
import { NotifyButton } from './notify-dialog';

export type ReviewMode = 'supervisor' | 'staff' | 'oversight';

/**
 * The cockpit: everything that can be done to this request from here.
 *
 * What is offered follows `row.canAdvance`, which is computed by the same rule
 * the server enforces — the UI never shows an action the backend will then
 * refuse. Stage-gate failures surface inline under the buttons, not in an
 * alert, so the note someone just typed is never lost.
 */
type Dialog =
  | 'advance'
  | 'finish'
  | 'revert'
  | 'return'
  | 'hold'
  | 'deny'
  | 'decline'
  | 'revision'
  | null;

export function ReviewActions({
  row,
  mode,
  assignableStaff,
  onDone,
}: {
  row: ReviewRow;
  mode: ReviewMode;
  assignableStaff: Array<{ id: number; name: string }>;
  onDone: () => void;
}) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [dialog, setDialog] = useState<Dialog>(null);
  const [errors, setErrors] = useState<string[]>([]);
  // Unassigned: start on the type's first usual handler (CTCAO's assignment
  // table) if they can be picked here; the supervisor confirms with Assign.
  const suggested = row.usualHandlers.find((handler) => assignableStaff.some((staff) => staff.id === handler.id));
  const [assignee, setAssignee] = useState<string>(String(row.assignedTo ?? suggested?.id ?? ''));

  const run = (action: () => Promise<ActionResult>, closeOnSuccess = true): void => {
    setErrors([]);

    startTransition(async () => {
      const result = await action();

      if (result.ok) {
        setDialog(null);
        router.refresh();

        if (closeOnSuccess) {
          onDone();
        }

        return;
      }

      setErrors(result.errors?.length ? result.errors : [result.message]);
    });
  };

  const isTerminal = ([DocumentStatus.Completed, DocumentStatus.Denied, DocumentStatus.Withdrawn] as string[]).includes(
    row.status,
  );

  return (
    <div className="dm-foot">
      {/* Stage-gate / concurrency failures surface here, inline. */}
      {errors.length > 0 ? (
        <ul className="gate-errors" role="alert">
          {errors.map((error) => (
            <li key={error}>{error}</li>
          ))}
        </ul>
      ) : null}

      {/* ── Supervisor: assign / reassign / deny ────────────────────────── */}
      {mode === 'supervisor' && !isTerminal ? (
        <section className="dm-panel-strong">
          <label className="req-label" htmlFor={`assign-${row.id}`}>
            {row.assignedTo ? 'Reassign to' : 'Assign to'}
          </label>
          <div className="mt-2 flex flex-wrap gap-2">
            <select
              id={`assign-${row.id}`}
              className="field req-select"
              value={assignee}
              onChange={(event) => setAssignee(event.target.value)}
            >
              <option value="">Choose a staff member…</option>
              {assignableStaff.map((staff) => (
                <option key={staff.id} value={staff.id}>
                  {staff.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending || assignee === ''}
              onClick={() => run(() => assignDocument(row.id, Number(assignee)))}
            >
              {row.assignedTo ? 'Reassign' : 'Assign'}
            </button>
            <button
              type="button"
              className="cr-btn cr-btn-danger"
              disabled={pending}
              onClick={() => setDialog('deny')}
            >
              Deny
            </button>
          </div>
          {row.usualHandlers.length > 0 ? (
            <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
              Usually handled by {row.usualHandlers.map((handler) => handler.name).join(' or ')}.
            </p>
          ) : null}
          {assignableStaff.length === 0 ? (
            <p className="mt-2 text-[length:var(--fs-small)] text-ink-soft">
              No staff in this office can be assigned work yet.
            </p>
          ) : null}
        </section>
      ) : null}

      {/* ── Staff triage: this assignment is awaiting a decision ────────── */}
      {mode === 'staff' && row.needsTriage ? (
        <section className="dm-panel-strong space-y-2 text-[length:var(--fs-small)] text-ink">
          <p className="font-bold">This request is waiting for you to accept it.</p>
          <div className="gate-actions">
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending}
              onClick={() => run(() => acceptAssignment(row.id), false)}
            >
              Accept
            </button>
            <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={() => setDialog('decline')}>
              Decline
            </button>
            <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={() => setDialog('revision')}>
              Request revision
            </button>
          </div>
        </section>
      ) : null}

      {/* ── Status cockpit ─────────────────────────────────────────────── */}
      {row.canAdvance && !isTerminal && !(mode === 'staff' && row.needsTriage) ? (
        <div className="gate-actions mt-4">
          {row.status === DocumentStatus.OnHold ? (
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending}
              onClick={() => run(() => unholdDocument(row.id), false)}
            >
              Lift hold
            </button>
          ) : (
            <>
              {/* Pending → Ongoing → Done: accepting starts it, Finish ends it. */}
              {(FINISHABLE as string[]).includes(row.status) ? (
                <button
                  type="button"
                  className="btn-step btn-step-fwd"
                  disabled={pending}
                  onClick={() => setDialog('finish')}
                >
                  Finish request
                </button>
              ) : row.status === DocumentStatus.Returned ? (
                <button
                  type="button"
                  className="btn-step btn-step-fwd"
                  disabled={pending}
                  onClick={() =>
                    run(
                      () =>
                        setDocumentStatus(row.id, {
                          expectedStatus: row.status,
                          status: DocumentStatus.InProgress,
                        }),
                      false,
                    )
                  }
                >
                  Resume (back to Ongoing)
                </button>
              ) : row.status === DocumentStatus.Pending && (row.assignedTo === null || row.needsTriage) ? null : (
                // Pending moves on when the assignee accepts; Advance is only for
                // a Pending request someone already accepted (older records).
                <button
                  type="button"
                  className="btn-step btn-step-fwd"
                  disabled={pending}
                  onClick={() => setDialog('advance')}
                >
                  Advance
                </button>
              )}

              {row.status !== DocumentStatus.Returned ? (
                <>
                  {row.status !== DocumentStatus.Pending ? (
                    <button type="button" className="btn-step btn-step-back" disabled={pending} onClick={() => setDialog('revert')}>
                      Move back
                    </button>
                  ) : null}
                  <button type="button" className="btn-step btn-step-return" disabled={pending} onClick={() => setDialog('return')}>
                    Return for revision
                  </button>
                </>
              ) : null}
              <button type="button" className="btn-step btn-step-hold" disabled={pending} onClick={() => setDialog('hold')}>
                Put on hold
              </button>
            </>
          )}
        </div>
      ) : null}

      {/* ── Oversight: watch, and push — never reassign ─────────────────── */}
      {mode === 'oversight' ? (
        <div className="mt-3">
          <p className="text-[length:var(--fs-small)] text-ink-soft">
            Oversight view — this page watches every office&apos;s requests without acting on them.
          </p>
          {/* Finished work has nobody left to chase, which is why the Blade
              version sent no nudge URL for a terminal request. */}
          {!isTerminal ? (
            <div className="mt-3">
              <NotifyButton
                subject={{
                  documentId: row.id,
                  tracking: row.trackingNumber,
                  type: row.documentType,
                  citizen: row.citizenName,
                  purpose: row.purpose,
                  stageLabel: row.statusLabel,
                  office: row.department,
                  assignee: row.assignedStaff,
                  over: row.overSla,
                }}
              />
            </div>
          ) : null}
        </div>
      ) : null}

      {/* ── Dialogs ────────────────────────────────────────────────────── */}
      {dialog === 'advance' ? (
        <AdvanceDialog
          row={row}
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(claimDate, note) =>
            run(() =>
              advanceDocument(row.id, { expectedStatus: row.status, claimDate, note }),
            )
          }
        />
      ) : null}

      {dialog === 'finish' ? (
        <FinishDialog
          row={row}
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(note) => run(() => finishDocument(row.id, { expectedStatus: row.status, note }))}
        />
      ) : null}

      {dialog === 'revert' ? (
        <ReasonDialog
          title="Move back a stage"
          hint="Moving a request backwards always carries a reason — it is part of the audit trail."
          submitLabel="Move back"
          required
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(reason) => run(() => revertDocument(row.id, { expectedStatus: row.status, reason }))}
        />
      ) : null}

      {dialog === 'return' ? (
        <ReasonDialog
          title="Return for revision"
          hint="Say what the citizen needs to fix — this is shown on their tracking page."
          submitLabel="Return"
          required
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(reason) =>
            run(() =>
              setDocumentStatus(row.id, {
                expectedStatus: row.status,
                status: DocumentStatus.Returned,
                reason,
              }),
            )
          }
        />
      ) : null}

      {dialog === 'deny' ? (
        <ReasonDialog
          title="Deny request"
          hint="The reason is shown to the citizen on their tracking page."
          submitLabel="Deny"
          danger
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(reason) => run(() => denyDocument(row.id, reason))}
        />
      ) : null}

      {dialog === 'decline' ? (
        <ReasonDialog
          title="Decline assignment"
          hint="It goes back to the supervisor's queue. Telling them why helps them reassign it."
          submitLabel="Decline"
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(reason) => run(() => declineAssignment(row.id, reason))}
        />
      ) : null}

      {dialog === 'revision' ? (
        <ReasonDialog
          title="Request revision"
          hint="Say what the citizen needs to fix before this can be worked on."
          submitLabel="Return for revision"
          required
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(reason) => run(() => requestRevision(row.id, reason))}
        />
      ) : null}

      {dialog === 'hold' ? (
        <HoldDialog
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(input) => run(() => holdDocument(row.id, input), false)}
        />
      ) : null}
    </div>
  );
}

function DialogShell({
  title,
  children,
  errors,
}: {
  title: string;
  children: React.ReactNode;
  errors: string[];
}) {
  return (
    <div className="gate-modal" role="dialog" aria-modal="true">
      <div className="gate-modal-card">
        <h3 className="text-base font-bold text-ink">{title}</h3>
        {errors.length > 0 ? (
          <ul className="gate-errors" role="alert">
            {errors.map((error) => (
              <li key={error}>{error}</li>
            ))}
          </ul>
        ) : null}
        {children}
      </div>
    </div>
  );
}

/**
 * Advancing sets the day the citizen collects. It replaced a free-text note:
 * the same decision, but a date the citizen can act on.
 */
function AdvanceDialog({
  row,
  pending,
  errors,
  onCancel,
  onSubmit,
}: {
  row: ReviewRow;
  pending: boolean;
  errors: string[];
  onCancel: () => void;
  onSubmit: (claimDate: string, note: string | null) => void;
}) {
  // Prefill with the date already set (Adjust Date), else the citizen's ask —
  // but never a day that has passed, which the server would refuse anyway.
  const todayIso = localIsoDate(new Date());
  const usable = (day: string | null): day is string => day !== null && day >= todayIso;
  const setPassed = row.claimOn !== null && !usable(row.claimOn);
  const [claimDate, setClaimDate] = useState(
    usable(row.claimOn) ? row.claimOn : usable(row.requestedClaimOn) ? row.requestedClaimOn : '',
  );
  const [note, setNote] = useState('');

  return (
    <DialogShell title="Advance this request" errors={errors}>
      <label className="gate-claim-label" htmlFor={`claim-${row.id}`}>
        Claiming date
      </label>
      <input
        id={`claim-${row.id}`}
        type="date"
        className="field"
        value={claimDate}
        min={todayIso}
        onChange={(event) => setClaimDate(event.target.value)}
        required
      />
      <p className="gate-claim-hint">
        {setPassed
          ? `The claiming date (${row.claimDate}) has passed. Pick a new one for the citizen.`
          : 'The day the citizen can collect. Their reservation, if any, moves with it.'}
        {row.requestedClaimDate ? ` They asked for ${row.requestedClaimDate}.` : ''}
      </p>

      <label className="gate-claim-label mt-3" htmlFor={`note-${row.id}`}>
        Note <span className="text-ink-soft">(optional)</span>
      </label>
      <textarea
        id={`note-${row.id}`}
        className="field"
        rows={3}
        value={note}
        onChange={(event) => setNote(event.target.value)}
        maxLength={1000}
      />

      <div className="gate-actions mt-4">
        <button
          type="button"
          className="cr-btn cr-btn-primary"
          disabled={pending || claimDate === ''}
          onClick={() => onSubmit(claimDate, note.trim() || null)}
        >
          {pending ? 'Saving…' : 'Advance'}
        </button>
        <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={onCancel}>
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

/**
 * Finish request (Ongoing → Done). Says up front what the server will check —
 * required files still unchecked, a reservation not yet approved — so the
 * click is not wasted; the server enforces the same rules either way.
 */
function FinishDialog({
  row,
  pending,
  errors,
  onCancel,
  onSubmit,
}: {
  row: ReviewRow;
  pending: boolean;
  errors: string[];
  onCancel: () => void;
  onSubmit: (note: string | null) => void;
}) {
  const [note, setNote] = useState('');
  const unchecked = row.requirements.filter((requirement) => requirement.isMandatory && !requirement.verified);
  // pending / cancelled are the two lending states of a booking not (or no longer) approved.
  const bookingWaiting = row.booking !== null && (row.booking.state === 'pending' || row.booking.state === 'cancelled');

  return (
    <DialogShell title="Finish this request" errors={errors}>
      <p className="gate-claim-hint">
        Marks it <strong>Done</strong>. Your supervisor is notified and the requester sees it as done on
        their tracking page.
      </p>

      {unchecked.length > 0 || bookingWaiting ? (
        <ul className="gate-checks mt-3">
          {unchecked.length > 0 ? (
            <li className="no">Check these required files first: {unchecked.map((r) => r.label).join(', ')}</li>
          ) : null}
          {bookingWaiting ? (
            <li className="no">Approve the reservation first (in this window, or on the Resources page)</li>
          ) : null}
        </ul>
      ) : null}

      <label className="gate-claim-label mt-3" htmlFor={`finish-note-${row.id}`}>
        What was done <span className="text-ink-soft">(optional)</span>
      </label>
      <textarea
        id={`finish-note-${row.id}`}
        className="field w-full"
        rows={3}
        value={note}
        onChange={(event) => setNote(event.target.value)}
        maxLength={1000}
        placeholder="e.g. 50 leis delivered to the City Hall lobby"
      />

      <div className="gate-actions mt-4">
        <button
          type="button"
          className="cr-btn cr-btn-primary"
          disabled={pending}
          onClick={() => onSubmit(note.trim() || null)}
        >
          {pending ? 'Saving…' : 'Mark as done'}
        </button>
        <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={onCancel}>
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

/** `YYYY-MM-DD` in the browser's own day, matching `<input type="date">`. */
function localIsoDate(date: Date): string {
  const pad = (value: number): string => String(value).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function ReasonDialog({
  title,
  hint,
  submitLabel,
  required = false,
  danger = false,
  pending,
  errors,
  onCancel,
  onSubmit,
}: {
  title: string;
  hint: string;
  submitLabel: string;
  required?: boolean;
  danger?: boolean;
  pending: boolean;
  errors: string[];
  onCancel: () => void;
  onSubmit: (reason: string) => void;
}) {
  const [reason, setReason] = useState('');

  return (
    <DialogShell title={title} errors={errors}>
      <p className="gate-claim-hint">{hint}</p>
      <textarea
        className="field mt-2"
        rows={4}
        value={reason}
        onChange={(event) => setReason(event.target.value)}
        maxLength={1000}
        autoFocus
      />
      <div className="gate-actions mt-4">
        <button
          type="button"
          className={`cr-btn ${danger ? 'cr-btn-danger' : 'cr-btn-primary'}`}
          disabled={pending || (required && reason.trim() === '')}
          onClick={() => onSubmit(reason.trim())}
        >
          {pending ? 'Saving…' : submitLabel}
        </button>
        <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={onCancel}>
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

/** Parking a blocked request. The SLA clock pauses while it is held. */
function HoldDialog({
  pending,
  errors,
  onCancel,
  onSubmit,
}: {
  pending: boolean;
  errors: string[];
  onCancel: () => void;
  onSubmit: (input: { holdReason: string; blockedBy: string; holdUntil: string | null }) => void;
}) {
  const [holdReason, setHoldReason] = useState('');
  const [blockedBy, setBlockedBy] = useState<string>(BLOCKED_BY[0]);
  const [holdUntil, setHoldUntil] = useState('');

  return (
    <DialogShell title="Put on hold" errors={errors}>
      <p className="gate-claim-hint">
        The deadline clock pauses while a request is on hold, so say who it is waiting on — a
        forgotten hold is chased on its own schedule.
      </p>

      <label className="gate-claim-label mt-3" htmlFor="blocked-by">
        Waiting on
      </label>
      <select
        id="blocked-by"
        className="field"
        value={blockedBy}
        onChange={(event) => setBlockedBy(event.target.value)}
      >
        {BLOCKED_BY.map((who) => (
          <option key={who} value={who}>
            {who.charAt(0).toUpperCase() + who.slice(1)}
          </option>
        ))}
      </select>

      <label className="gate-claim-label mt-3" htmlFor="hold-reason">
        Reason
      </label>
      <textarea
        id="hold-reason"
        className="field"
        rows={3}
        value={holdReason}
        onChange={(event) => setHoldReason(event.target.value)}
        maxLength={1000}
      />

      <label className="gate-claim-label mt-3" htmlFor="hold-until">
        Expected to resume <span className="text-ink-soft">(optional)</span>
      </label>
      <input
        id="hold-until"
        type="date"
        className="field"
        value={holdUntil}
        onChange={(event) => setHoldUntil(event.target.value)}
      />

      <div className="gate-actions mt-4">
        <button
          type="button"
          className="cr-btn cr-btn-primary"
          disabled={pending || holdReason.trim() === ''}
          onClick={() =>
            onSubmit({ holdReason: holdReason.trim(), blockedBy, holdUntil: holdUntil || null })
          }
        >
          {pending ? 'Saving…' : 'Put on hold'}
        </button>
        <button type="button" className="cr-btn cr-btn-ghost" disabled={pending} onClick={onCancel}>
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}
