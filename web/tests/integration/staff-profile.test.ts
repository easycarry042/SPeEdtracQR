import { afterAll, describe, expect, it } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { db } from '@/lib/db';
import { directory, profile } from '@/lib/domain/staff-profile';

/** The directory and a profile against real data: every query, end to end. */
const runs = process.env.DATABASE_URL?.startsWith('postgres') ? describe : describe.skip;

runs('staff profile queries', () => {
  afterAll(() => db.$disconnect());

  it('lists the directory, sorted by open load', async () => {
    const data = await directory({ sort: 'load' });

    expect(data.staff.length).toBeGreaterThan(0);
    expect(data.staff.map((s) => s.assigned)).toEqual([...data.staff.map((s) => s.assigned)].sort((a, b) => b - a));
  });

  it('builds a profile on every tab', async () => {
    const someone = await db.users.findFirst({ select: { id: true } });
    const viewer = { id: Number(someone!.id), can: () => true, hasRole: () => true } as unknown as Actor;

    for (const tab of ['activity', 'assigned', 'completions']) {
      const data = await profile(Number(someone!.id), viewer, tab);

      expect(data?.heatmap.since).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    }
  });
});
