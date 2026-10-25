import { db, id, toId } from '@/lib/db';

export type AutoDispatchResult = {
  id: number;
  name: string;
  email: string | null;
} | null;

/**
 * Resolves which staff member should be auto-assigned a new citizen request
 * based on the request type's configured handlers (request_type_handlers).
 *
 * Implements the Structural Layer: Multi-Handler Rotation & Pool Fallback:
 *
 * 1. Round-Robin Among Type Handlers:
 *    If a request type has multiple assigned handlers (e.g. 3 staff members),
 *    routes requests sequentially among handlers who are marked active and present
 *    (is_active = true and is_available = true).
 *
 * 2. Empty Roster Fallback:
 *    If all pre-configured handlers for that request type are marked absent or
 *    unavailable (or none configured), defaults to null (assigned_to = null) and
 *    routes the document straight into the department's Unassigned / Open Pool
 *    so any staff member on duty can manually claim it.
 */
export async function resolveAutoDispatch(
  requestTypeId: bigint | number | null | undefined,
  excludeUserIds: number[] = [],
): Promise<AutoDispatchResult> {
  if (!requestTypeId) {
    return null;
  }

  const handlers = await db.request_type_handlers.findMany({
    where: { request_type_id: id(requestTypeId) },
    orderBy: [{ sort_order: 'asc' }, { id: 'asc' }],
    include: {
      requestType: {
        select: {
          id: true,
          name: true,
        },
      },
      user: {
        select: {
          id: true,
          name: true,
          email: true,
          is_active: true,
          is_available: true,
          deleted_at: true,
        },
      },
    },
  });

  const excluded = new Set(excludeUserIds.map((v) => Number(v)));

  const activeHandlers = handlers.filter(
    (h) =>
      h.user &&
      h.user.is_active &&
      h.user.is_available &&
      h.user.deleted_at === null &&
      !excluded.has(toId(h.user.id)),
  );

  // Empty Roster Fallback: all handlers absent or unconfigured -> Department Open Pool
  if (activeHandlers.length === 0) {
    return null;
  }

  if (activeHandlers.length === 1) {
    const user = activeHandlers[0].user;
    return { id: toId(user.id), name: user.name, email: user.email };
  }

  // Round-Robin: Multiple active & present handlers. Determine last assigned
  // handler for this request type and pick the next in sequence.
  let selected = activeHandlers[0].user;

  const typeName =
    handlers[0]?.requestType?.name ??
    (
      await db.request_types
        .findUnique({
          where: { id: id(requestTypeId) },
          select: { name: true },
        })
        .catch(() => null)
    )?.name ??
    null;

  if (typeName) {
    const lastDoc = await db.documents.findFirst({
      where: {
        document_type: typeName,
        assigned_to: { not: null },
        deleted_at: null,
      },
      orderBy: { id: 'desc' },
      select: { assigned_to: true },
    });

    if (lastDoc?.assigned_to !== null && lastDoc?.assigned_to !== undefined) {
      const lastAssignedId = toId(lastDoc.assigned_to);
      const lastIndex = activeHandlers.findIndex(
        (h) => toId(h.user.id) === lastAssignedId,
      );

      if (lastIndex !== -1) {
        const nextIndex = (lastIndex + 1) % activeHandlers.length;
        selected = activeHandlers[nextIndex].user;
      }
    }
  }

  return {
    id: toId(selected.id),
    name: selected.name,
    email: selected.email,
  };
}
