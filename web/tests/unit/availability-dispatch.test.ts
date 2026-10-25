import { beforeEach, describe, expect, it, vi } from 'vitest';
import { resolveAutoDispatch } from '@/lib/domain/auto-dispatch';
import {
  setUserAvailability,
  toggleUserAvailability,
  toggleMyAvailability,
} from '@/lib/domain/availability';
import { db } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';
import { ActionError } from '@/lib/domain/status-actions';

vi.mock('@/lib/realtime', () => ({
  broadcastStatusUpdated: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/activity', () => ({
  logOnDocument: vi.fn().mockResolvedValue(undefined),
}));

describe('Proactive Layer: "On Duty" Availability & Dispatch (Item 1)', () => {
  const staff1 = {
    id: 11,
    name: 'Ana Cruz',
    email: 'ana.cruz@speedtraqr.com',
    role: 'staff',
    departmentId: 1,
    can: (p: string) => false,
    hasRole: (r: string) => r === 'staff',
    isSuperAdmin: () => false,
    isSupervisor: () => false,
  } as unknown as Actor;

  const staff2 = {
    id: 12,
    name: 'Paolo Mendoza',
    email: 'paolo.mendoza@speedtraqr.com',
    role: 'staff',
    departmentId: 1,
    can: (p: string) => false,
    hasRole: (r: string) => r === 'staff',
    isSuperAdmin: () => false,
    isSupervisor: () => false,
  } as unknown as Actor;

  const supervisorDept1 = {
    id: 2,
    name: 'Maria Santos',
    email: 'maria.santos@speedtraqr.com',
    role: 'Supervisor',
    departmentId: 1,
    can: (p: string) => p === 'assign documents',
    hasRole: (r: string) => r === 'Supervisor',
    isSuperAdmin: () => false,
    isSupervisor: () => true,
  } as unknown as Actor;

  const supervisorDept2 = {
    id: 3,
    name: 'Ramon Bautista',
    email: 'om.head@speedtraqr.com',
    role: 'Supervisor',
    departmentId: 2,
    can: (p: string) => p === 'assign documents',
    hasRole: (r: string) => r === 'Supervisor',
    isSuperAdmin: () => false,
    isSupervisor: () => true,
  } as unknown as Actor;

  const superAdmin = {
    id: 1,
    name: 'Super Admin',
    email: 'admin@speedtraqr.com',
    role: 'super_admin',
    departmentId: null,
    can: (p: string) => true,
    hasRole: (r: string) => r === 'super_admin',
    isSuperAdmin: () => true,
    isSupervisor: () => false,
  } as unknown as Actor;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Daily Availability Toggle & Supervisor Override', () => {
    it('allows a staff member to toggle their own availability (On Duty <-> On Leave)', async () => {
      const mockUserRow = {
        id: 11n,
        name: 'Ana Cruz',
        department_id: 1n,
        is_available: true,
      };

      vi.spyOn(db.users, 'findFirst').mockResolvedValue(mockUserRow as any);
      const updateSpy = vi.spyOn(db.users, 'update').mockResolvedValue({} as any);

      // Staff toggles to false (On Leave)
      const res1 = await setUserAvailability(11, false, staff1);
      expect(res1.ok).toBe(true);
      expect(res1.isAvailable).toBe(false);
      expect(res1.message).toContain('On Leave');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 11n },
          data: expect.objectContaining({ is_available: false }),
        }),
      );

      // Toggle helper
      const res2 = await toggleUserAvailability(11, staff1);
      expect(res2.ok).toBe(true);
      expect(res2.isAvailable).toBe(false); // inverted mockUserRow.is_available (true -> false)
    });

    it('allows a supervisor to override availability for staff in their department', async () => {
      const mockStaffRow = {
        id: 11n,
        name: 'Ana Cruz',
        department_id: 1n,
        is_available: true,
      };

      vi.spyOn(db.users, 'findFirst').mockResolvedValue(mockStaffRow as any);
      const updateSpy = vi.spyOn(db.users, 'update').mockResolvedValue({} as any);

      // Supervisor marks sick staff as On Leave
      const res = await setUserAvailability(11, false, supervisorDept1);
      expect(res.ok).toBe(true);
      expect(res.isAvailable).toBe(false);
      expect(res.message).toBe('Ana Cruz has been marked as On Leave.');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 11n },
          data: expect.objectContaining({ is_available: false }),
        }),
      );
    });

    it('forbids a supervisor from overriding availability of staff in another department', async () => {
      const mockStaffOtherDept = {
        id: 11n,
        name: 'Ana Cruz',
        department_id: 1n, // Dept 1
        is_available: true,
      };

      vi.spyOn(db.users, 'findFirst').mockResolvedValue(mockStaffOtherDept as any);

      // Supervisor from Dept 2 attempts to toggle staff in Dept 1
      await expect(setUserAvailability(11, false, supervisorDept2)).rejects.toThrow(
        'You do not have permission to change this staff member’s duty status.',
      );
    });

    it('allows super admin to override availability for any staff member across departments', async () => {
      const mockStaffRow = {
        id: 11n,
        name: 'Ana Cruz',
        department_id: 1n,
        is_available: false,
      };

      vi.spyOn(db.users, 'findFirst').mockResolvedValue(mockStaffRow as any);
      const updateSpy = vi.spyOn(db.users, 'update').mockResolvedValue({} as any);

      const res = await setUserAvailability(11, true, superAdmin);
      expect(res.ok).toBe(true);
      expect(res.isAvailable).toBe(true);
      expect(res.message).toBe('Ana Cruz has been marked as On Duty.');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 11n },
          data: expect.objectContaining({ is_available: true }),
        }),
      );
    });
  });

  describe('Auto-Dispatch Check with Availability', () => {
    it('automatically bypasses a primary handler on leave and assigns to the secondary available handler', async () => {
      // Primary handler (sort_order: 1) is On Leave (is_available: false)
      // Secondary handler (sort_order: 2) is On Duty (is_available: true)
      const mockHandlers = [
        {
          id: 1n,
          request_type_id: 100n,
          user_id: 11n,
          sort_order: 1,
          user: {
            id: 11n,
            name: 'Ana Cruz (Primary)',
            email: 'ana.cruz@speedtraqr.com',
            is_active: true,
            is_available: false, // ON LEAVE
            deleted_at: null,
          },
        },
        {
          id: 2n,
          request_type_id: 100n,
          user_id: 12n,
          sort_order: 2,
          user: {
            id: 12n,
            name: 'Paolo Mendoza (Secondary)',
            email: 'paolo.mendoza@speedtraqr.com',
            is_active: true,
            is_available: true, // ON DUTY
            deleted_at: null,
          },
        },
      ];

      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue(mockHandlers as any);

      const dispatchResult = await resolveAutoDispatch(100);

      expect(dispatchResult).not.toBeNull();
      expect(dispatchResult?.id).toBe(12);
      expect(dispatchResult?.name).toBe('Paolo Mendoza (Secondary)');
      expect(dispatchResult?.email).toBe('paolo.mendoza@speedtraqr.com');
    });

    it('assigns to the primary handler when they are available and on duty', async () => {
      const mockHandlers = [
        {
          id: 1n,
          request_type_id: 100n,
          user_id: 11n,
          sort_order: 1,
          user: {
            id: 11n,
            name: 'Ana Cruz (Primary)',
            email: 'ana.cruz@speedtraqr.com',
            is_active: true,
            is_available: true, // ON DUTY
            deleted_at: null,
          },
        },
        {
          id: 2n,
          request_type_id: 100n,
          user_id: 12n,
          sort_order: 2,
          user: {
            id: 12n,
            name: 'Paolo Mendoza (Secondary)',
            email: 'paolo.mendoza@speedtraqr.com',
            is_active: true,
            is_available: true, // ON DUTY
            deleted_at: null,
          },
        },
      ];

      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue(mockHandlers as any);
      vi.spyOn(db.documents, 'groupBy').mockResolvedValue([] as any); // Equal load (0)

      const dispatchResult = await resolveAutoDispatch(100);

      expect(dispatchResult).not.toBeNull();
      expect(dispatchResult?.id).toBe(11);
      expect(dispatchResult?.name).toBe('Ana Cruz (Primary)');
    });

    it('returns null (falls back to Department Pool) if all configured handlers are on leave', async () => {
      const mockHandlers = [
        {
          id: 1n,
          request_type_id: 100n,
          user_id: 11n,
          sort_order: 1,
          user: {
            id: 11n,
            name: 'Ana Cruz',
            email: 'ana.cruz@speedtraqr.com',
            is_active: true,
            is_available: false, // ON LEAVE
            deleted_at: null,
          },
        },
        {
          id: 2n,
          request_type_id: 100n,
          user_id: 12n,
          sort_order: 2,
          user: {
            id: 12n,
            name: 'Paolo Mendoza',
            email: 'paolo.mendoza@speedtraqr.com',
            is_active: true,
            is_available: false, // ON LEAVE
            deleted_at: null,
          },
        },
      ];

      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue(mockHandlers as any);

      const dispatchResult = await resolveAutoDispatch(100);

      expect(dispatchResult).toBeNull();
    });
  });

  describe('Structural Layer: Multi-Handler Rotation & Pool Fallback (Item 3)', () => {
    const handler1 = {
      id: 1n,
      request_type_id: 200n,
      user_id: 101n,
      sort_order: 1,
      requestType: { id: 200n, name: 'Community Tax Certificate (Cedula)' },
      user: {
        id: 101n,
        name: 'Staff One (Ana)',
        email: 'ana@speedtraqr.com',
        is_active: true,
        is_available: true,
        deleted_at: null,
      },
    };

    const handler2 = {
      id: 2n,
      request_type_id: 200n,
      user_id: 102n,
      sort_order: 2,
      requestType: { id: 200n, name: 'Community Tax Certificate (Cedula)' },
      user: {
        id: 102n,
        name: 'Staff Two (Paolo)',
        email: 'paolo@speedtraqr.com',
        is_active: true,
        is_available: true,
        deleted_at: null,
      },
    };

    const handler3 = {
      id: 3n,
      request_type_id: 200n,
      user_id: 103n,
      sort_order: 3,
      requestType: { id: 200n, name: 'Community Tax Certificate (Cedula)' },
      user: {
        id: 103n,
        name: 'Staff Three (Rosa)',
        email: 'rosa@speedtraqr.com',
        is_active: true,
        is_available: true,
        deleted_at: null,
      },
    };

    it('routes requests sequentially in round-robin fashion among 3 active/present handlers', async () => {
      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue([
        handler1,
        handler2,
        handler3,
      ] as any);

      // 1. When last assigned was Handler 1 (Ana), next request should go to Handler 2 (Paolo)
      vi.spyOn(db.documents, 'findFirst').mockResolvedValueOnce({ assigned_to: 101n } as any);
      const res1 = await resolveAutoDispatch(200);
      expect(res1?.id).toBe(102);
      expect(res1?.name).toBe('Staff Two (Paolo)');

      // 2. When last assigned was Handler 2 (Paolo), next request should go to Handler 3 (Rosa)
      vi.spyOn(db.documents, 'findFirst').mockResolvedValueOnce({ assigned_to: 102n } as any);
      const res2 = await resolveAutoDispatch(200);
      expect(res2?.id).toBe(103);
      expect(res2?.name).toBe('Staff Three (Rosa)');

      // 3. When last assigned was Handler 3 (Rosa), next request wraps around to Handler 1 (Ana)
      vi.spyOn(db.documents, 'findFirst').mockResolvedValueOnce({ assigned_to: 103n } as any);
      const res3 = await resolveAutoDispatch(200);
      expect(res3?.id).toBe(101);
      expect(res3?.name).toBe('Staff One (Ana)');
    });

    it('skips handlers who are marked absent/on leave during round-robin rotation', async () => {
      // Handler 2 (Paolo) is on leave
      const paoloOnLeave = {
        ...handler2,
        user: { ...handler2.user, is_available: false },
      };

      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue([
        handler1,
        paoloOnLeave,
        handler3,
      ] as any);

      // When last assigned was Handler 1 (Ana), next should SKIP Paolo and go to Handler 3 (Rosa)
      vi.spyOn(db.documents, 'findFirst').mockResolvedValueOnce({ assigned_to: 101n } as any);
      const res = await resolveAutoDispatch(200);
      expect(res?.id).toBe(103);
      expect(res?.name).toBe('Staff Three (Rosa)');

      // When last assigned was Handler 3 (Rosa), next wraps around to Handler 1 (Ana)
      vi.spyOn(db.documents, 'findFirst').mockResolvedValueOnce({ assigned_to: 103n } as any);
      const resNext = await resolveAutoDispatch(200);
      expect(resNext?.id).toBe(101);
      expect(resNext?.name).toBe('Staff One (Ana)');
    });

    it('empty roster fallback: returns null (routes straight into department open pool) when all handlers are absent', async () => {
      const allAbsent = [
        { ...handler1, user: { ...handler1.user, is_available: false } },
        { ...handler2, user: { ...handler2.user, is_available: false } },
        { ...handler3, user: { ...handler3.user, is_available: false } },
      ];

      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue(allAbsent as any);

      const res = await resolveAutoDispatch(200);
      expect(res).toBeNull();
    });

    it('empty roster fallback: returns null when no handlers are configured for the request type', async () => {
      vi.spyOn(db.request_type_handlers, 'findMany').mockResolvedValue([]);

      const res = await resolveAutoDispatch(200);
      expect(res).toBeNull();
    });
  });
});
