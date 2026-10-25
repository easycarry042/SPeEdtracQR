import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';
import { checks, validate, GateError } from '@/lib/domain/status-gate';
import { finish, confirmReview, ActionError } from '@/lib/domain/status-actions';
import { badgesFor } from '@/lib/domain/nav-badges';
import { db } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';
import * as mailSend from '@/lib/mail/send';
import * as smsSend from '@/lib/sms/send';

vi.mock('@/lib/realtime', () => ({
  broadcastStatusUpdated: vi.fn().mockResolvedValue(undefined),
  broadcastComment: vi.fn(),
  broadcastThreadChanged: vi.fn(),
}));

vi.mock('@/lib/activity', () => ({
  logOnDocument: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/domain/notifications', () => ({
  notifyMany: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/mail/send', () => ({
  queueMail: vi.fn(),
  absoluteUrl: (url: string) => `https://speedtraqr.gov.ph${url}`,
  layout: vi.fn((opts) => `<div>${opts.heading}</div>`),
  e: (val: unknown) => String(val ?? ''),
}));

vi.mock('@/lib/sms/send', () => ({
  queueSms: vi.fn(),
}));

vi.mock('@/lib/domain/document', async () => {
  const actual = await vi.importActual<typeof import('@/lib/domain/document')>('@/lib/domain/document');
  return {
    ...actual,
    unverifiedMandatoryRequirements: vi.fn().mockResolvedValue([]),
  };
});

describe('Implementation Roadmap (Item 5)', () => {
  const staffActor = {
    id: 10,
    name: 'Ana Cruz',
    email: 'ana.cruz@speedtraqr.com',
    role: 'staff',
    departmentId: 1,
    can: (perm: string) => perm === 'advance documents',
    hasRole: (role: string) => role === 'staff',
  } as unknown as Actor;

  const supervisorActor = {
    id: 2,
    name: 'Maria Santos',
    email: 'maria.santos@speedtraqr.com',
    role: 'Supervisor',
    departmentId: 1,
    can: (perm: string) => perm === 'assign documents' || perm === 'review documents' || perm === 'advance documents',
    hasRole: (role: string) => role === 'Supervisor',
  } as unknown as Actor;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('StatusGate Validation & Clearance Checks', () => {
    it('blocks staff from advancing to Completed directly', async () => {
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);

      const checkList = await checks({ id: 50 }, DocumentStatus.Completed, 'Done', staffActor);
      const clearanceCheck = checkList.find((c) => c.key === 'supervisor_clearance');

      expect(clearanceCheck).toBeDefined();
      expect(clearanceCheck?.passed).toBe(false);

      await expect(validate({ id: 50 }, DocumentStatus.Completed, 'Done', staffActor)).rejects.toThrow(
        GateError,
      );
    });

    it('permits supervisor to pass Completed checks', async () => {
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);

      const checkList = await checks({ id: 50 }, DocumentStatus.Completed, 'Approved note', supervisorActor);
      const clearanceCheck = checkList.find((c) => c.key === 'supervisor_clearance');

      expect(clearanceCheck).toBeDefined();
      expect(clearanceCheck?.passed).toBe(true);

      await expect(validate({ id: 50 }, DocumentStatus.Completed, 'Approved note', supervisorActor)).resolves.not.toThrow();
    });

    it('checks requirements and evidence for UnderReview and PendingClearance', async () => {
      const checkListUnderReview = await checks({ id: 50 }, DocumentStatus.UnderReview, 'Has work note');
      expect(checkListUnderReview.some((c) => c.key === 'requirements_verified')).toBe(true);
      expect(checkListUnderReview.some((c) => c.key === 'work_evidence')).toBe(true);

      const checkListPendingClearance = await checks({ id: 50 }, DocumentStatus.PendingClearance, 'Has work note');
      expect(checkListPendingClearance.some((c) => c.key === 'requirements_verified')).toBe(true);
      expect(checkListPendingClearance.some((c) => c.key === 'work_evidence')).toBe(true);
    });
  });

  describe('Backend Gateways & Actions: finish vs confirmReview', () => {
    const mockDoc = {
      id: 50n,
      tracking_number: 'SPD-20261025-CLEAR1',
      document_type: 'Travel Clearance',
      status: DocumentStatus.InProgress,
      assigned_to: 10n,
      accepted_at: new Date(),
      department_id: 1n,
      department: { name: 'City Tourism and Cultural Affairs Office' },
      citizen_name: 'Juan Citizen',
      citizen_email: 'juan@test.ph',
      citizen_contact: '09181234567',
      notify_citizen: true,
      created_at: new Date(),
      claim_date: null,
      remarks: null,
    };

    it('finish() rejects staff callers with a 403 guidance error', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(mockDoc as any);

      await expect(
        finish(50, staffActor, { expectedStatus: DocumentStatus.InProgress, note: 'Finishing directly' }),
      ).rejects.toThrow(
        'Staff cannot directly complete requests. Please submit for supervisor review instead.',
      );
    });

    it('confirmReview() updates audit columns (reviewed_by, reviewed_at, supervisor_notes) and completes request', async () => {
      const docInReview = {
        ...mockDoc,
        status: DocumentStatus.InReview,
      };
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(docInReview as any);
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      const commentSpy = vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as any);

      const result = await confirmReview(50, supervisorActor, {
        note: 'All identity proofs verified and authorized.',
        claimDate: '2026-11-01',
      });

      expect(result.status).toBe(DocumentStatus.Completed);
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 50n },
          data: expect.objectContaining({
            status: DocumentStatus.Completed,
            reviewed_by: 2n,
            supervisor_notes: 'All identity proofs verified and authorized.',
          }),
        }),
      );

      // Verifies claim alert was dispatched
      expect(mailSend.queueMail).toHaveBeenCalledTimes(1);
      const mailCall = (mailSend.queueMail as any).mock.calls[0][0];
      expect(mailCall.to).toBe('juan@test.ph');
      expect(mailCall.subject).toContain('ready for pickup / claiming');

      expect(smsSend.queueSms).toHaveBeenCalledTimes(1);
      const smsCall = (smsSend.queueSms as any).mock.calls[0][0];
      expect(smsCall.to).toBe('09181234567');
      expect(smsCall.text).toContain('Ready for Pickup / Claiming');

      expect(commentSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          data: expect.objectContaining({
            body: expect.stringContaining('Ready for Pickup / Claiming'),
          }),
        }),
      );
    });

    it('confirmReview() rejects non-supervisor actors with 403', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(mockDoc as any);

      await expect(
        confirmReview(50, staffActor, { note: 'Trying to approve myself' }),
      ).rejects.toThrow('Only a supervisor or department administrator can confirm clearance.');
    });
  });

  describe('Supervisor NavBadges & Clearance Queue', () => {
    it('counts documents in InReview, UnderReview, and PendingClearance in home badge for supervisors', async () => {
      const countSpy = vi.spyOn(db.documents, 'count').mockResolvedValue(3);

      const badges = await badgesFor(supervisorActor);

      expect(badges.home).toBe(3);
      expect(countSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: expect.objectContaining({
            OR: expect.arrayContaining([
              expect.objectContaining({
                status: {
                  in: [DocumentStatus.InReview, DocumentStatus.UnderReview, DocumentStatus.PendingClearance],
                },
              }),
            ]),
          }),
        }),
      );
    });
  });
});
