/**
 * One materialized hop in an internal request's endorsement chain: the office
 * it must pass through, the action required there, and how/when it was acted on.
 */
export const STEP_STATUS = {
  Pending: 'pending',
  Current: 'current',
  Approved: 'approved',
  Returned: 'returned',
  Denied: 'denied',
  Skipped: 'skipped',
  /**
   * The office held the paper and passed it on without a formal endorsement —
   * recorded when the next department scans the folder into its own custody.
   * The chain grows from those scans, so it is a log, not a planned route.
   */
  Forwarded: 'forwarded',
} as const;

export type StepStatus = (typeof STEP_STATUS)[keyof typeof STEP_STATUS];

export type RequestStepRow = {
  id: number;
  document_id: number;
  step_order: number;
  department_id: number;
  recipient_id: number | null;
  action: string;
  status: string;
  acted_by: number | null;
  acted_at: Date | null;
  started_at: Date | null;
  remarks: string | null;
  signature_path: string | null;
  departmentName: string;
  departmentCode: string;
  recipientName: string | null;
  actorName: string | null;
};

/** Labelled status badge for this hop on the timeline. */
export function statusBadge(status: string): { label: string; band: string } {
  switch (status) {
    case STEP_STATUS.Current:
      return { label: 'Under review', band: 'amber' };
    case STEP_STATUS.Approved:
      return { label: 'Approved', band: 'green' };
    case STEP_STATUS.Denied:
      return { label: 'Denied', band: 'red' };
    case STEP_STATUS.Returned:
      return { label: 'Returned', band: 'returned' };
    case STEP_STATUS.Forwarded:
      return { label: 'Handled · passed on', band: 'muted' };
    default:
      return { label: 'Waiting', band: 'muted' };
  }
}
