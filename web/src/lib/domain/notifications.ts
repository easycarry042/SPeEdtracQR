import { randomUUID } from 'node:crypto';
import { db, id, toId } from '@/lib/db';
import { forHumans, now } from '@/lib/time';

/**
 * The bell feed, over the same `notifications` table Laravel's database channel
 * wrote to — `data` is a JSON string, `notifiable_type`/`notifiable_id` point at
 * the user, and `read_at` is the unread flag.
 *
 * Keeping the shape means notifications written by the Laravel app still render
 * here, and vice versa, for as long as both run.
 */
const NOTIFIABLE_TYPE = 'App\\Models\\User';

/** Matches `App\Notifications\DocumentEvent`'s payload. */
export type NotificationData = {
  title: string;
  body?: string | null;
  /** Where the bell row links to. */
  url?: string | null;
  tracking_number?: string | null;
  event?: string | null;
};

export type BellRow = {
  id: string;
  title: string;
  body: string | null;
  url: string | null;
  time: string;
  read: boolean;
};

export async function bellFor(userId: number, limit = 10): Promise<BellRow[]> {
  const rows = await db.notifications.findMany({
    where: { notifiable_type: NOTIFIABLE_TYPE, notifiable_id: id(userId) },
    orderBy: { created_at: 'desc' },
    take: limit,
  });

  return rows.map((row) => {
    let data: NotificationData;

    try {
      data = JSON.parse(row.data) as NotificationData;
    } catch {
      // A malformed payload must not blank the whole bell.
      data = { title: 'Notification' };
    }

    return {
      id: row.id,
      title: data.title ?? 'Notification',
      body: data.body ?? null,
      url: data.url ?? null,
      time: row.created_at ? forHumans(row.created_at) : '',
      read: row.read_at !== null,
    };
  });
}

export function unreadCountFor(userId: number): Promise<number> {
  return db.notifications.count({
    where: { notifiable_type: NOTIFIABLE_TYPE, notifiable_id: id(userId), read_at: null },
  });
}

export async function markAllRead(userId: number): Promise<void> {
  await db.notifications.updateMany({
    where: { notifiable_type: NOTIFIABLE_TYPE, notifiable_id: id(userId), read_at: null },
    data: { read_at: now() },
  });
}

/**
 * Marks one notification read and returns where it points, so the bell row can
 * open the thing it is about. Null when it is not this user's notification —
 * the id is a UUID, but the ownership check is what actually gates it.
 */
export async function markRead(
  notificationId: string,
  userId: number,
): Promise<{ url: string | null } | null> {
  const row = await db.notifications.findFirst({
    where: { id: notificationId, notifiable_type: NOTIFIABLE_TYPE, notifiable_id: id(userId) },
    select: { id: true, data: true, read_at: true },
  });

  if (!row) {
    return null;
  }

  if (row.read_at === null) {
    await db.notifications.update({ where: { id: row.id }, data: { read_at: now() } });
  }

  try {
    return { url: (JSON.parse(row.data) as NotificationData).url ?? null };
  } catch {
    return { url: null };
  }
}

/**
 * Sends a notification to one user. Written in Laravel's own row shape so the
 * PHP side can still read it.
 */
export async function notify(userId: number, data: NotificationData, type = 'App\\Notifications\\DocumentEvent'): Promise<void> {
  await db.notifications.create({
    data: {
      id: randomUUID(),
      type,
      notifiable_type: NOTIFIABLE_TYPE,
      notifiable_id: id(userId),
      data: JSON.stringify(data),
      read_at: null,
      created_at: now(),
      updated_at: now(),
    },
  });
}

/** Fan-out helper — de-duplicates so one person is never belled twice. */
export async function notifyMany(userIds: number[], data: NotificationData): Promise<void> {
  const unique = [...new Set(userIds.filter((value) => Number.isInteger(value)))];

  await Promise.all(unique.map((userId) => notify(userId, data)));
}

/**
 * The path and query of a stored bell URL. Laravel wrote absolute URLs
 * (`route()`), so rows copied from the old database carry the old host; and a
 * same-origin path can never become an open redirect.
 */
export function followablePath(stored: string | null): string | null {
  if (!stored) {
    return null;
  }

  try {
    const url = new URL(stored, 'http://local.invalid');

    return url.pathname.startsWith('/') && !url.pathname.startsWith('//') ? `${url.pathname}${url.search}` : null;
  } catch {
    return null;
  }
}

export { toId };
