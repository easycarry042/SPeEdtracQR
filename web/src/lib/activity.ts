import { db, id, toId } from '@/lib/db';
import { now } from '@/lib/time';

/**
 * Writes to the same `activity_log` table Spatie's activitylog package owns, so
 * the audit trail is continuous across the rewrite and `ActivityLine` can word
 * rows from either stack.
 *
 * The `subject_type` / `causer_type` strings stay as the Eloquent class names
 * Laravel stored ("App\Models\Document"), because existing rows use them and
 * the audit log queries on them.
 */
export const SUBJECT = {
  Document: 'App\\Models\\Document',
  User: 'App\\Models\\User',
  Resource: 'App\\Models\\Resource',
  Department: 'App\\Models\\Department',
  RequestType: 'App\\Models\\RequestType',
  Booking: 'App\\Models\\Booking',
} as const;

export type SubjectType = (typeof SUBJECT)[keyof typeof SUBJECT];

export type LogEntry = {
  description: string;
  subjectType?: SubjectType;
  subjectId?: number | null;
  causerId?: number | null;
  /** Spatie's `event` column — "created", "updated", or a custom verb. */
  event?: string | null;
  properties?: Record<string, unknown> | null;
  logName?: string;
};

/**
 * Records one audit line. Mirrors Laravel's
 * `activity()->performedOn($x)->causedBy($u)->withProperties([...])->log('…')`.
 */
export async function logActivity(entry: LogEntry): Promise<void> {
  await db.activity_log.create({
    data: {
      log_name: entry.logName ?? 'default',
      description: entry.description,
      subject_type: entry.subjectType ?? null,
      subject_id: entry.subjectId === null || entry.subjectId === undefined ? null : id(entry.subjectId),
      causer_type: entry.causerId === null || entry.causerId === undefined ? null : SUBJECT.User,
      causer_id: entry.causerId === null || entry.causerId === undefined ? null : id(entry.causerId),
      event: entry.event ?? null,
      properties: (entry.properties ?? null) as never,
      created_at: now(),
      updated_at: now(),
    },
  });
}

/** Shorthand for the common "something happened to this document" line. */
export function logOnDocument(
  documentId: number,
  description: string,
  causerId: number | null = null,
  properties: Record<string, unknown> | null = null,
  event: string | null = null,
): Promise<void> {
  return logActivity({
    description,
    subjectType: SUBJECT.Document,
    subjectId: documentId,
    causerId,
    properties,
    event,
  });
}

export function logOnResource(
  resourceId: number,
  description: string,
  causerId: number | null = null,
): Promise<void> {
  return logActivity({
    description,
    subjectType: SUBJECT.Resource,
    subjectId: resourceId,
    causerId,
  });
}

/**
 * The fields `Document::getActivitylogOptions()` logged, and only those — a
 * save that touched none of them wrote an empty "updated" row, which the
 * Laravel side suppressed with `dontSubmitEmptyLogs()`.
 */
const LOGGED_DOCUMENT_ATTRIBUTES = [
  'tracking_number',
  'status',
  'assigned_to',
  'citizen_name',
  'citizen_contact',
  'document_type',
  'purpose',
  'description',
  'remarks',
  'hold_reason',
  'hold_until',
  'blocked_by',
  'status_before_hold',
] as const;

/**
 * Logs a document update the way `LogsActivity` did: only the watched
 * attributes, only the ones that actually changed, and nothing at all when the
 * change touched none of them.
 */
export async function logDocumentUpdate(
  documentId: number,
  before: Record<string, unknown>,
  after: Record<string, unknown>,
  causerId: number | null = null,
): Promise<void> {
  const attributes: Record<string, unknown> = {};
  const old: Record<string, unknown> = {};

  for (const field of LOGGED_DOCUMENT_ATTRIBUTES) {
    if (!(field in after)) {
      continue;
    }

    const next = after[field] ?? null;
    const previous = before[field] ?? null;

    // Dates compare by value, not identity.
    const changed =
      next instanceof Date && previous instanceof Date
        ? next.getTime() !== previous.getTime()
        : next !== previous;

    if (changed) {
      attributes[field] = next instanceof Date ? next.toISOString() : next;
      old[field] = previous instanceof Date ? previous.toISOString() : previous;
    }
  }

  if (Object.keys(attributes).length === 0) {
    return;
  }

  await logActivity({
    description: 'updated',
    subjectType: SUBJECT.Document,
    subjectId: documentId,
    causerId,
    event: 'updated',
    properties: { attributes, old },
  });
}

export type ActivityRow = {
  id: number;
  description: string;
  event: string | null;
  subjectType: string | null;
  subjectId: number | null;
  causerId: number | null;
  causerName: string | null;
  properties: Record<string, unknown> | null;
  createdAt: Date | null;
};

/** Reads the trail for one subject, newest first. */
export async function activityFor(
  subjectType: SubjectType,
  subjectId: number,
  limit = 50,
): Promise<ActivityRow[]> {
  const rows = await db.activity_log.findMany({
    where: { subject_type: subjectType, subject_id: id(subjectId) },
    orderBy: { id: 'desc' },
    take: limit,
  });

  return hydrateActivity(rows);
}

type RawActivity = {
  id: bigint;
  description: string;
  event: string | null;
  subject_type: string | null;
  subject_id: bigint | null;
  causer_id: bigint | null;
  properties: unknown;
  created_at: Date | null;
};

export async function hydrateActivity(rows: RawActivity[]): Promise<ActivityRow[]> {
  const causerIds = [...new Set(rows.map((row) => row.causer_id).filter((v): v is bigint => v !== null))];

  const causers =
    causerIds.length === 0
      ? []
      : await db.users.findMany({
          where: { id: { in: causerIds } },
          select: { id: true, name: true },
        });

  const names = new Map(causers.map((user) => [toId(user.id), user.name]));

  return rows.map((row) => ({
    id: toId(row.id),
    description: row.description,
    event: row.event,
    subjectType: row.subject_type,
    subjectId: row.subject_id === null ? null : toId(row.subject_id),
    causerId: row.causer_id === null ? null : toId(row.causer_id),
    causerName: row.causer_id === null ? null : (names.get(toId(row.causer_id)) ?? null),
    properties: (row.properties ?? null) as Record<string, unknown> | null,
    createdAt: row.created_at,
  }));
}
