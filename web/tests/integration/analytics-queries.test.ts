import { afterAll, describe, expect, it } from 'vitest';
import { db } from '@/lib/db';
import * as citizen from '@/lib/domain/admin-analytics';
import * as internal from '@/lib/domain/internal-analytics';
import * as services from '@/lib/domain/service-report';
import * as commandCenter from '@/lib/domain/command-center';
import { recentOnRequests } from '@/lib/activity';
import { endOfDay, now, startOfDay, subDays } from '@/lib/time';

/**
 * Runs every analytics query against the real database, READ ONLY.
 *
 * TypeScript cannot catch a wrong Prisma relation name or an invalid `groupBy`
 * shape — those fail at runtime, and these panels are otherwise only exercised
 * by loading a page. This suite is the cheap guard: it asserts the queries
 * execute and return the documented shape, not any particular figure, so it
 * stays green whatever the office's data happens to be.
 *
 * Skips itself when there is no database, so the unit suite still runs anywhere.
 */
const reachable = await db.documents
  .count()
  .then(() => true)
  .catch(() => false);

const scope = {
  from: startOfDay(subDays(now(), 29)),
  to: endOfDay(now()),
  departmentId: null,
  documentType: null,
};

afterAll(async () => {
  await db.$disconnect();
});

describe.skipIf(!reachable)('citizen analytics against the live schema', () => {
  it('kpis reads one cohort, so the tiles add back to the total', async () => {
    const result = await citizen.kpis(scope.from, scope.to);

    // Each tile is a value plus its trend, not a bare number.
    expect(typeof result.total.value).toBe('number');

    // Pending + in progress + completed + denied are the cohort split by where
    // each request is now, so they cannot exceed what came in.
    const split =
      result.pending.value +
      result.inProgress.value +
      result.completed.value +
      result.denied.value;

    expect(split).toBeLessThanOrEqual(result.total.value);
  });

  it('throughput returns one row per week with both series', async () => {
    const rows = await citizen.throughput(scope);

    expect(Array.isArray(rows)).toBe(true);
    for (const row of rows) {
      expect(row).toMatchObject({
        label: expect.any(String),
        created: expect.any(Number),
        completed: expect.any(Number),
      });
    }
  });

  it('staffWorkload lists idle staff too, so "who has room?" is answerable', async () => {
    const rows = await citizen.staffWorkload(scope);

    for (const row of rows) {
      expect(row).toMatchObject({
        name: expect.any(String),
        completed: expect.any(Number),
        active: expect.any(Number),
        overloaded: expect.any(Boolean),
      });
    }
  });

  it('timeByStage covers every citizen stage except On Hold', async () => {
    const rows = await citizen.timeByStage(scope);

    expect(rows.length).toBeGreaterThan(0);
    // Paused time is not a slow stage.
    expect(rows.map((row) => row.label)).not.toContain('On Hold');
    for (const row of rows) {
      expect(typeof row.hours).toBe('number');
    }
  });

  it('typeBreakdown returns at most six rows, busiest first', async () => {
    const rows = await citizen.typeBreakdown(scope);

    expect(rows.length).toBeLessThanOrEqual(6);
    for (let i = 1; i < rows.length; i += 1) {
      expect(rows[i - 1].value).toBeGreaterThanOrEqual(rows[i].value);
    }
  });

  it('departmentVolume scales each bar against the busiest office', async () => {
    const rows = await citizen.departmentVolume(scope);

    for (const row of rows) {
      expect(row.pct).toBeGreaterThanOrEqual(0);
      expect(row.pct).toBeLessThanOrEqual(100);
    }

    if (rows.length > 0) {
      // Exactly the busiest row(s) sit at 100%.
      expect(rows[0].busiest).toBe(true);
      expect(rows[0].pct).toBe(100);
    }
  });

  it('unrouted finds only open requests with no office', async () => {
    const rows = await citizen.unrouted(scope);

    for (const row of rows) {
      expect(row).toHaveProperty('tracking_number');
    }
  });

  it('atRisk and stalledHolds are disjoint — a hold nulls the stage SLA', async () => {
    const [overdue, held] = await Promise.all([
      citizen.atRisk(scope),
      citizen.stalledHolds(scope),
    ]);

    const overdueIds = new Set(overdue.map((row) => row.id));
    for (const row of held) {
      expect(overdueIds.has(row.id), `${row.tracking_number} in both panels`).toBe(false);
    }
  });

  it('statusDistribution', async () => {
    const rows = await citizen.statusDistribution(null);

    for (const row of rows) {
      expect(row).toMatchObject({ status: expect.any(String), count: expect.any(Number) });
    }
  });
});

describe.skipIf(!reachable)('internal analytics against the live schema', () => {
  it('kpis', async () => {
    const result = await internal.kpis(scope);

    expect(result).toMatchObject({
      filed: expect.any(Number),
      moving: expect.any(Number),
      returned: expect.any(Number),
      overdue: expect.any(Number),
    });
  });

  it('waiting sorts by longest wait and never gives a returned row a deadline', async () => {
    const rows = await internal.waiting(scope);

    for (let i = 1; i < rows.length; i += 1) {
      expect(rows[i - 1].waited_hours).toBeGreaterThanOrEqual(rows[i].waited_hours);
    }

    for (const row of rows) {
      if (row.returned) {
        // A request returned for revision waits on the office that filed it,
        // with no deadline of its own.
        expect(row.limit_hours).toBeNull();
        expect(row.overdue).toBe(false);
      }
    }
  });

  it('waitingByOffice', async () => {
    const rows = await internal.waitingByOffice(scope);

    for (const row of rows) {
      expect(row.overdue).toBeLessThanOrEqual(row.count);
    }
  });

  it('officeTurnaround', async () => {
    const rows = await internal.officeTurnaround(scope);

    for (const row of rows) {
      expect(row.hops).toBeGreaterThan(0);
      expect(typeof row.over).toBe('boolean');
    }
  });

  it('byRequestingOffice', async () => {
    const rows = await internal.byRequestingOffice(scope);

    for (const row of rows) {
      expect(row.pct).toBeLessThanOrEqual(100);
    }
  });

  it('throughput', async () => {
    expect(Array.isArray(await internal.throughput(scope))).toBe(true);
  });
});

describe.skipIf(!reachable)('service report panels', () => {
  it('requirementStats percentages never exceed 100', async () => {
    const stats = await services.requirementStats(null);

    expect(stats.submitted_pct).toBeLessThanOrEqual(100);
    expect(stats.verified_pct).toBeLessThanOrEqual(100);
    expect(stats.submitted).toBeLessThanOrEqual(stats.mandatory_total);
  });

  it('bookingsByResource', async () => {
    const rows = await services.bookingsByResource(null);

    for (const row of rows) {
      expect(row.approved + row.pending).toBeLessThanOrEqual(row.total);
    }
  });
});

describe.skipIf(!reachable)('the command center composes both sides', () => {
  it('builds the citizen view city-wide', async () => {
    const data = await commandCenter.data(new URLSearchParams(), null);

    expect(data.audience).toBe('admin');
    expect(data.filters.view).toBe('citizen');
    expect(data.kpis).toBeDefined();
    // The city page compares offices; an office's own page has nothing to.
    expect(data.departmentVolume).toBeDefined();
    expect(data.internalKpis).toBeUndefined();
  });

  it('builds the internal view', async () => {
    const data = await commandCenter.data(new URLSearchParams('view=internal'), null);

    expect(data.filters.view).toBe('internal');
    expect(data.internalKpis).toBeDefined();
    // Never both sides at once — they are different lives, never added up.
    expect(data.kpis).toBeUndefined();
  });

  it('clamps a hand-edited range instead of widening the window', async () => {
    const data = await commandCenter.data(new URLSearchParams('range=99999'), null);

    expect(data.filters.range).toBe(30);
  });

  it('ignores a document type that does not exist', async () => {
    const data = await commandCenter.data(
      new URLSearchParams('document_type=NoSuchTypeAtAll'),
      null,
    );

    expect(data.filters.document_type).toBeNull();
  });

  it('scopes to one office when given one', async () => {
    const office = await db.departments.findFirst({ select: { id: true, name: true, code: true } });

    if (!office) {
      return;
    }

    const data = await commandCenter.data(new URLSearchParams(), {
      id: Number(office.id),
      name: office.name,
      code: office.code,
    });

    expect(data.audience).toBe('office');
    // One office has nothing to compare itself against.
    expect(data.departmentVolume).toEqual([]);
    expect(data.unrouted).toEqual([]);
  });
});

describe.skipIf(!reachable)('activity strip', () => {
  it('reads both sides off the activity log', async () => {
    for (const internalSide of [true, false]) {
      const rows = await recentOnRequests({ internal: internalSide, limit: 5 });

      expect(rows.length).toBeLessThanOrEqual(5);
      for (const row of rows) {
        expect(row).toHaveProperty('description');
      }
    }
  });
});
