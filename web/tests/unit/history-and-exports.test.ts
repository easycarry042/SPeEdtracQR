import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { toCsv } from '@/lib/csv';

/**
 * Port of `HistoryExportTest` / `ReportsExportTest`, plus the CSV rules the
 * PHP export did not have: names come from a public form, so a cell that
 * would run as a spreadsheet formula is neutered.
 */
const count = vi.fn();
const findMany = vi.fn();
const currentActor = vi.fn();
const exportCsv = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { documents: { count, findMany }, departments: { findFirst: vi.fn() } },
  toId: Number,
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/auth/actor', () => ({ currentActor }));
vi.mock('@/lib/domain/command-center', () => ({ exportCsv }));

const history = await import('@/lib/domain/history');
const adminExport = await import('@/app/(staff)/admin/dashboard/export/route');
const reportsExport = await import('@/app/(staff)/reports/export/route');

function actor(permissions: string[], roles: string[] = []): Actor {
  return {
    id: 5,
    departmentId: 3,
    roles,
    can: (permission: string) => permissions.includes(permission),
    hasRole: (role: string) => roles.includes(role),
  } as unknown as Actor;
}

const json = (value: unknown): string =>
  JSON.stringify(value, (_key, val) => (typeof val === 'bigint' ? Number(val) : val));

beforeEach(() => {
  vi.clearAllMocks();
  count.mockResolvedValue(0);
  findMany.mockResolvedValue([]);
  exportCsv.mockResolvedValue({ filename: 'analytics.csv', rows: [['x']] });
});

describe('toCsv', () => {
  it('quotes what needs quoting and starts with a BOM for Excel', () => {
    expect(toCsv([['a,b', 'say "hi"', null, 3]])).toBe('﻿"a,b","say ""hi""",,3\r\n');
  });

  it('neuters cells a spreadsheet would run as formulas', () => {
    expect(toCsv([['=HYPERLINK("x")', '+1', '-2', '@SUM(A1)', 'Juan']])).toBe(
      '﻿"\'=HYPERLINK(""x"")",\'+1,\'-2,\'@SUM(A1),Juan\r\n',
    );
  });

  it('leaves negative numbers alone', () => {
    expect(toCsv([[-5]])).toBe('﻿-5\r\n');
  });
});

describe('history', () => {
  it('lists completed requests only, newest completion first', async () => {
    await history.historyPage(actor(['advance documents']), 1, {});

    const call = findMany.mock.calls.at(-1)?.[0];
    expect(json(call.where)).toContain('"status":"completed"');
    expect(call.orderBy[0]).toEqual({ completed_at: { sort: 'desc', nulls: 'last' } });
    expect(call.take).toBe(15);
  });

  it('searches ignoring case and filters by type and day', async () => {
    await history.historyPage(actor(['advance documents']), 1, {
      search: 'juan',
      document_type: 'Cedula',
      from: '2026-10-01',
      to: '2026-10-31',
    });

    const where = json(count.mock.calls[0][0].where);
    expect(where).toContain('"citizen_name":{"contains":"juan","mode":"insensitive"}');
    expect(where).toContain('"document_type":"Cedula"');
    expect(where).toContain('"created_at":{"gte"');
    expect(where).toContain('"created_at":{"lt"');
  });
});

describe('exports', () => {
  const request = (path: string) => ({ url: `http://localhost${path}`, nextUrl: new URL(`http://localhost${path}`) }) as never;

  it('gives the city export to super admins only', async () => {
    currentActor.mockResolvedValue(actor(['view reports']));
    expect((await adminExport.GET(request('/admin/dashboard/export'))).status).toBe(403);

    currentActor.mockResolvedValue(actor(['manage system'], ['super_admin']));
    const response = await adminExport.GET(request('/admin/dashboard/export?view=internal'));

    expect(response.status).toBe(200);
    expect(response.headers.get('content-disposition')).toBe('attachment; filename="analytics.csv"');
    expect(exportCsv).toHaveBeenCalledWith(expect.any(URLSearchParams), null);
  });

  it('sends a super admin on from Reports to the city export, keeping the filters', async () => {
    currentActor.mockResolvedValue(actor(['view reports', 'manage system'], ['super_admin']));

    const response = await reportsExport.GET(request('/reports/export?range=7'));

    expect(response.status).toBe(307);
    expect(response.headers.get('location')).toBe('http://localhost/admin/dashboard/export?range=7');
  });

  it('refuses staff without view reports', async () => {
    currentActor.mockResolvedValue(actor(['advance documents']));

    expect((await reportsExport.GET(request('/reports/export'))).status).toBe(403);
  });
});
