import type { Prisma } from '@prisma/client';
import { id } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';
import { ROLE_SUPERVISOR, ROLE_SUPER_ADMIN } from '@/lib/auth/rbac';

/** Citizen-facing ticket (guest/staff created) — the original flow. */
export const ORIGIN_EXTERNAL = 'external';

/** Dept-to-dept request (supervisor created) travelling an endorsement chain. */
export const ORIGIN_INTERNAL = 'internal';

/**
 * Citizen requests only. Origin is the one line between the two kinds.
 *
 * Laravel also tolerated a NULL origin for rows predating the column; the
 * column is NOT NULL with a default in the live schema, so that branch is
 * unreachable and is left out here.
 */
export const citizenFacing: Prisma.documentsWhereInput = {
  origin: { not: ORIGIN_INTERNAL },
};

/** Office-to-office requests only. */
export const internalOnly: Prisma.documentsWhereInput = {
  origin: ORIGIN_INTERNAL,
};

/**
 * Internal requests one office has a hand in: filed by it, or routed through it
 * at any hop. An internal request has no `department_id` of its own, so this —
 * not `department_id` — is what "this office's" means.
 */
export function involvingOffice(departmentId: number): Prisma.documentsWhereInput {
  return {
    origin: ORIGIN_INTERNAL,
    OR: [
      { requesting_department_id: id(departmentId) },
      { request_steps: { some: { department_id: id(departmentId) } } },
    ],
  };
}

/** Port of `AssignmentScope::canViewAll`. */
export function canViewAll(actor: Actor | null): boolean {
  if (!actor) {
    return false;
  }

  return actor.can('manage system') || actor.hasRole(ROLE_SUPER_ADMIN) || actor.hasRole(ROLE_SUPERVISOR);
}

export function canViewUnclaimedQueue(actor: Actor | null): boolean {
  if (!actor) {
    return false;
  }

  return canViewAll(actor) || actor.can('accept documents');
}

/** A `where` fragment that matches nothing — the guest case. */
const MATCH_NOTHING: Prisma.documentsWhereInput = { id: { lt: 0 } };

/**
 * Which documents this user may read (port of
 * `AssignmentScope::applyDocumentScope`). Compose it into a query with
 * `AND: [documentScope(actor), ...]`.
 */
export function documentScope(actor: Actor | null): Prisma.documentsWhereInput {
  if (!actor) {
    return MATCH_NOTHING;
  }

  // Org-wide means org-wide: only a super admin reads every office's work.
  // `canViewAll()` is deliberately NOT used here — it answers "may this user see
  // documents they were not assigned", which a supervisor may, and returning an
  // unscoped query for them leaked every other office's documents into their
  // Look Up, History and Reports.
  if (actor.can('manage system')) {
    return {};
  }

  if (actor.hasRole(ROLE_SUPERVISOR)) {
    // A supervisor who heads no particular office is not scoped to one; scoping
    // them to nothing would blank out their History and Reports instead.
    if (actor.departmentId === null) {
      return {};
    }

    const departmentId = id(actor.departmentId);

    return {
      OR: [
        { department_id: departmentId },
        // Unrouted citizen intake is every triager's to pick up.
        { department_id: null, origin: { not: ORIGIN_INTERNAL } },
        // Internal requests carry no department_id, so "this office's" means
        // filed by it or passed through it — not simply "has no office".
        {
          origin: ORIGIN_INTERNAL,
          OR: [
            { requesting_department_id: departmentId },
            { request_steps: { some: { department_id: departmentId } } },
          ],
        },
      ],
    };
  }

  return {
    OR: [{ assigned_to: id(actor.id) }, { created_by: id(actor.id) }],
  };
}

/**
 * Enforces the Guest → Supervisor → Staff flow: a supervisor may only assign a
 * ticket to staff in their OWN office. Super admins are org-wide.
 */
export function canAssignWithinDepartment(
  actor: Actor,
  assignee: { department_id: number | null },
): boolean {
  if (actor.can('manage system')) {
    return true;
  }

  return actor.departmentId !== null && assignee.department_id === actor.departmentId;
}

/**
 * Who may EDIT a document's details. Stricter than view access: supervisors and
 * admins may correct any record, but a staff member may only edit the ticket
 * currently assigned to them — never unclaimed or others' tickets.
 */
export function canEditDocument(
  document: { assigned_to: number | null },
  actor: Actor | null,
): boolean {
  if (!actor) {
    return false;
  }

  return canViewAll(actor) || document.assigned_to === actor.id;
}

export function canAccessDocument(
  document: { created_by: number | null; assigned_to: number | null; origin?: string | null },
  actor: Actor | null,
): boolean {
  if (!actor) {
    return false;
  }

  if (document.origin === ORIGIN_INTERNAL) {
    return true;
  }

  if (canViewAll(actor)) {
    return true;
  }

  if (document.created_by === actor.id || document.assigned_to === actor.id) {
    return true;
  }

  return document.assigned_to === null && canViewUnclaimedQueue(actor);
}
