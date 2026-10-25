import { beforeEach, describe, expect, it, vi } from 'vitest';
import { assign, releaseToPool } from '@/lib/domain/review-actions';
import { DocumentStatus } from '@/lib/domain/document-status';
import { db } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';

const mockBroadcast = vi.fn().mockResolvedValue(undefined);
const mockLogOnDocument = vi.fn().mockResolvedValue(undefined);
const mockLogSystemComment = vi.fn().mockResolvedValue(undefined);
const mockNotifyMany = vi.fn().mockResolvedValue(undefined);

vi.mock('@/lib/realtime', () => ({
  broadcastStatusUpdated: (...args: any[]) => mockBroadcast(...args),
}));

vi.mock('@/lib/activity', () => ({
  logOnDocument: (...args: any[]) => mockLogOnDocument(...args),
  logOnResource: vi.fn(),
  logActivity: vi.fn(),
}));

vi.mock('@/lib/domain/comments', () => ({
  logSystemComment: (...args: any[]) => mockLogSystemComment(...args),
  postComment: vi.fn(),
  AUTHOR: { STAFF: 'staff' },
  VISIBILITY: { INTERNAL: 'internal' },
}));

vi.mock('@/lib/domain/notifications', () => ({
  notify: vi.fn().mockResolvedValue(undefined),
  notifyMany: (...args: any[]) => mockNotifyMany(...args),
}));

vi.mock('@/lib/auth/rbac', () => ({
  ROLE_SUPERVISOR: 'Supervisor',
  ROLE_SUPER_ADMIN: 'super_admin',
  usersWithRole: vi.fn().mockResolvedValue([{ id: 2, name: 'Supervisor Maria', email: 'maria@example.com' }]),
  grantsFor: vi.fn().mockResolvedValue({ permissions: ['advance documents'] }),
}));

describe('Manual Safety Valves (Item 4)', () => {
  const staffActor = {
    id: 11,
    name: 'Ana Cruz',
    email: 'ana.cruz@speedtraqr.com',
    role: 'staff',
    departmentId: 1,
    can: (p: string) => p === 'advance documents',
    hasRole: (r: string) => r === 'staff',
    isSuperAdmin: () => false,
    isSupervisor: () => false,
  } as unknown as Actor;

  const otherStaffActor = {
    id: 12,
    name: 'Paolo Mendoza',
    email: 'paolo.mendoza@speedtraqr.com',
    role: 'staff',
    departmentId: 1,
    can: (p: string) => p === 'advance documents',
    hasRole: (r: string) => r === 'staff',
    isSuperAdmin: () => false,
    isSupervisor: () => false,
  } as unknown as Actor;

  const supervisorActor = {
    id: 2,
    name: 'Maria Santos',
    email: 'maria.santos@speedtraqr.com',
    role: 'Supervisor',
    departmentId: 1,
    can: (p: string) => p === 'assign documents' || p === 'view all documents',
    hasRole: (r: string) => r === 'Supervisor',
    isSuperAdmin: () => false,
    isSupervisor: () => true,
  } as unknown as Actor;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Staff "Return to Pool" Action', () => {
    const activeDoc = {
      id: 50n,
      tracking_number: 'SPD-20261025-POOL01',
      document_type: 'Community Tax Certificate (Cedula)',
      status: DocumentStatus.InProgress,
      department_id: 1n,
      assigned_to: 11n,
      assigned_by: 2n,
      assigned_at: new Date(),
      accepted_at: new Date(),
      deleted_at: null,
    };

    it('allows assigned staff to release their task to the office pool when leaving for field work or emergency', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(activeDoc as any);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);

      const result = await releaseToPool(50, staffActor, 'Leaving for field inspection at Barangay 5');

      expect(result.message).toContain('Released SPD-20261025-POOL01 back to the office pool');

      // Unbinds assigned_to, resets accepted_at and moves status to Pending (open pool)
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 50n },
          data: expect.objectContaining({
            assigned_to: null,
            assigned_by: null,
            assigned_at: null,
            accepted_at: null,
            status: DocumentStatus.Pending,
          }),
        }),
      );

      // Records internal audit log note
      expect(mockLogOnDocument).toHaveBeenCalledWith(
        50,
        expect.stringContaining('Released to office pool: Leaving for field inspection'),
        11,
      );

      // Records internal system comment
      expect(mockLogSystemComment).toHaveBeenCalledWith(
        50,
        expect.stringContaining('Returned to unassigned pool for peers to pick up'),
      );

      // Realtime update broadcast
      expect(mockBroadcast).toHaveBeenCalledWith(50);

      // Alerts office supervisors
      expect(mockNotifyMany).toHaveBeenCalledWith(
        [2],
        expect.objectContaining({
          event: 'released_to_pool',
          title: expect.stringContaining('Request returned to pool'),
          body: expect.stringContaining('Ana Cruz released'),
        }),
      );
    });

    it('rejects release attempts by an unassigned colleague who lacks supervisor permissions', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(activeDoc as any);

      await expect(
        releaseToPool(50, otherStaffActor, 'I am not assigned to this'),
      ).rejects.toThrow('Only the assigned staff member or an office supervisor can release this request');
    });

    it('allows office supervisor to release a stalled request to the pool', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(activeDoc as any);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);

      const result = await releaseToPool(50, supervisorActor, 'Supervisor returning stalled request to open pool');
      expect(result.message).toContain('Released SPD-20261025-POOL01 back to the office pool');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          data: expect.objectContaining({ assigned_to: null, status: DocumentStatus.Pending }),
        }),
      );
    });

    it('rejects releasing terminal requests', async () => {
      const completedDoc = { ...activeDoc, status: DocumentStatus.Completed };
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(completedDoc as any);

      await expect(
        releaseToPool(50, staffActor, 'Attempting to release completed'),
      ).rejects.toThrow('Terminal requests cannot be released to the pool');
    });
  });

  describe('Supervisor One-Click / Two-Click Reassignment', () => {
    const stalledDoc = {
      id: 55n,
      tracking_number: 'SPD-20261025-REASSIGN01',
      document_type: 'Business Permit',
      status: DocumentStatus.InProgress,
      department_id: 1n,
      assigned_to: 11n,
      assigned_by: 2n,
      assigned_at: new Date(),
      accepted_at: new Date(),
      deleted_at: null,
    };

    const newStaffUser = {
      id: 12n,
      name: 'Paolo Mendoza',
      email: 'paolo.mendoza@speedtraqr.com',
      department_id: 1n,
      is_active: true,
      is_available: true,
      deleted_at: null,
    };

    const prevStaffUser = {
      id: 11n,
      name: 'Ana Cruz',
      email: 'ana.cruz@speedtraqr.com',
      department_id: 1n,
      is_active: true,
      is_available: false, // Called in sick
      deleted_at: null,
    };

    it('reassigns stalled request to an available colleague and notifies both parties', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(stalledDoc as any);
      vi.spyOn(db.users, 'findUnique').mockResolvedValue(prevStaffUser as any);
      vi.spyOn(db.users, 'findFirst').mockResolvedValue(newStaffUser as any);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);

      const result = await assign(55, supervisorActor, 12);

      expect(result.message).toContain('Reassigned SPD-20261025-REASSIGN01 to Paolo Mendoza');

      // Updates assigned_to, resets accepted_at
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 55n },
          data: expect.objectContaining({
            assigned_to: 12n,
            assigned_by: 2n,
            accepted_at: null,
          }),
        }),
      );

      // Audit trail logs reassignment from Ana to Paolo
      expect(mockLogOnDocument).toHaveBeenCalledWith(
        55,
        'Reassigned from Ana Cruz to Paolo Mendoza',
        2,
      );

      // System comment notes supervisor action
      expect(mockLogSystemComment).toHaveBeenCalledWith(
        55,
        expect.stringContaining('Reassigned by Maria Santos from Ana Cruz to Paolo Mendoza'),
      );

      // Notifies the new assignee (Paolo)
      expect(mockNotifyMany).toHaveBeenCalledWith(
        [12],
        expect.objectContaining({
          event: 'assigned',
          title: expect.stringContaining('Reassigned to you'),
          body: expect.stringContaining('Maria Santos assigned you'),
        }),
      );

      // Notifies the previous assignee (Ana) that the request was transferred
      expect(mockNotifyMany).toHaveBeenCalledWith(
        [11],
        expect.objectContaining({
          event: 'reassigned',
          title: expect.stringContaining('Assignment updated'),
          body: expect.stringContaining('reassigned Business Permit to Paolo Mendoza'),
        }),
      );

      // Broadcasts status update
      expect(mockBroadcast).toHaveBeenCalledWith(55);
    });
  });
});
