import { PrismaClient } from '@prisma/client';

/**
 * Single Prisma client. Next.js reloads modules in dev, so the client is cached
 * on `globalThis` to avoid exhausting the database's connection pool.
 */
const globalForPrisma = globalThis as unknown as { prisma?: PrismaClient };

export const db =
  globalForPrisma.prisma ??
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['warn', 'error'] : ['error'],
  });

if (process.env.NODE_ENV !== 'production') {
  globalForPrisma.prisma = db;
}

/**
 * Every primary key in the schema is a BigInt, so Prisma hands back
 * `bigint`. These two helpers are the boundary: ids become `number` the moment
 * they leave the data layer (no table here comes close to 2^53), and go back to
 * `bigint` on the way into a query.
 */
export function toId(value: bigint | number): number {
  return typeof value === 'bigint' ? Number(value) : value;
}

export function id(value: number | string | bigint): bigint {
  return BigInt(value);
}

/** `JSON.stringify` throws on bigint; route handlers serialise through this. */
export function jsonSafe<T>(value: T): T {
  return JSON.parse(
    JSON.stringify(value, (_key, val) => (typeof val === 'bigint' ? Number(val) : val)),
  ) as T;
}
