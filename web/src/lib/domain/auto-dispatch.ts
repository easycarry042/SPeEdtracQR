import { db, id, toId } from '@/lib/db';
import { activeValues } from '@/lib/domain/document-status';

export type AutoDispatchResult = {
  id: number;
  name: string;
  email: string | null;
} | null;

/**
 * Resolves which staff member should be auto-assigned a new citizen request
 * based on the request type's configured handlers (request_type_handlers).
 *
 * Implements the "Least-Loaded" rotation rule:
 * - Handlers must be active users and not deleted.
 * - If 0 handlers are configured, returns null (request lands in Department Pool).
 * - If 1 handler is configured, returns that handler.
 * - If multiple handlers are configured, counts currently active documents
 *   (Pending, InProgress, OnHold, etc.) assigned to each candidate, and assigns
 *   to the handler with the lowest active workload.
 * - In case of ties, breaks tie by handler sort_order (and id).
 */
export async function resolveAutoDispatch(
  requestTypeId: bigint | number | null | undefined,
): Promise<AutoDispatchResult> {
  if (!requestTypeId) {
    return null;
  }

  const handlers = await db.request_type_handlers.findMany({
    where: { request_type_id: id(requestTypeId) },
    orderBy: [{ sort_order: 'asc' }, { id: 'asc' }],
    include: {
      user: {
        select: {
          id: true,
          name: true,
          email: true,
          is_active: true,
          deleted_at: true,
        },
      },
    },
  });

  const activeHandlers = handlers.filter(
    (h) => h.user && h.user.is_active && h.user.deleted_at === null,
  );

  if (activeHandlers.length === 0) {
    return null;
  }

  if (activeHandlers.length === 1) {
    const user = activeHandlers[0].user;
    return { id: toId(user.id), name: user.name, email: user.email };
  }

  // Multiple candidates: calculate workload of active (non-terminal) requests
  const candidateIds = activeHandlers.map((h) => h.user.id);
  const counts = await db.documents.groupBy({
    by: ['assigned_to'],
    where: {
      assigned_to: { in: candidateIds },
      status: { in: activeValues() },
      deleted_at: null,
    },
    _count: { assigned_to: true },
  });

  const loadMap = new Map<number, number>();
  for (const row of counts) {
    if (row.assigned_to !== null) {
      loadMap.set(toId(row.assigned_to), row._count.assigned_to);
    }
  }

  // Sort candidate handlers by workload ascending, preserving sort_order as tie-breaker
  const sorted = [...activeHandlers].sort((a, b) => {
    const loadA = loadMap.get(toId(a.user.id)) ?? 0;
    const loadB = loadMap.get(toId(b.user.id)) ?? 0;
    if (loadA !== loadB) {
      return loadA - loadB;
    }
    return a.sort_order - b.sort_order;
  });

  const selected = sorted[0].user;
  return {
    id: toId(selected.id),
    name: selected.name,
    email: selected.email,
  };
}
