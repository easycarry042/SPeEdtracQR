import { db, id, toId } from '@/lib/db';
import { DocumentStatus, isTerminal, slaHours } from '@/lib/domain/document-status';
import { holdOverdueDays, isHoldOverdue } from '@/lib/domain/document';
import { ROLE_SUPERVISOR, ROLE_SUPER_ADMIN, usersWithRole } from '@/lib/auth/rbac';
import { notify, notifyMany } from '@/lib/domain/notifications';
import { logOnDocument } from '@/lib/activity';
import { logSystemComment } from '@/lib/domain/comments';
import { broadcastStatusUpdated } from '@/lib/realtime';
import { routes } from '@/lib/routes';
import { resolveAutoDispatch, type AutoDispatchResult } from '@/lib/domain/auto-dispatch';
import { sendMail } from '@/lib/mail/send';
import { holdOverdueMail, slaBreachMail, slaWarningMail } from '@/lib/mail/sla-alerts';
import * as lendingDesk from '@/lib/domain/lending-desk';
import { tracking } from '@/lib/config';
import { addHours, diffInDays, diffInHours, now } from '@/lib/time';

/**
 * The three scheduled sweeps, ported from `app/Console/Commands`.
 *
 * Laravel ran these from `schedule:run` on cron. There is no scheduler here, so
 * each one is exposed as an authenticated cron route handler (see
 * `src/app/api/cron/[job]/route.ts`) and the logic lives here, callable from a
 * test without an HTTP request.
 *
 * All three are idempotent: they dedupe through the same columns the PHP
 * versions used, so calling one twice in an hour sends nothing twice.
 */
export type SweepResult = { job: string; summary: string; counts: Record<string, number> };

/** Fraction of the stage SLA at which a warning is sent. */
const WARNING_RATIO = 0.75;

/**
 * SLA is measured per status STAGE (time since `status_changed_at` vs
 * `slaHours()`), and the warning/breach emails go to the document's ASSIGNED
 * staff member — not a department mailbox.
 */
export async function checkDocumentSla(): Promise<SweepResult> {
  const documents = await db.documents.findMany({
    where: { assigned_to: { not: null }, status_changed_at: { not: null }, deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      citizen_name: true,
      status: true,
      status_changed_at: true,
      sla_warning_notified_at: true,
      sla_breach_notified_at: true,
      sla_breached_at: true,
      assignee: { select: { email: true } },
    },
  });

  let warned = 0;
  let breached = 0;

  for (const document of documents) {
    const stage = document.status as DocumentStatus;
    const budget = slaHours(stage);

    // Terminal / no-budget stages never breach. On Hold PAUSES the clock, so it
    // is skipped here by design — `checkDocumentHolds` is its backstop.
    if (budget === null || isTerminal(stage)) {
      continue;
    }

    const email = document.assignee?.email;

    if (!email) {
      continue;
    }

    const elapsed = diffInHours(document.status_changed_at!);
    const alert = {
      id: toId(document.id),
      tracking_number: document.tracking_number,
      document_type: document.document_type,
      citizen_name: document.citizen_name,
      status: document.status,
    };

    if (elapsed >= budget && !document.sla_breach_notified_at) {
      await sendMail(slaBreachMail({ to: email, document: alert }));
      await db.documents.update({
        where: { id: document.id },
        data: {
          sla_breach_notified_at: now(),
          // Sticky lifetime marker for the staff SLA-rate KPI (set once).
          sla_breached_at: document.sla_breached_at ?? now(),
        },
      });
      breached += 1;
    } else if (
      elapsed >= budget * WARNING_RATIO &&
      !document.sla_warning_notified_at &&
      !document.sla_breach_notified_at
    ) {
      await sendMail(slaWarningMail({ to: email, document: alert }));
      await db.documents.update({
        where: { id: document.id },
        data: { sla_warning_notified_at: now() },
      });
      warned += 1;
    }
  }

  return {
    job: 'documents:check-sla',
    summary: `SLA sweep complete: ${warned} warning(s), ${breached} breach(es).`,
    counts: {
      warned,
      breached,
      scanned: documents.length,
    },
  };
}

/**
 * The backstop for paused SLA clocks.
 *
 * A hold pauses the SLA deliberately, which means a held document answers to
 * nothing. This gives every hold a second deadline: `hold_until` if staff chose
 * one, otherwise `tracking.holds.staleAfterDays`. The assignee is emailed and
 * the handling office's supervisor copied, so a stall becomes visible to someone
 * other than whoever parked it.
 */
export async function checkDocumentHolds(): Promise<SweepResult> {
  if (!tracking.holds.enabled) {
    return {
      job: 'documents:check-holds',
      summary: 'Hold sweep disabled (tracking.holds.enabled).',
      counts: {},
    };
  }

  const documents = await db.documents.findMany({
    where: { status: DocumentStatus.OnHold, deleted_at: null },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      citizen_name: true,
      status: true,
      hold_until: true,
      held_at: true,
      hold_reminder_sent_at: true,
      blocked_by: true,
      department_id: true,
      assigned_to: true,
      assignee: { select: { email: true, department_id: true } },
    },
  });

  const remindEvery = Math.max(1, tracking.holds.remindEveryDays);

  let chased = 0;
  let stalled = 0;

  for (const document of documents) {
    if (!isHoldOverdue(document)) {
      continue;
    }

    stalled += 1;

    // Already chased recently? Wait out the re-nag window.
    if (document.hold_reminder_sent_at && diffInDays(document.hold_reminder_sent_at) < remindEvery) {
      continue;
    }

    const days = holdOverdueDays(document);
    const assigneeEmail = document.assignee?.email ?? null;
    const supervisorEmails = await holdEscalationEmails(document, assigneeEmail);

    if (assigneeEmail === null && supervisorEmails.length === 0) {
      // Nobody to tell — still log it so the audit trail shows the stall was
      // detected and why it went unreported.
      await logOnDocument(
        toId(document.id),
        `Hold overdue by ${days} day(s) — no assignee or supervisor mailbox to notify`,
      );

      continue;
    }

    const alert = {
      id: toId(document.id),
      tracking_number: document.tracking_number,
      document_type: document.document_type,
      citizen_name: document.citizen_name,
      status: document.status,
    };

    if (assigneeEmail) {
      await sendMail(
        holdOverdueMail({
          to: assigneeEmail,
          document: alert,
          daysOverdue: days,
          blockedBy: document.blocked_by,
        }),
      );
    }

    for (const to of supervisorEmails) {
      await sendMail(
        holdOverdueMail({
          to,
          document: alert,
          daysOverdue: days,
          blockedBy: document.blocked_by,
          isEscalation: true,
        }),
      );
    }

    await db.documents.update({
      where: { id: document.id },
      data: { hold_reminder_sent_at: now() },
    });

    await logOnDocument(
      toId(document.id),
      `Hold overdue by ${days} day(s) — chased assignee and supervisor`,
      null,
      { days_overdue: days, blocked_by: document.blocked_by, escalated_to: supervisorEmails },
    );

    chased += 1;
  }

  return {
    job: 'documents:check-holds',
    summary: `Hold sweep complete: ${stalled} stalled hold(s), ${chased} chased.`,
    counts: { stalled, chased, held: documents.length },
  };
}

/**
 * Who hears about a stalled hold, besides the assignee.
 *
 * Over half of real documents carry no `department_id`, so the handling office
 * falls back to the ASSIGNEE's department before anything else. Escalating a
 * weekly automated email to every supervisor in the city would get the whole
 * sweep muted, so with no department anywhere this goes to the super admins only
 * — one authoritative owner instead of a broadcast.
 */
async function holdEscalationEmails(
  document: { department_id: bigint | null; assigned_to: bigint | null; assignee: { department_id: bigint | null } | null },
  assigneeEmail: string | null,
): Promise<string[]> {
  const departmentId = document.department_id ?? document.assignee?.department_id ?? null;

  const people = departmentId
    ? await usersWithRole(ROLE_SUPERVISOR, toId(departmentId))
    : await usersWithRole(ROLE_SUPER_ADMIN);

  const assignedTo = document.assigned_to === null ? null : toId(document.assigned_to);

  return [
    ...new Set(
      people
        .filter((person) => person.id !== assignedTo && person.email !== '' && person.email !== assigneeEmail)
        .map((person) => person.email),
    ),
  ];
}

/**
 * Borrowed items past their return date.
 *
 * A loan only closes when someone records the return, so a late borrower keeps
 * the items "out" — overdue — instead of them quietly rejoining stock. The
 * borrower is emailed and the handling staff (plus their office's supervisors)
 * get a bell, repeating every `tracking.loans.remindEveryDays`.
 */
export async function checkOverdueLoans(): Promise<SweepResult> {
  if (!tracking.loans.enabled) {
    return {
      job: 'inventory:check-overdue',
      summary: 'Overdue loan sweep disabled (tracking.loans.enabled).',
      counts: {},
    };
  }

  const remindEvery = Math.max(1, tracking.loans.remindEveryDays);

  const overdue = await db.bookings.findMany({
    where: {
      status: { not: 'cancelled' },
      returned_at: null,
      // Only a handed-over loan can be overdue: an approved-but-uncollected
      // booking is the office's to chase, not the borrower's.
      released_at: { not: null },
      ends_at: { lt: now() },
    },
    select: {
      id: true,
      ends_at: true,
      overdue_reminded_at: true,
      resource: { select: { name: true } },
      document: { select: { department_id: true, assigned_to: true, tracking_number: true } },
    },
  });

  let chased = 0;

  for (const booking of overdue) {
    if (booking.overdue_reminded_at && diffInDays(booking.overdue_reminded_at) < remindEvery) {
      continue;
    }

    // `remind` owns the citizen email, the activity line and the
    // `overdue_reminded_at` stamp — the same path the Resources page uses.
    await lendingDesk.remind(toId(booking.id));

    const days = Math.max(0, diffInDays(booking.ends_at));

    await notifyMany(await loanStaffIds(booking.document), {
      event: 'loan_overdue',
      title: 'Borrowed items overdue',
      body: `${booking.resource?.name ?? 'Borrowed items'} — ${days} day(s) past the return date`,
      url: '/inventory',
      tracking_number: booking.document.tracking_number,
    });

    chased += 1;
  }

  return {
    job: 'inventory:check-overdue',
    summary: `Overdue loan sweep complete: ${overdue.length} overdue, ${chased} chased.`,
    counts: { overdue: overdue.length, chased },
  };
}

/** The staff member handling the request, and the supervisors of its office. */
async function loanStaffIds(document: {
  department_id: bigint | null;
  assigned_to: bigint | null;
}): Promise<number[]> {
  const ids = document.assigned_to === null ? [] : [toId(document.assigned_to)];

  if (document.department_id !== null) {
    const supervisors = await usersWithRole(ROLE_SUPERVISOR, toId(document.department_id));
    ids.push(...supervisors.map((person) => person.id));
  }

  return [...new Set(ids)];
}

/** Supervisors of a department, falling back to super admins if none exist. */
async function departmentSupervisors(
  departmentId: number | null,
): Promise<Array<{ id: number; name: string; email: string }>> {
  const people = departmentId
    ? await usersWithRole(ROLE_SUPERVISOR, departmentId)
    : await usersWithRole(ROLE_SUPER_ADMIN);

  return people.length > 0 ? people : await usersWithRole(ROLE_SUPER_ADMIN);
}

/**
 * Reactive escalation: Reclaims or auto-escalates unaccepted requests.
 *
 * When a citizen request is assigned to a staff member, it sits in `Pending`
 * until accepted (`accepted_at`). If the assignee is absent or unattended past
 * the acceptance window (default 2 hours), the system auto-escalates:
 * 1. If another active & available handler is configured for the request type
 *    in `request_type_handlers`, reassign to the least-loaded candidate.
 * 2. If no secondary handler is available, strip `assigned_to` and return the
 *    document to the Department Open Pool (`assigned_to: null`).
 *
 * In either case, audit logs and system comments are recorded, real-time status
 * is broadcast, and the department's supervisors are alerted (incrementing
 * their NavBadges count for unassigned pool requests).
 */
export async function reclaimUnacceptedRequests(
  acceptanceWindowHours = 2,
  options: {
    /** Only this staff member's assignments (they just went On Leave). */
    assigneeId?: number;
    /** Why it moved, for the log lines and bells ("is on leave"). */
    why?: string;
  } = {},
): Promise<SweepResult> {
  // Stored times are naive Manila wall-clock (lib/time), so the cutoff is too;
  // `Date.now()` here would sit eight hours early.
  const cutoff = addHours(now(), -acceptanceWindowHours);

  const documents = await db.documents.findMany({
    where: {
      status: DocumentStatus.Pending,
      assigned_to: options.assigneeId === undefined ? { not: null } : id(options.assigneeId),
      accepted_at: null,
      OR: [
        { assigned_at: { lt: cutoff } },
        { assigned_at: null, status_changed_at: { lt: cutoff } },
        { assigned_at: null, status_changed_at: null, created_at: { lt: cutoff } },
      ],
      deleted_at: null,
    },
    select: {
      id: true,
      tracking_number: true,
      document_type: true,
      status: true,
      department_id: true,
      assigned_to: true,
      assigned_at: true,
      assignee: {
        select: {
          id: true,
          name: true,
          email: true,
          department_id: true,
        },
      },
    },
  });

  let reassigned = 0;
  let reclaimedToPool = 0;

  for (const document of documents) {
    if (document.status !== DocumentStatus.Pending || document.assigned_to === null) {
      continue;
    }

    const documentId = toId(document.id);
    const prevAssigneeId = toId(document.assigned_to);
    const prevAssigneeName = document.assignee?.name ?? 'Assigned staff';
    // e.g. "Ana Cruz did not accept it within 2 hours" / "Ana Cruz is on leave"
    const why = `${prevAssigneeName} ${options.why ?? `did not accept it within ${acceptanceWindowHours} hours`}`;
    const departmentId = document.department_id ?? document.assignee?.department_id ?? null;

    let nextAssignee: AutoDispatchResult = null;
    if (document.document_type) {
      try {
        const type = await db.request_types.findFirst({
          where: { name: document.document_type },
          select: { id: true },
        });
        if (type) {
          nextAssignee = await resolveAutoDispatch(type.id, [prevAssigneeId]);
        }
      } catch {
        nextAssignee = null;
      }
    }

    if (nextAssignee) {
      await db.documents.update({
        where: { id: document.id },
        data: {
          assigned_to: id(nextAssignee.id),
          assigned_by: null,
          assigned_at: now(),
          accepted_at: null,
          updated_at: now(),
        },
      });

      await logOnDocument(
        documentId,
        `Auto-escalated to ${nextAssignee.name}: ${why}`,
      );
      await logSystemComment(
        documentId,
        `Reassigned to ${nextAssignee.name} — ${why}.`,
      );

      await notify(nextAssignee.id, {
        title: 'Assigned to you',
        body: `${document.document_type ?? 'Document'} — auto-escalated to you. Accept it on your Requests page.`,
        url: routes.staffDashboard({ open: document.tracking_number }),
        tracking_number: document.tracking_number,
        event: 'assigned',
      });

      const supervisors = await departmentSupervisors(
        departmentId === null ? null : toId(departmentId),
      );
      await notifyMany(
        supervisors.map((s) => s.id),
        {
          title: `Request auto-escalated: ${document.tracking_number}`,
          body: `${document.document_type ?? 'Document'} went to ${nextAssignee.name}: ${why}.`,
          url: routes.dashboard({ tab: 'active', q: document.tracking_number }),
          tracking_number: document.tracking_number,
          event: 'auto_escalated',
        },
      );

      reassigned += 1;
    } else {
      await db.documents.update({
        where: { id: document.id },
        data: {
          assigned_to: null,
          assigned_by: null,
          assigned_at: null,
          accepted_at: null,
          updated_at: now(),
        },
      });

      await logOnDocument(
        documentId,
        `Returned to the office pool: ${why}`,
      );
      await logSystemComment(
        documentId,
        `Returned to the office pool — ${why}.`,
      );

      const supervisors = await departmentSupervisors(
        departmentId === null ? null : toId(departmentId),
      );
      await notifyMany(
        supervisors.map((s) => s.id),
        {
          title: `Back in the pool: ${document.tracking_number}`,
          body: `${document.document_type ?? 'Document'} is waiting in the office pool: ${why}.`,
          url: routes.dashboard({ tab: 'pending', q: document.tracking_number }),
          tracking_number: document.tracking_number,
          event: 'unattended_reclaimed',
        },
      );

      reclaimedToPool += 1;
    }

    try {
      await broadcastStatusUpdated(documentId);
    } catch {
      // Realtime broadcast failures should not abort the sweep
    }
  }

  return {
    job: 'documents:reclaim-unaccepted',
    summary: `Reclaim unaccepted sweep complete: ${documents.length} scanned, ${reassigned} reassigned, ${reclaimedToPool} returned to pool.`,
    counts: { scanned: documents.length, reassigned, reclaimedToPool },
  };
}

/** Every sweep, by the Artisan name it had, so the cron route can dispatch by job. */
export const SWEEPS: Record<string, () => Promise<SweepResult>> = {
  'check-sla': checkDocumentSla,
  'check-holds': checkDocumentHolds,
  'check-overdue-loans': checkOverdueLoans,
  'reclaim-unaccepted': () => reclaimUnacceptedRequests(),
  'daily': async () => {
    const sla = await checkDocumentSla();
    const holds = await checkDocumentHolds();
    const loans = await checkOverdueLoans();

    return {
      job: 'daily',
      summary: `Daily sweep: ${sla.summary} | ${holds.summary} | ${loans.summary}`,
      counts: { ...sla.counts, ...holds.counts, ...loans.counts },
    };
  },
};
