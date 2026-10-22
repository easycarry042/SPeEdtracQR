import { db } from '@/lib/db';
import { addHours, diffInHoursExact, now } from '@/lib/time';
import { DocumentStatus, FLOW, slaHours } from '@/lib/domain/document-status';
import { statusOf } from '@/lib/domain/document';
import { citizenFacing } from '@/lib/domain/scope';

/**
 * Self-hosted predictive completion-time estimator.
 *
 * Every number is derived from real database timestamps so it is fully
 * explainable to a citizen or an auditor — there is no black-box model:
 *
 *   1. It learns the typical end-to-end turnaround (`created_at` →
 *      `completed_at`) of past COMPLETED documents of the same `document_type`,
 *      using the MEDIAN so a few slow outliers do not skew the estimate.
 *   2. remaining_hours = typical_total − hours already elapsed for THIS document.
 *   3. When there is no history yet, it falls back to the sum of each remaining
 *      stage's configured SLA budget.
 *
 * Confidence scales with how many similar past documents were available.
 */

/** Samples needed before the median is treated as a confident estimate. */
const HIGH_CONFIDENCE_SAMPLES = 8;
const MEDIUM_CONFIDENCE_SAMPLES = 3;

/** SLA budgets are an upper bound; documents usually finish well before them. */
const SLA_FALLBACK_FACTOR = 0.6;

/** Never predict an ETA in the past — keep at least this much runway. */
const MIN_REMAINING_HOURS = 2;

export type Prediction = {
  available: boolean;
  eta: Date | null;
  confidence: 'actual' | 'high' | 'medium' | 'low' | 'none';
  basedOn: number;
  remainingHours: number;
};

const UNAVAILABLE: Prediction = {
  available: false,
  eta: null,
  confidence: 'none',
  basedOn: 0,
  remainingHours: 0,
};

export async function predict(document: {
  status: string;
  document_type: string;
  created_at: Date | null;
  completed_at: Date | null;
  status_changed_at: Date | null;
}): Promise<Prediction> {
  const stage = statusOf(document);

  // Already finished — the "estimate" is simply the recorded fact.
  if (stage === DocumentStatus.Completed) {
    return {
      available: true,
      eta: document.completed_at,
      confidence: 'actual',
      basedOn: 0,
      remainingHours: 0,
    };
  }

  // Denied documents never complete, so there is nothing to predict.
  if (stage === DocumentStatus.Denied) {
    return UNAVAILABLE;
  }

  const samples = await historicalTurnaroundHours(document.document_type);
  const basedOn = samples.length;

  let remainingHours: number;
  let confidence: Prediction['confidence'];

  if (basedOn > 0) {
    const typicalTotalHours = median(samples);
    const anchor = document.created_at ?? document.status_changed_at;
    const elapsedHours = anchor ? Math.floor(diffInHoursExact(anchor)) : 0;

    remainingHours = typicalTotalHours - elapsedHours;
    confidence =
      basedOn >= HIGH_CONFIDENCE_SAMPLES
        ? 'high'
        : basedOn >= MEDIUM_CONFIDENCE_SAMPLES
          ? 'medium'
          : 'low';
  } else {
    // No history: budget the remaining stages from their configured SLAs.
    const budget = slaBudgetHours(stage);

    if (budget === null) {
      return UNAVAILABLE;
    }

    remainingHours = budget;
    confidence = 'low';
  }

  remainingHours = Math.max(remainingHours, MIN_REMAINING_HOURS);

  return {
    available: true,
    eta: addHours(now(), Math.round(remainingHours)),
    confidence,
    basedOn,
    remainingHours: Math.round(remainingHours * 10) / 10,
  };
}

/**
 * Turnaround hours of past completed documents, preferring the same
 * `document_type` and relaxing to all types when the type has no history yet.
 */
async function historicalTurnaroundHours(type: string | null): Promise<number[]> {
  const completed = await db.documents.findMany({
    where: {
      status: DocumentStatus.Completed,
      completed_at: { not: null },
      created_at: { not: null },
      deleted_at: null,
      ...citizenFacing,
    },
    select: { document_type: true, created_at: true, completed_at: true },
  });

  const toHours = (rows: typeof completed): number[] =>
    rows
      .map((row) => diffInHoursExact(row.created_at!, row.completed_at!))
      .filter((hours) => hours > 0);

  const typed = toHours(completed.filter((row) => row.document_type === type));

  return typed.length > 0 ? typed : toHours(completed);
}

/**
 * Sum of the SLA budgets of the current stage and every forward stage still
 * ahead of it, discounted by the fallback factor. Null when no stage ahead has
 * an SLA (nothing to base a fallback on).
 */
function slaBudgetHours(stage: DocumentStatus): number | null {
  const index = Math.max(0, FLOW.indexOf(stage));

  let sum = 0;
  let hasBudget = false;

  for (const ahead of FLOW.slice(index)) {
    const sla = slaHours(ahead);

    if (sla !== null) {
      sum += sla;
      hasBudget = true;
    }
  }

  return hasBudget ? sum * SLA_FALLBACK_FACTOR : null;
}

function median(values: number[]): number {
  const sorted = [...values].sort((a, b) => a - b);
  const middle = Math.trunc(sorted.length / 2);

  return sorted.length % 2 === 1 ? sorted[middle] : (sorted[middle - 1] + sorted[middle]) / 2;
}
