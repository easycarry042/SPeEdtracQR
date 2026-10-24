import { afterAll, describe, expect, it } from 'vitest';
import { db } from '@/lib/db';
import { hit } from '@/lib/rate-limit';

/**
 * The Postgres-backed throttle, against a real database: the counter has to
 * be atomic and reset by the database's own clock, which no mock can show.
 */
const KEY = `test:${Date.now()}:${Math.random()}`;
const runs = process.env.DATABASE_URL?.startsWith('postgres') ? describe : describe.skip;

runs('rate limit', () => {
  afterAll(async () => {
    await db.rate_limits.deleteMany({ where: { key: { startsWith: KEY } } });
    await db.$disconnect();
  });

  it('allows the quota, then refuses', async () => {
    const results = [];

    for (let i = 0; i < 4; i += 1) {
      results.push(await hit(KEY, { max: 3, perSeconds: 60 }));
    }

    expect(results).toEqual([true, true, true, false]);
  });

  it('counts concurrent hits exactly', async () => {
    const results = await Promise.all(
      Array.from({ length: 10 }, () => hit(`${KEY}:burst`, { max: 5, perSeconds: 60 })),
    );

    expect(results.filter(Boolean)).toHaveLength(5);
  });

  it('starts a fresh window once the old one has passed', async () => {
    await hit(`${KEY}:window`, { max: 1, perSeconds: 1 });
    expect(await hit(`${KEY}:window`, { max: 1, perSeconds: 1 })).toBe(false);

    await new Promise((resolve) => setTimeout(resolve, 1_100));

    expect(await hit(`${KEY}:window`, { max: 1, perSeconds: 1 })).toBe(true);
  });
});
