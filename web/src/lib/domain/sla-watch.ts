import { diffInHoursExact } from '@/lib/time';
import { slaHours } from '@/lib/domain/document-status';
import { statusOf } from '@/lib/domain/document';

/**
 * SLA pressure on a set of documents.
 *
 * Each stage carries its own budget and spends it from `status_changed_at`: the
 * manual status model moves that timestamp on every stage change, so a request
 * that has been sitting for a week in Processing is not judged by the day it
 * was filed.
 */

/** A document is at risk once it has spent this share of its stage budget. */
export const AT_RISK_RATIO = 0.75;

export type SlaStanding = {
  budgetHours: number;
  elapsedHours: number;
  remainingHours: number;
  overdue: boolean;
  ratio: number;
  /**
   * Signed, and precise: positive is hours overdue, negative is hours left.
   * One key that ranks both halves of the at-risk list.
   */
  hoursOver: number;
};

export type SlaInput = {
  status: string;
  status_changed_at: Date | null;
  updated_at: Date | null;
  created_at: Date | null;
};

/**
 * One document's SLA standing, or null when its stage has no budget to spend —
 * terminal stages, and the side states whose clock is deliberately paused.
 */
export function annotate(document: SlaInput): SlaStanding | null {
  const budget = slaHours(statusOf(document));
  const anchor = document.status_changed_at ?? document.updated_at ?? document.created_at;

  if (!budget || !anchor) {
    return null;
  }

  // Fractional on purpose. The two figures on screen are whole hours, but the
  // decisions keep the precise value, so a request ten minutes past its budget
  // still reads as overdue rather than as having exactly none of it left.
  const elapsed = diffInHoursExact(anchor);

  return {
    budgetHours: budget,
    elapsedHours: Math.round(elapsed),
    remainingHours: Math.max(0, Math.ceil(budget - elapsed)),
    overdue: elapsed > budget,
    ratio: elapsed / budget,
    hoursOver: elapsed - budget,
  };
}

/**
 * Annotate each document and keep only the ones at risk, worst first — the
 * order a queue should be worked in.
 *
 * Ordered by signed hours past the budget: the furthest overdue first, then the
 * closest to breaching. Sorting on remaining hours instead put every overdue
 * request at zero and left the worst offenders in whatever order the database
 * returned them.
 */
export function atRisk<T extends SlaInput>(documents: T[]): Array<T & { sla: SlaStanding }> {
  return documents
    .map((document) => ({ document, sla: annotate(document) }))
    .filter(
      (row): row is { document: T; sla: SlaStanding } =>
        row.sla !== null && row.sla.ratio >= AT_RISK_RATIO,
    )
    .sort((a, b) => b.sla.hoursOver - a.sla.hoursOver)
    .map((row) => ({ ...row.document, sla: row.sla }));
}

/**
 * Share of the stage budget spent, capped at 100 — the width of the bar. Past
 * the cap the overrun is reported in hours beside it, because a bar that can
 * only ever be full says nothing about how late a thing is.
 */
export function percentUsed(sla: SlaStanding | null): number {
  return Math.min(Math.round((sla?.ratio ?? 0) * 100), 100);
}
