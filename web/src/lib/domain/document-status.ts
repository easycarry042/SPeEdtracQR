/**
 * Single source of truth for a document's lifecycle (port of
 * `App\Enums\DocumentStatus`).
 *
 * Documents are advanced MANUALLY by their assigned staff member (and admins)
 * through the linear `FLOW` below. `Returned`, `OnHold` are side states; the
 * terminal ones end the line.
 *
 * To rename a stage: edit its `label()` entry — that is the only change needed.
 * To re-order / add / remove a linear stage: edit `FLOW`. Adding a value needs
 * no migration (`documents.status` is a varchar).
 */
export enum DocumentStatus {
  Pending = 'pending',
  InProgress = 'in_progress',
  InReview = 'in_review',
  Approved = 'approved',
  Completed = 'completed',
  Returned = 'returned', // side state — off the linear flow
  OnHold = 'on_hold', // side state — blocked/waiting; SLA paused
  Denied = 'denied', // terminal — supervisor rejected the request
  Withdrawn = 'withdrawn', // terminal — the filing office closed a returned internal request
}

export const DEFAULT_SLA_HOURS = 48;

/** The ordered forward line shown in the stepper. Excludes the side states. */
export const FLOW: readonly DocumentStatus[] = [
  DocumentStatus.Pending,
  DocumentStatus.InProgress,
  DocumentStatus.InReview,
  DocumentStatus.Approved,
  DocumentStatus.Completed,
];

const TERMINAL: readonly DocumentStatus[] = [
  DocumentStatus.Completed,
  DocumentStatus.Denied,
  DocumentStatus.Withdrawn,
];

/** EDIT LABELS HERE. */
const LABELS: Record<DocumentStatus, string> = {
  [DocumentStatus.Pending]: 'Pending',
  [DocumentStatus.InProgress]: 'In Progress',
  [DocumentStatus.InReview]: 'In Review',
  [DocumentStatus.Approved]: 'Approved',
  [DocumentStatus.Completed]: 'Completed',
  [DocumentStatus.Returned]: 'Returned / For Revision',
  [DocumentStatus.OnHold]: 'On Hold',
  [DocumentStatus.Denied]: 'Denied',
  [DocumentStatus.Withdrawn]: 'Withdrawn',
};

/** Plain-language, citizen-facing explanation of what each status means. */
const DESCRIPTIONS: Record<DocumentStatus, string> = {
  [DocumentStatus.Pending]: 'We received your request and it is waiting to be picked up by staff.',
  [DocumentStatus.InProgress]: 'A staff member is actively working on your request.',
  [DocumentStatus.InReview]: 'Your request is being checked and reviewed before approval.',
  [DocumentStatus.Approved]: 'Your request has been approved and is being finalized.',
  [DocumentStatus.Completed]: 'Your document is finished and ready. Watch for pickup or release details.',
  [DocumentStatus.Returned]: 'Something needs to be corrected. Please check for a message from staff.',
  [DocumentStatus.OnHold]: 'Your request is paused, usually waiting on a requirement. Staff will resume it.',
  [DocumentStatus.Denied]: 'Your request could not be approved. Staff should have included a reason.',
  [DocumentStatus.Withdrawn]: 'The office that filed this request closed it.',
};

/**
 * Per-stage SLA budget in hours, anchored to `status_changed_at`.
 *
 * Terminal and off-line stages have no SLA. On Hold PAUSES the clock — a null
 * budget means `isOverdue()` is always false and the hourly SLA sweep skips it.
 */
const SLA_HOURS: Record<DocumentStatus, number | null> = {
  [DocumentStatus.Pending]: 24,
  [DocumentStatus.InProgress]: 72,
  [DocumentStatus.InReview]: 48,
  [DocumentStatus.Approved]: 24,
  [DocumentStatus.Completed]: null,
  [DocumentStatus.Returned]: null,
  [DocumentStatus.OnHold]: null,
  [DocumentStatus.Denied]: null,
  [DocumentStatus.Withdrawn]: null,
};

/**
 * Civic Record colour band for the stepper / badge.
 * green = done/active · brass = in progress/review · muted = not reached ·
 * warn = returned/denied · hold = blocked/waiting (amber).
 */
const BANDS: Record<DocumentStatus, string> = {
  [DocumentStatus.Completed]: 'green',
  [DocumentStatus.Approved]: 'green',
  [DocumentStatus.InProgress]: 'brass',
  [DocumentStatus.InReview]: 'brass',
  [DocumentStatus.Returned]: 'warn',
  [DocumentStatus.Denied]: 'warn',
  [DocumentStatus.OnHold]: 'hold',
  [DocumentStatus.Pending]: 'muted',
  [DocumentStatus.Withdrawn]: 'muted',
};

/**
 * The four stages the staff progress bar shows, each with the statuses that sit
 * on it. The flow has five statuses; In Progress and In Review share the review
 * stage. Side states are placed separately — see `railStage()`.
 */
export const RAIL_STAGES: ReadonlyArray<{ label: string; statuses: DocumentStatus[] }> = [
  { label: 'Pending', statuses: [DocumentStatus.Pending] },
  { label: 'In review', statuses: [DocumentStatus.InProgress, DocumentStatus.InReview] },
  { label: 'Processing', statuses: [DocumentStatus.Approved] },
  { label: 'Completed', statuses: [DocumentStatus.Completed] },
];

export function label(status: DocumentStatus): string {
  return LABELS[status];
}

export function description(status: DocumentStatus): string {
  return DESCRIPTIONS[status];
}

export function slaHours(status: DocumentStatus): number | null {
  return SLA_HOURS[status];
}

export function band(status: DocumentStatus): string {
  return BANDS[status];
}

/** Stages where the document is finished and the SLA clock stops. */
export function isTerminal(status: DocumentStatus): boolean {
  return TERMINAL.includes(status);
}

/** All stored status values. */
export function values(): DocumentStatus[] {
  return Object.values(DocumentStatus);
}

/**
 * The stages a citizen request can be in. Withdrawn belongs to internal
 * requests only, so citizen pages and citizen reports leave it out.
 */
export function citizenCases(): DocumentStatus[] {
  return values().filter((status) => status !== DocumentStatus.Withdrawn);
}

/** Open/active stages (everything except the terminal ones). */
export function activeValues(): DocumentStatus[] {
  return values().filter((status) => !TERMINAL.includes(status));
}

/** 1-based position in the forward line (0 for the off-line states). */
export function position(status: DocumentStatus): number {
  const index = FLOW.indexOf(status);
  return index === -1 ? 0 : index + 1;
}

/** Next stage in the forward line, or null at the end / off the line. */
export function next(status: DocumentStatus): DocumentStatus | null {
  const index = FLOW.indexOf(status);
  return index === -1 ? null : (FLOW[index + 1] ?? null);
}

/** Previous stage in the forward line, or null at the start / off the line. */
export function previous(status: DocumentStatus): DocumentStatus | null {
  const index = FLOW.indexOf(status);
  return index <= 0 ? null : FLOW[index - 1];
}

function railStageOrNull(status: DocumentStatus): number | null {
  const index = RAIL_STAGES.findIndex((stage) => stage.statuses.includes(status));
  return index === -1 ? null : index + 1;
}

/**
 * 1-based stage on the four-stage bar. Off-line states show where the request
 * stands: Returned is back in review; On Hold, Denied and Withdrawn sit where
 * the request was when it stopped (`before`, if recorded).
 */
export function railStage(status: DocumentStatus, before: DocumentStatus | null = null): number {
  let onRail: DocumentStatus;

  switch (status) {
    case DocumentStatus.Returned:
      onRail = DocumentStatus.InReview;
      break;
    case DocumentStatus.OnHold:
    case DocumentStatus.Denied:
    case DocumentStatus.Withdrawn:
      onRail = before && railStageOrNull(before) ? before : DocumentStatus.InReview;
      break;
    default:
      onRail = status;
  }

  return railStageOrNull(onRail) ?? 1;
}

/** Tolerant parse: accepts a canonical key, or the legacy `in_transit`. */
export function fromLoose(
  value: DocumentStatus | string | null | undefined,
  fallback: DocumentStatus = DocumentStatus.Pending,
): DocumentStatus {
  if (value === null || value === undefined || value === '') {
    return fallback;
  }

  if (value === 'in_transit') {
    return DocumentStatus.InProgress;
  }

  return values().includes(value as DocumentStatus) ? (value as DocumentStatus) : fallback;
}
