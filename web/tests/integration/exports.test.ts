import { afterAll, describe, expect, it } from 'vitest';
import { db } from '@/lib/db';
import { exportCsv } from '@/lib/domain/command-center';

/** Both command-center exports, against the real analytics queries. */
const runs = process.env.DATABASE_URL?.startsWith('postgres') ? describe : describe.skip;

runs('command center export', () => {
  afterAll(() => db.$disconnect());

  it('writes the citizen side', async () => {
    const { filename, rows } = await exportCsv(new URLSearchParams('range=90'), null);

    expect(filename).toMatch(/^analytics-\d{4}-\d{2}-\d{2}\.csv$/);
    expect(rows.map((row) => row[0])).toContain('Requests received');
  });

  it('writes the internal side for one office', async () => {
    const { filename, rows } = await exportCsv(new URLSearchParams('view=internal'), { id: 5, name: 'Office', code: 'OFC' });

    expect(filename).toMatch(/^ofc-internal-requests-/);
    expect(rows.map((row) => row[0])).toContain('Requests filed');
  });
});
