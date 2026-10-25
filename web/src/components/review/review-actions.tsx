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
  approveClearanceDocument,
  assignDocument,
  claimDocument,
  finishDocument,
  declineAssignment,
  denyDocument,
  holdDocument,
  requestRevision,
  returnToStaffDocument,
  revertDocument,
  setDocumentStatus,
  submitForClearanceDocument,
  unholdDocument,
} from '@/app/actions/document';
import { NotifyButton } from './notify-dialog';

export type ReviewMode = 'supervisor' | 'staff' | 'oversight';

type Dialog =
  | 'advance'
  | 'finish'
  | 'clearance'
  | 'approve_clearance'
  | 'return_staff'
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
  const [reassignOpen, setReassignOpen] = useState(false);

  // Unassigned: start on the type's first usual handler (CTCAO's assignment
  // table) if they can be picked here; the supervisor confirms with Approve/Assign.
  const suggested = row.usualHandlers.find((handler) =>
    assignableStaff.some((staff) => staff.id === handler.id),
  );
  const [assignee, setAssignee] = useState<string>(
    row.assignedTo ? '' : String(suggested?.id ?? ''),
  );

  const run = (action: () => Promise<ActionResult>, closeOnSuccess = true): void => {
    setErrors([]);

    startTransition(async () => {
      const result = await action();

      if (result.ok) {
        setDialog(null);
        setReassignOpen(false);
        router.refresh();

        if (closeOnSuccess) {
          onDone();
        }

        return;
      }

      setErrors(result.errors?.length ? result.errors : [result.message]);
    });
  };

  const isTerminal = (
    [DocumentStatus.Completed, DocumentStatus.Denied, DocumentStatus.Withdrawn] as string[]
  ).includes(row.status);

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

      {/* ── Supervisor: assign / reassign / clearance / deny ────────────── */}
      {mode === 'supervisor' && !isTerminal ? (
        row.status === DocumentStatus.InReview ? (
          <section className="dm-panel-strong space-y-3">
            <div>
              <div className="flex items-center gap-2">
                <span className="pill p-brass text-xs">Review & Sign-Off</span>
                <h4 className="font-bold text-green-deep text-sm">Review & Sign-Off</h4>
              </div>
              <p className="text-xs text-ink-soft mt-1">
                Submitted by <strong>{row.assignedStaff || 'Staff handler'}</strong> for clearance and confirmation. Review requirement verifications and booking dependencies above before deciding.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-[var(--cr-border-subtle,#e2e8f0)]">
              <button
                type="button"
                className="cr-btn cr-btn-primary"
                disabled={pending}
                onClick={() => setDialog('approve_clearance')}
              >
                Confirm & Release
              </button>
              <button
                type="button"
                className="cr-btn !border-status-amber-wash !bg-status-amber-wash !text-status-amber font-semibold"
                disabled={pending}
                onClick={() => setDialog('return_staff')}
              >
                Return for Revision
              </button>
              <button
                type="button"
                className="cr-btn cr-btn-danger"
                disabled={pending}
                onClick={() => setDialog('deny')}
              >
                Deny
              </button>
              <button
                type="button"
                onClick={() => setReassignOpen(!reassignOpen)}
                className="cr-btn cr-btn-ghost text-xs ml-auto"
              >
                {reassignOpen ? 'Cancel Reassign' : 'Reassign…'}
              </button>
            </div>
            {reassignOpen ? (
              <div className="w-full pt-2 border-t border-[var(--cr-border-subtle,#e2e8f0)]">
                <label
                  htmlFor={`reassign-${row.id}`}
                  className="block font-bold text-xs text-green-deep"
                >
                  Reassign to
                </label>
                <div className="mt-1 flex flex-wrap items-center gap-2">
                  <select
                    id={`reassign-${row.id}`}
                    value={assignee}
                    onChange={(event) => setAssignee(event.target.value)}
                    className="st-filter-field min-w-0 flex-1 text-xs"
                  >
                    <option value="">Select a staff member…</option>
                    {assignableStaff
                      .filter((m) => m.id !== row.assignedTo)
                      .map((staff) => (
                        <option key={staff.id} value={staff.id}>
                          {staff.name}
                        </option>
                      ))}
                  </select>
                  <button
                    type="button"
                    disabled={!assignee || pending}
                    onClick={() => run(() => assignDocument(row.id, Number(assignee)), false)}
                    className="cr-btn cr-btn-primary text-xs"
                  >
                    Reassign
                  </button>
                </div>
              </div>
            ) : null}
          </section>
        ) : !row.assignedTo ? (
          <section className="dm-panel-strong">
            <label
              className="block text-[length:var(--fs-small)] font-bold text-green-deep"
              htmlFor={`assign-${row.id}`}
            >
              Assign to Staff <span className="text-status-red">*</span>
            </label>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <select
                id={`assign-${row.id}`}
                className="st-filter-field min-w-0 flex-1"
                value={assignee}
                onChange={(event) => setAssignee(event.target.value)}
              >
                <option value="">Select a staff member…</option>
                {assignableStaff.map((staff) => (
                  <option key={staff.id} value={staff.id}>
                    {staff.name}
                  </option>
                ))}
              </select>
              <button
                type="button"
                className="cr-btn cr-btn-danger"
                disabled={pending}
                onClick={() => setDialog('deny')}
              >
                Deny
              </button>
              <button
                type="button"
                className="cr-btn cr-btn-primary"
                disabled={pending || assignee === ''}
                onClick={() => run(() => assignDocument(row.id, Number(assignee)))}
              >
                {pending ? 'Approving…' : 'Approve & Assign'}
              </button>
            </div>
            {row.usualHandlers.length > 0 ? (
              <p className="mt-2 text-[length:var(--fs-caption)] text-ink-soft">
                Usually handled by {row.usualHandlers.map((handler) => handler.name).join(' or ')}.
              </p>
            ) : null}
            <p className="mt-1 text-[length:var(--fs-caption)] text-ink-soft">
              Approving assigns the request to the selected staff member. They must{' '}
              <strong className="text-ink">accept it on their Requests page</strong> before work
              begins.
            </p>
          </section>
        ) : (
          <section className="dm-panel-strong text-[length:var(--fs-small)] text-ink">
            {row.overSla ? (
              <div className="mb-3 rounded-lg border border-red-300 bg-red-50 p-2.5 text-xs text-red-900">
                <div className="flex items-center gap-1.5 font-bold text-red-800">
                  <svg className="h-4 w-4 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  SLA Threshold Breached (+{row.overSla} overdue)
                </div>
                <p className="mt-1 text-red-850">
                  This request has exceeded its stage budget. As supervisor, you can reassign it to an available staff member to keep processing on schedule.
                </p>
              </div>
            ) : null}
            <p>
              Being handled by{' '}
              <strong className="text-green-deep">{row.assignedStaff || 'a staff member'}</strong>.
              You can adjust the claiming date, reassign if delayed or absent, and answer the citizen from here; the processing stages are
              theirs to advance.
            </p>

            <div className="mt-3 flex flex-wrap items-center gap-2">
              {!reassignOpen ? (
                <>
                  <button
                    type="button"
                    onClick={() => setReassignOpen(true)}
                    className="cr-btn cr-btn-sm"
                  >
                    Reassign…
                  </button>
                  <button
                    type="button"
                    className="cr-btn cr-btn-danger cr-btn-sm"
                    disabled={pending}
                    onClick={() => setDialog('deny')}
                  >
                    Deny
                  </button>
                </>
              ) : (
                <div className="w-full">
                  <label
                    htmlFor={`reassign-${row.id}`}
                    className="block font-bold text-green-deep"
                  >
                    Reassign to
                  </label>
                  <div className="mt-2 flex flex-wrap items-center gap-2">
                    <select
                      id={`reassign-${row.id}`}
                      value={assignee}
                      onChange={(event) => setAssignee(event.target.value)}
                      className="st-filter-field min-w-0 flex-1"
                    >
                      <option value="">Select a staff member…</option>
                      {assignableStaff
                        .filter((m) => m.id !== row.assignedTo)
                        .map((staff) => (
                          <option key={staff.id} value={staff.id}>
                            {staff.name}
                          </option>
                        ))}
                    </select>
                    <button
                      type="button"
                      onClick={() => {
                        setReassignOpen(false);
                        setAssignee('');
                      }}
                      className="cr-btn"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      disabled={!assignee || pending}
                      onClick={() => run(() => assignDocument(row.id, Number(assignee)), false)}
                      className="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      Reassign
                    </button>
                  </div>
                  <p className="mt-2 text-[length:var(--fs-caption)] text-ink-soft">
                    The stage stays where it is; the new staff member accepts it on their Requests
                    page before carrying on.
                  </p>
                </div>
              )}
            </div>
          </section>
        )
      ) : null}

      {/* ── Staff pool claim: unassigned request in department pool ────────── */}
      {mode === 'staff' && !row.assignedTo && !isTerminal ? (
        <section className="dm-panel-strong space-y-2 text-[length:var(--fs-small)] text-ink">
          <p className="font-bold text-green-deep">Open Request (Department Pool)</p>
          <p>
            This request has not been assigned to anyone yet. Claiming it will lock the record to your account and move it to Ongoing so you can start processing immediately.
          </p>
          <div className="gate-actions pt-1">
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending}
              onClick={() => run(() => claimDocument(row.id), false)}
            >
              {pending ? 'Claiming…' : 'Claim / Start Processing'}
            </button>
          </div>
        </section>
      ) : null}

      {/* ── Staff triage: this assignment is awaiting a decision ────────── */}
      {mode === 'staff' && row.needsTriage ? (
        <section className="dm-panel-strong space-y-2 text-[length:var(--fs-small)] text-ink">
          <p className="font-bold text-green-deep">Review this assignment before deciding.</p>
          <p>
            Check the citizen details and requirements above, then choose <strong>Accept</strong> to
            start work, <strong>Decline</strong> to send it back to the supervisor, or{' '}
            <strong>Request revision</strong> if the citizen must fix something first.
          </p>
          <div className="gate-actions pt-1">
            <button
              type="button"
              className="cr-btn cr-btn-primary"
              disabled={pending}
              onClick={() => run(() => acceptAssignment(row.id), false)}
            >
              {pending ? 'Accepting…' : 'Accept'}
            </button>
            <button
              type="button"
              className="cr-btn cr-btn-danger"
              disabled={pending}
              onClick={() => setDialog('decline')}
            >
              Decline
            </button>
            <button
              type="button"
              className="cr-btn !border-status-amber-wash !bg-status-amber-wash !text-status-amber"
              disabled={pending}
              onClick={() => setDialog('revision')}
            >
              Request revision
            </button>
          </div>
        </section>
      ) : null}

      {/* ── Staff clearance pending notice ── */}
      {mode === 'staff' && row.status === DocumentStatus.InReview ? (
        <section className="dm-panel-strong space-y-2 text-sm text-ink">
          <div className="flex items-center gap-2">
            <span className="pill p-brass text-xs">Review & Sign-Off</span>
            <p className="font-bold text-green-deep">Awaiting Supervisor Review</p>
          </div>
          <p className="text-xs text-ink-soft">
            You have completed processing and submitted this request for supervisor sign-off. Once confirmed by your supervisor, the citizen will be notified with claiming details.
          </p>
        </section>
      ) : null}

      {/* ── Status cockpit ─────────────────────────────────────────────── */}
      {row.canAdvance && !isTerminal && !(mode === 'staff' && row.needsTriage) && !(mode === 'staff' && row.status === DocumentStatus.InReview) ? (
        <div className="gate-actions mt-3">
          {mode === 'staff' && row.status === DocumentStatus.InProgress && row.remarks ? (
            <div className="mb-3 w-full rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
              <div className="flex items-center gap-1.5 font-bold text-amber-900">
                <svg className="h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Supervisor Requested Revision
              </div>
              <p className="mt-1 text-xs leading-relaxed text-amber-900">{row.remarks}</p>
              <p className="mt-2 text-xs font-semibold text-amber-700">Please address the requested corrections and resubmit for supervisor review.</p>
            </div>
          ) : null}

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
              {mode === 'staff' && row.status === DocumentStatus.InProgress ? (
                <button
                  type="button"
                  className="btn-step btn-step-fwd"
                  disabled={pending}
                  onClick={() => setDialog('clearance')}
                >
                  {row.remarks ? 'Resubmit for Supervisor Review' : 'Submit for Supervisor Review'}
                </button>
              ) : mode !== 'staff' && (FINISHABLE as string[]).includes(row.status) ? (
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
              ) : row.status === DocumentStatus.Pending &&
                (row.assignedTo === null || row.needsTriage) ? null : (
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
                    <button
                      type="button"
                      className="btn-step btn-step-back"
                      disabled={pending}
                      onClick={() => setDialog('revert')}
                    >
                      Move back
                    </button>
                  ) : null}
                  <button
                    type="button"
                    className="btn-step btn-step-return"
                    disabled={pending}
                    onClick={() => setDialog('return')}
                  >
                    Return for revision
                  </button>
                </>
              ) : null}
              <button
                type="button"
                className="btn-step btn-step-hold"
                disabled={pending}
                onClick={() => setDialog('hold')}
              >
                Put on hold
              </button>
            </>
          )}
        </div>
      ) : null}

      {/* ── Oversight: watch, and push — never reassign ─────────────────── */}
      {mode === 'oversight' ? (
        <section className="dm-panel-strong text-[length:var(--fs-small)] text-ink">
          {row.assignedTo ? (
            <p>
              Being handled by{' '}
              <strong className="text-green-deep">{row.assignedStaff || 'a staff member'}</strong>
              {row.department ? ` at the ${row.department}` : ''}. The stage is theirs to advance;
              use <strong>Notify</strong> if it needs a push.
            </p>
          ) : (
            <p>
              {row.department
                ? `Not assigned yet. The ${row.department} supervisors assign it.`
                : 'This request reached no office: its request type has none. Give the type an office on the Request Types page.'}
            </p>
          )}
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
        </section>
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
          onSubmit={(note) =>
            run(() => finishDocument(row.id, { expectedStatus: row.status, note }))
          }
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
          onSubmit={(reason) =>
            run(() => revertDocument(row.id, { expectedStatus: row.status, reason }))
          }
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
          title="Deny Request"
          hint="The official refusal reason is sent to the citizen and recorded on the document."
          submitLabel="Deny"
          danger
          required
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

      {dialog === 'clearance' ? (
        <ClearanceDialog
          row={row}
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(note) =>
            run(() => submitForClearanceDocument(row.id, note))
          }
        />
      ) : null}

      {dialog === 'approve_clearance' ? (
        <ApproveClearanceDialog
          row={row}
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(input) =>
            run(() => approveClearanceDocument(row.id, input))
          }
        />
      ) : null}

      {dialog === 'return_staff' ? (
        <ReasonDialog
          title="Return for Revision"
          hint="Specify what clerical errors or missing documents need to be resolved. The citizen will NOT see these internal remarks; their public tracker remains in an active Processing state."
          submitLabel="Return for Revision"
          required
          pending={pending}
          errors={errors}
          onCancel={() => setDialog(null)}
          onSubmit={(remarks) => run(() => returnToStaffDocument(row.id, remarks))}
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
        className="st-filter-field mt-1"
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
        className="st-filter-field mt-1 h-auto py-2"
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
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

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
  const unchecked = row.requirements.filter(
    (requirement) => requirement.isMandatory && !requirement.verified,
  );
  const bookingWaiting =
    row.booking !== null && (row.booking.state === 'pending' || row.booking.state === 'cancelled');

  return (
    <DialogShell title="Finish this request" errors={errors}>
      <p className="gate-claim-hint">
        Marks it <strong>Done</strong>. Your supervisor is notified and the requester sees it as
        done on their tracking page.
      </p>

      {unchecked.length > 0 || bookingWaiting ? (
        <ul className="gate-checks mt-3">
          {unchecked.length > 0 ? (
            <li className="no">
              Check these required files first: {unchecked.map((r) => r.label).join(', ')}
            </li>
          ) : null}
          {bookingWaiting ? (
            <li className="no">
              Approve the reservation first (in this window, or on the Resources page)
            </li>
          ) : null}
        </ul>
      ) : null}

      <label className="gate-claim-label mt-3" htmlFor={`finish-note-${row.id}`}>
        What was done <span className="text-ink-soft">(optional)</span>
      </label>
      <textarea
        id={`finish-note-${row.id}`}
        className="st-filter-field mt-1 h-auto w-full py-2"
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
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

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
        className="st-filter-field mt-2 h-auto py-2"
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
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

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
        className="st-filter-field mt-1"
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
        className="st-filter-field mt-1 h-auto py-2"
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
        className="st-filter-field mt-1"
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
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

function ClearanceDialog({
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
  const unchecked = row.requirements.filter(
    (requirement) => requirement.isMandatory && !requirement.verified,
  );
  const bookingWaiting =
    row.booking !== null && (row.booking.state === 'pending' || row.booking.state === 'cancelled');

  return (
    <DialogShell
      title={row.remarks ? 'Resubmit for Supervisor Review' : 'Submit for Supervisor Review'}
      errors={errors}
    >
      <p className="gate-claim-hint">
        Submits this request to your office supervisor for clearance and sign-off. A StatusGate check ensures all mandatory prerequisites and reservation dependencies are verified before submission.
      </p>

      {unchecked.length > 0 || bookingWaiting ? (
        <ul className="gate-checks mt-3">
          {unchecked.length > 0 ? (
            <li className="no">
              Verify required documents first: {unchecked.map((r) => r.label).join(', ')}
            </li>
          ) : null}
          {bookingWaiting ? (
            <li className="no">
              Awaiting resource reservation approval before this request can be submitted.
            </li>
          ) : null}
        </ul>
      ) : null}

      <label className="gate-claim-label mt-3 block" htmlFor={`clearance-note-${row.id}`}>
        Handover note for supervisor <span className="text-ink-soft">(optional)</span>
      </label>
      <textarea
        id={`clearance-note-${row.id}`}
        className="st-filter-field mt-1 h-auto w-full py-2"
        rows={3}
        value={note}
        onChange={(event) => setNote(event.target.value)}
        placeholder="Add any internal handover notes for the supervisor..."
      />

      <div className="gate-actions mt-4">
        <button
          type="button"
          className="cr-btn cr-btn-primary"
          disabled={pending || unchecked.length > 0 || bookingWaiting}
          onClick={() => onSubmit(note.trim() || null)}
        >
          {pending ? 'Submitting…' : 'Submit for Supervisor Review'}
        </button>
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}

function ApproveClearanceDialog({
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
  onSubmit: (input: { claimDate?: string | null; note?: string | null }) => void;
}) {
  const [claimDate, setClaimDate] = useState(row.claimDate ?? '');
  const [note, setNote] = useState('');
  const unchecked = row.requirements.filter(
    (requirement) => requirement.isMandatory && !requirement.verified,
  );
  const bookingWaiting =
    row.booking !== null && (row.booking.state === 'pending' || row.booking.state === 'cancelled');

  return (
    <DialogShell title="Confirm & Release" errors={errors}>
      <p className="gate-claim-hint">
        Confirms staff verification and marks the request <strong>Ready for Claim</strong>. Setting a release/claiming date alerts the citizen with pickup instructions and QR claim verification.
      </p>

      {unchecked.length > 0 || bookingWaiting ? (
        <ul className="gate-checks mt-3">
          {unchecked.length > 0 ? (
            <li className="no">
              Check required prerequisites first: {unchecked.map((r) => r.label).join(', ')}
            </li>
          ) : null}
          {bookingWaiting ? (
            <li className="no">
              Approve reservation dependencies first
            </li>
          ) : null}
        </ul>
      ) : null}

      <label className="gate-claim-label mt-3 block" htmlFor={`approve-claim-date-${row.id}`}>
        Citizen release / claim date <span className="text-ink-soft">(optional)</span>
      </label>
      <input
        id={`approve-claim-date-${row.id}`}
        type="date"
        className="st-filter-field mt-1"
        value={claimDate}
        min={localIsoDate(new Date())}
        onChange={(event) => setClaimDate(event.target.value)}
      />

      <label className="gate-claim-label mt-3 block" htmlFor={`approve-clearance-note-${row.id}`}>
        Claiming remarks / instructions <span className="text-ink-soft">(optional)</span>
      </label>
      <textarea
        id={`approve-clearance-note-${row.id}`}
        className="st-filter-field mt-1 h-auto w-full py-2"
        rows={3}
        value={note}
        onChange={(event) => setNote(event.target.value)}
        placeholder="Add instructions or confirmation remarks for citizen pickup..."
      />

      <div className="gate-actions mt-4">
        <button
          type="button"
          className="cr-btn cr-btn-primary"
          disabled={pending || unchecked.length > 0 || bookingWaiting}
          onClick={() => onSubmit({ claimDate: claimDate || null, note: note.trim() || null })}
        >
          {pending ? 'Confirming…' : 'Confirm & Release'}
        </button>
        <button
          type="button"
          className="cr-btn cr-btn-ghost"
          disabled={pending}
          onClick={onCancel}
        >
          Cancel
        </button>
      </div>
    </DialogShell>
  );
}
