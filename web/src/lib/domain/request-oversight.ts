import { DocumentStatus } from '@/lib/domain/document-status';
import type { ReviewRow } from '@/lib/domain/request-review';

/**
 * The queues on the super admin's Requests page: every citizen request in the
 * city, sorted into the five states someone watching the whole city looks for.
 *
 * Port of the Blade page's Alpine state (`admin/requests/index`). Deliberately
 * free of any database import: the page runs these over one payload on the
 * client, which is the only way the five tab counts can agree with each other.
 */
export const OVERSIGHT_QUEUES = [
  { key: 'open', label: 'Open' },
  { key: 'unassigned', label: 'Unassigned' },
  { key: 'overdue', label: 'Overdue' },
  { key: 'completed', label: 'Completed' },
  { key: 'denied', label: 'Denied' },
] as const;

export type OversightQueue = (typeof OVERSIGHT_QUEUES)[number]['key'];

export function isClosed(row: Pick<ReviewRow, 'status'>): boolean {
  return (
    row.status === DocumentStatus.Completed ||
    row.status === DocumentStatus.Denied ||
    row.status === DocumentStatus.Withdrawn
  );
}

/** Which of the five tabs a request belongs to (it can be in several). */
export function inQueue(
  row: Pick<ReviewRow, 'status' | 'assignedTo' | 'overSla'>,
  queue: OversightQueue,
): boolean {
  switch (queue) {
    case 'open':
      return !isClosed(row);
    case 'unassigned':
      return !isClosed(row) && row.assignedTo === null;
    case 'overdue':
      return row.overSla !== null;
    case 'completed':
      return row.status === DocumentStatus.Completed;
    case 'denied':
      return row.status === DocumentStatus.Denied;
  }
}

/**
 * Free-text match across the columns the table actually shows. Searching a
 * field that is not on screen reads as the filter being broken.
 */
export function matchesSearch(
  row: Pick<
    ReviewRow,
    'citizenName' | 'trackingNumber' | 'documentType' | 'department' | 'assignedStaff'
  >,
  needle: string,
): boolean {
  const term = needle.trim().toLowerCase();

  if (term === '') {
    return true;
  }

  return [
    row.citizenName,
    row.trackingNumber,
    row.documentType,
    row.department,
    row.assignedStaff,
  ].some((field) => (field ?? '').toLowerCase().includes(term));
}
