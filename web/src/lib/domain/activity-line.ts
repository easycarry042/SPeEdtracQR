import { db, id, toId } from '@/lib/db';
import type { ActivityRow } from '@/lib/activity';
import { SUBJECT } from '@/lib/activity';
import { fromLoose, label as statusLabel } from '@/lib/domain/document-status';

/**
 * Port of `App\Support\ActivityLine` — one activity-log row in words, shared
 * by the command center's Recent Activity strip and the Audit Log page so the
 * two never describe the same change differently.
 *
 * Laravel's audit log printed "updated (Document #19)": the database id rather
 * than the tracking number anyone would search for, and nothing on what
 * changed.
 */
export type Line = { action: string; detail: string | null; subject: string | null };

export type DescribedRow = ActivityRow & Line;

function changedAttributes(row: ActivityRow): Record<string, unknown> {
  const attributes = row.properties?.attributes;

  return attributes !== null && typeof attributes === 'object' && !Array.isArray(attributes)
    ? (attributes as Record<string, unknown>)
    : {};
}

/**
 * A model save that changed nothing worth logging. Documents no longer write
 * them, but older rows remain and were noise.
 */
export function isEmptyUpdate(row: ActivityRow): boolean {
  return row.event === 'updated' && Object.keys(changedAttributes(row)).length === 0;
}

function ucfirst(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

export function describe(
  row: ActivityRow,
  lookups: { userNames: Map<number, string>; subjects: Map<string, string | null> },
): Line {
  const changed = changedAttributes(row);
  let action: string;
  let detail: string | null = null;

  if (row.event === 'created') {
    action = 'Request Created';
    detail = typeof changed.document_type === 'string' ? changed.document_type : null;
  } else if ('status' in changed) {
    action = 'Status Updated';
    detail = `→ ${statusLabel(fromLoose(String(changed.status ?? '')))}`;
  } else if ('assigned_to' in changed) {
    action = 'Staff Assigned';
    detail = changed.assigned_to
      ? `→ ${lookups.userNames.get(Number(changed.assigned_to)) ?? 'Unknown'}`
      : '→ Unassigned';
  } else if (row.event === 'updated' && Object.keys(changed).length > 0) {
    action = 'Details Edited';
    detail = Object.keys(changed)
      .map((field) => field.replace(/_/g, ' '))
      .join(', ');
  } else {
    action = ucfirst(row.description || row.event || 'Change');
  }

  const subject =
    row.subjectType && row.subjectId !== null
      ? (lookups.subjects.get(`${row.subjectType}#${row.subjectId}`) ?? null)
      : null;

  return { action, detail, subject };
}

/** What a subject is called by a person: a tracking number, "Resource: Chairs". */
async function subjectLabels(rows: ActivityRow[]): Promise<Map<string, string | null>> {
  const byType = new Map<string, number[]>();

  for (const row of rows) {
    if (row.subjectType && row.subjectId !== null && row.subjectType !== SUBJECT.User) {
      byType.set(row.subjectType, [...(byType.get(row.subjectType) ?? []), row.subjectId]);
    }
  }

  const labels = new Map<string, string | null>();
  const ids = (type: string) => [...new Set(byType.get(type) ?? [])].map((value) => id(value));

  const lookups: Array<Promise<void>> = [];

  if (byType.has(SUBJECT.Document)) {
    lookups.push(
      db.documents
        .findMany({ where: { id: { in: ids(SUBJECT.Document) } }, select: { id: true, tracking_number: true } })
        .then((found) =>
          found.forEach((row) => labels.set(`${SUBJECT.Document}#${toId(row.id)}`, row.tracking_number)),
        ),
    );
  }

  const named: Array<[string, string, (where: { id: { in: bigint[] } }) => Promise<Array<{ id: bigint; name: string }>>]> = [
    [SUBJECT.Resource, 'Resource', (where) => db.resources.findMany({ where, select: { id: true, name: true } })],
    [SUBJECT.Department, 'Department', (where) => db.departments.findMany({ where, select: { id: true, name: true } })],
    [SUBJECT.RequestType, 'RequestType', (where) => db.request_types.findMany({ where, select: { id: true, name: true } })],
  ];

  for (const [type, base, find] of named) {
    if (byType.has(type)) {
      lookups.push(
        find({ id: { in: ids(type) } }).then((found) =>
          found.forEach((row) => labels.set(`${type}#${toId(row.id)}`, `${base}: ${row.name}`)),
        ),
      );
    }
  }

  await Promise.all(lookups);

  // Anything else (a booking, a deleted row) is named by its class alone.
  for (const [type, list] of byType) {
    for (const value of list) {
      const key = `${type}#${value}`;

      if (!labels.has(key)) {
        labels.set(key, type.split('\\').pop() ?? null);
      }
    }
  }

  return labels;
}

/** Word a page of rows, with the names and labels they need loaded in two queries. */
export async function describeRows(rows: ActivityRow[]): Promise<DescribedRow[]> {
  const assigneeIds = [
    ...new Set(
      rows
        .map((row) => changedAttributes(row).assigned_to)
        .filter((value): value is number | string => value !== null && value !== undefined && value !== '')
        .map((value) => Number(value))
        .filter((value) => Number.isInteger(value) && value > 0),
    ),
  ];

  const [users, subjects] = await Promise.all([
    assigneeIds.length === 0
      ? Promise.resolve([])
      : db.users.findMany({ where: { id: { in: assigneeIds.map((value) => id(value)) } }, select: { id: true, name: true } }),
    subjectLabels(rows),
  ]);

  const userNames = new Map(users.map((user) => [toId(user.id), user.name]));

  return rows.map((row) => ({ ...row, ...describe(row, { userNames, subjects }) }));
}
