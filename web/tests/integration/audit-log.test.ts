import { afterAll, describe, expect, it } from 'vitest';
import { db } from '@/lib/db';
import { auditPage } from '@/lib/domain/audit-log';

/**
 * The audit query against real Postgres: the JSON-path filter that drops empty
 * "updated" rows is the part no mock can check.
 */
const runs = process.env.DATABASE_URL?.startsWith('postgres') ? describe : describe.skip;

runs('audit log', () => {
  afterAll(() => db.$disconnect());

  it('pages the log without empty updates', async () => {
    const page = await auditPage(1, {});

    expect(page.rows.length).toBeLessThanOrEqual(30);
    expect(page.rows.every((row) => !(row.event === 'updated' && Object.keys((row.properties?.attributes as object) ?? {}).length === 0))).toBe(true);
  });

  it('filters to sign-ins', async () => {
    const page = await auditPage(1, { log: 'auth' });

    expect(page.rows.every((row) => row.isAuth)).toBe(true);
  });
});
