import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import type { Permission } from '@/lib/auth/rbac';
import {
  ROLE_STAFF,
  ROLE_SUPER_ADMIN,
  ROLE_SUPERVISOR,
} from '@/lib/auth/rbac';

const {
  queryRawMock,
  usersFindFirstMock,
  requestTypesFindFirstMock,
  headersMock,
  redirectMock,
} = vi.hoisted(() => ({
  queryRawMock: vi.fn(),
  usersFindFirstMock: vi.fn(),
  requestTypesFindFirstMock: vi.fn(),
  headersMock: vi.fn(),
  redirectMock: vi.fn(),
}));

vi.mock('@/lib/db', () => ({
  id: (value: bigint | number | string) => BigInt(value),
  toId: (value: bigint | number | string) => Number(value),
  db: {
    $queryRaw: queryRawMock,
    users: { findFirst: usersFindFirstMock },
    request_types: { findFirst: requestTypesFindFirstMock },
    rate_limits: { deleteMany: vi.fn(async () => ({ count: 0 })) },
  },
}));

vi.mock('next/headers', () => ({
  headers: headersMock,
}));

vi.mock('next/navigation', () => ({
  redirect: redirectMock,
}));

const { canAccessDocument, ORIGIN_EXTERNAL, ORIGIN_INTERNAL } = await import(
  '@/lib/domain/scope'
);
const { crossSite, hit, LIMITS, resetMemoryRateLimits } = await import(
  '@/lib/rate-limit'
);
const { login } = await import('@/app/(auth)/login/actions');
const { submitRequest } = await import('@/app/actions/request');

function makeActor(overrides: {
  id: number;
  departmentId: number | null;
  roles: string[];
  permissions?: Permission[];
}): Actor {
  const roles = overrides.roles;
  const permissions = overrides.permissions ?? [];

  return {
    id: overrides.id,
    name: `User ${overrides.id}`,
    email: `user${overrides.id}@speedtraqr.local`,
    departmentId: overrides.departmentId,
    departmentName: overrides.departmentId ? `Dept ${overrides.departmentId}` : null,
    avatarPath: null,
    signaturePath: null,
    isActive: true,
    isAvailable: true,
    roles,
    permissions,
    can: (permission) => permissions.includes(permission),
    hasRole: (role) => roles.includes(role),
    isSuperAdmin: () => roles.includes(ROLE_SUPER_ADMIN),
    isSupervisor: () => roles.includes(ROLE_SUPERVISOR),
    isClerk: () => false,
  };
}

describe('scope.canAccessDocument — department and involvement boundaries', () => {
  const superAdmin = makeActor({
    id: 1,
    departmentId: null,
    roles: [ROLE_SUPER_ADMIN],
    permissions: ['manage system'],
  });
  const supDept1 = makeActor({
    id: 10,
    departmentId: 1,
    roles: [ROLE_SUPERVISOR],
    permissions: ['accept documents'],
  });
  const supDept2 = makeActor({
    id: 20,
    departmentId: 2,
    roles: [ROLE_SUPERVISOR],
    permissions: ['accept documents'],
  });
  const staffDept1 = makeActor({
    id: 11,
    departmentId: 1,
    roles: [ROLE_STAFF],
    permissions: ['accept documents'],
  });
  const coHandlerDept1 = makeActor({
    id: 12,
    departmentId: 1,
    roles: [ROLE_STAFF],
  });
  const staffDept3 = makeActor({
    id: 30,
    departmentId: 3,
    roles: [ROLE_STAFF],
    permissions: ['accept documents'],
  });

  it('denies supervisors from accessing external requests belonging to another office', () => {
    const docDept1 = {
      origin: ORIGIN_EXTERNAL,
      department_id: 1,
      created_by: null,
      assigned_to: 11,
      accepted_at: new Date('2026-11-27T09:00:00Z'),
    };

    expect(canAccessDocument(docDept1, supDept2)).toBe(false);
    expect(canAccessDocument(docDept1, supDept1)).toBe(true);
    expect(canAccessDocument(docDept1, superAdmin)).toBe(true);
  });

  it('allows any supervisor to triage unrouted external requests', () => {
    const unrouted = {
      origin: ORIGIN_EXTERNAL,
      department_id: null,
      created_by: null,
      assigned_to: null,
      accepted_at: null,
    };

    expect(canAccessDocument(unrouted, supDept2)).toBe(true);
  });

  it('restricts internal document attachments to filing or routed offices and creator/admin', () => {
    const internalDoc = {
      origin: ORIGIN_INTERNAL,
      created_by: 11,
      assigned_to: null,
      requesting_department_id: 1,
      involved_department_ids: [2],
    };

    expect(canAccessDocument(internalDoc, staffDept3)).toBe(false);
    expect(
      canAccessDocument(internalDoc, makeActor({ id: 31, departmentId: 3, roles: [ROLE_SUPERVISOR] })),
    ).toBe(false);
    expect(canAccessDocument(internalDoc, supDept1)).toBe(true);
    expect(canAccessDocument(internalDoc, supDept2)).toBe(true);
    expect(canAccessDocument(internalDoc, staffDept1)).toBe(true);
    expect(canAccessDocument(internalDoc, superAdmin)).toBe(true);
  });

  it('allows same-office co-handlers to view unaccepted external files, then denies after another handler accepts', () => {
    const unaccepted = {
      origin: ORIGIN_EXTERNAL,
      department_id: 1,
      created_by: null,
      assigned_to: 11,
      accepted_at: null,
      handler_user_ids: [11, 12],
    };

    expect(canAccessDocument(unaccepted, coHandlerDept1)).toBe(true);
    expect(
      canAccessDocument(
        { ...unaccepted, accepted_at: new Date('2026-11-27T10:00:00Z') },
        coHandlerDept1,
      ),
    ).toBe(false);
  });
});

describe('rate-limit — fallback & crossSite hardening', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    resetMemoryRateLimits();
  });

  it('enforces quota via in-memory fallback when Postgres is unavailable', async () => {
    queryRawMock.mockRejectedValue(new Error('DB connection lost'));

    expect(await hit('fallback:test', { max: 2, perSeconds: 60 })).toBe(true);
    expect(await hit('fallback:test', { max: 2, perSeconds: 60 })).toBe(true);
    expect(await hit('fallback:test', { max: 2, perSeconds: 60 })).toBe(false);
  });

  it('rejects cross-site Sec-Fetch-Site, mismatched Referer without Origin, and Origin: null', () => {
    const makeReq = (hdrs: Record<string, string>) =>
      new Request('https://speedtraqr.local/api/test', {
        method: 'POST',
        headers: { host: 'speedtraqr.local', ...hdrs },
      });

    expect(crossSite(makeReq({ 'sec-fetch-site': 'cross-site' }))).toBe(true);
    expect(crossSite(makeReq({ referer: 'https://evil.example/form' }))).toBe(true);
    expect(crossSite(makeReq({ origin: 'null' }))).toBe(true);
    expect(
      crossSite(
        makeReq({
          'sec-fetch-site': 'same-origin',
          origin: 'https://speedtraqr.local',
        }),
      ),
    ).toBe(false);
    expect(crossSite(makeReq({ referer: 'https://speedtraqr.local/track/SPD-1' }))).toBe(false);
    expect(crossSite(makeReq({}))).toBe(false);
  });
});

describe('login & submitRequest server-action rate limiting', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    resetMemoryRateLimits();
    headersMock.mockResolvedValue(new Headers({ 'x-forwarded-for': '203.0.113.9' }));
  });

  it('blocks login attempts beyond LIMITS.login without querying db.users', async () => {
    // Reject DB so the in-memory counter handles calls deterministically in unit test
    queryRawMock.mockRejectedValue(new Error('unit mock'));

    const buildForm = () => {
      const form = new FormData();
      form.set('email', 'staff@example.com');
      form.set('password', 'wrong');
      return form;
    };

    for (let i = 0; i < LIMITS.login.max; i += 1) {
      await login(undefined, buildForm());
    }

    usersFindFirstMock.mockClear();
    const throttled = await login(undefined, buildForm());

    expect(throttled?.error).toMatch(/too many sign-in attempts/i);
    expect(usersFindFirstMock).not.toHaveBeenCalled();
  });

  it('blocks submitRequest attempts beyond LIMITS.citizenSubmit without querying request_types', async () => {
    queryRawMock.mockRejectedValue(new Error('unit mock'));

    const buildCitizenForm = () => {
      const form = new FormData();
      form.set('document_type', '');
      form.set('citizen_name', 'Juan Cruz');
      form.set('citizen_email', 'juan@example.com');
      form.set('citizen_contact', '09171234567');
      form.set('consent', '1');
      return form;
    };

    for (let i = 0; i < LIMITS.citizenSubmit.max; i += 1) {
      await submitRequest(buildCitizenForm());
    }

    requestTypesFindFirstMock.mockClear();
    const throttled = await submitRequest(buildCitizenForm());

    expect(throttled.ok).toBe(false);
    expect(throttled.errors.form).toMatch(/too many requests/i);
    expect(requestTypesFindFirstMock).not.toHaveBeenCalled();
  });
});
