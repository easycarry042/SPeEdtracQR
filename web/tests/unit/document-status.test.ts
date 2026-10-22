import { describe, expect, it } from 'vitest';
import {
  DEFAULT_SLA_HOURS,
  DocumentStatus,
  FLOW,
  RAIL_STAGES,
  activeValues,
  band,
  citizenCases,
  description,
  fromLoose,
  isTerminal,
  label,
  next,
  position,
  previous,
  railStage,
  slaHours,
  values,
} from '@/lib/domain/document-status';

/**
 * Ports `tests/Unit/DocumentStatusTest.php` and
 * `tests/Unit/DocumentStatusRailTest.php`.
 */
describe('DocumentStatus', () => {
  it('gives every status a non-empty citizen description that reads as a sentence', () => {
    for (const status of values()) {
      const text = description(status);

      expect(text, `${status} is missing a description`).not.toBe('');
      expect(text.endsWith('.'), `${status} description should read as a full sentence`).toBe(true);
    }
  });

  it('never reuses the label as the description', () => {
    for (const status of values()) {
      expect(description(status)).not.toBe(label(status));
    }
  });

  it('labels and bands every status', () => {
    for (const status of values()) {
      expect(label(status)).not.toBe('');
      expect(['green', 'brass', 'warn', 'hold', 'muted']).toContain(band(status));
    }
  });

  describe('the four-stage progress bar', () => {
    it('has the prototype stages in order', () => {
      expect(RAIL_STAGES.map((stage) => stage.label)).toEqual([
        'Pending',
        'In review',
        'Processing',
        'Completed',
      ]);
    });

    it('places every status on the forward flow on exactly one stage', () => {
      const placed = RAIL_STAGES.flatMap((stage) => stage.statuses);

      for (const status of FLOW) {
        expect(placed.filter((value) => value === status), status).toHaveLength(1);
      }
    });

    it.each([
      ['pending', DocumentStatus.Pending, null, 1],
      ['in progress is review', DocumentStatus.InProgress, null, 2],
      ['in review', DocumentStatus.InReview, null, 2],
      ['approved is processing', DocumentStatus.Approved, null, 3],
      ['completed', DocumentStatus.Completed, null, 4],
      ['returned goes back to review', DocumentStatus.Returned, null, 2],
      ['on hold keeps its place', DocumentStatus.OnHold, DocumentStatus.Approved, 3],
      ['on hold with no record', DocumentStatus.OnHold, null, 2],
      ['denied where it stopped', DocumentStatus.Denied, DocumentStatus.Pending, 1],
    ] as const)('lands %s on its stage', (_name, status, before, stage) => {
      expect(railStage(status, before)).toBe(stage);
    });
  });

  describe('the forward line', () => {
    it('walks forward and back through the flow', () => {
      expect(next(DocumentStatus.Pending)).toBe(DocumentStatus.InProgress);
      expect(next(DocumentStatus.Approved)).toBe(DocumentStatus.Completed);
      // End of the line.
      expect(next(DocumentStatus.Completed)).toBeNull();
      // Off the line entirely.
      expect(next(DocumentStatus.Returned)).toBeNull();
      expect(next(DocumentStatus.OnHold)).toBeNull();

      expect(previous(DocumentStatus.InProgress)).toBe(DocumentStatus.Pending);
      expect(previous(DocumentStatus.Pending)).toBeNull();
      expect(previous(DocumentStatus.Returned)).toBeNull();
    });

    it('numbers positions from 1, and 0 off the line', () => {
      expect(position(DocumentStatus.Pending)).toBe(1);
      expect(position(DocumentStatus.Completed)).toBe(5);
      expect(position(DocumentStatus.Returned)).toBe(0);
      expect(position(DocumentStatus.OnHold)).toBe(0);
    });
  });

  describe('SLA budgets', () => {
    it('gives the four open stages a budget', () => {
      expect(slaHours(DocumentStatus.Pending)).toBe(24);
      expect(slaHours(DocumentStatus.InProgress)).toBe(72);
      expect(slaHours(DocumentStatus.InReview)).toBe(48);
      expect(slaHours(DocumentStatus.Approved)).toBe(24);
      expect(DEFAULT_SLA_HOURS).toBe(48);
    });

    it('gives terminal and off-line stages none, so On Hold pauses the clock', () => {
      for (const status of [
        DocumentStatus.Completed,
        DocumentStatus.Returned,
        DocumentStatus.OnHold,
        DocumentStatus.Denied,
        DocumentStatus.Withdrawn,
      ]) {
        expect(slaHours(status), status).toBeNull();
      }
    });
  });

  describe('cohorts', () => {
    it('treats Completed, Denied and Withdrawn as terminal', () => {
      expect(values().filter(isTerminal)).toEqual([
        DocumentStatus.Completed,
        DocumentStatus.Denied,
        DocumentStatus.Withdrawn,
      ]);
    });

    it('leaves Withdrawn out of the citizen cases — it is internal-only', () => {
      expect(citizenCases()).not.toContain(DocumentStatus.Withdrawn);
      expect(citizenCases()).toContain(DocumentStatus.Denied);
      expect(citizenCases()).toHaveLength(values().length - 1);
    });

    it('counts everything non-terminal as active', () => {
      expect(activeValues()).not.toContain(DocumentStatus.Completed);
      expect(activeValues()).not.toContain(DocumentStatus.Withdrawn);
      expect(activeValues()).toContain(DocumentStatus.OnHold);
      expect(activeValues()).toContain(DocumentStatus.Returned);
    });
  });

  describe('fromLoose', () => {
    it('maps the legacy in_transit value onto In Progress', () => {
      expect(fromLoose('in_transit')).toBe(DocumentStatus.InProgress);
    });

    it('falls back for null, empty and unknown values', () => {
      expect(fromLoose(null)).toBe(DocumentStatus.Pending);
      expect(fromLoose('')).toBe(DocumentStatus.Pending);
      expect(fromLoose('nonsense')).toBe(DocumentStatus.Pending);
      expect(fromLoose(undefined, DocumentStatus.InReview)).toBe(DocumentStatus.InReview);
    });

    it('passes canonical values straight through', () => {
      for (const status of values()) {
        expect(fromLoose(status)).toBe(status);
      }
    });
  });
});
