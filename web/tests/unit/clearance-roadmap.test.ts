import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';
import { checks, validate, GateError } from '@/lib/domain/status-gate';
import { approveClearance, returnToStaff } from '@/lib/domain/review-actions';
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
  smsConfigured: vi.fn(() => true),
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

    it('checks requirements and evidence before a request is submitted for review', async () => {
      const checkList = await checks({ id: 50 }, DocumentStatus.InReview, 'Has work note');
      expect(checkList.some((c) => c.key === 'requirements_verified')).toBe(true);
      expect(checkList.some((c) => c.key === 'work_evidence')).toBe(true);
    });
  });

  describe('Supervisor sign-off: approveClearance and returnToStaff', () => {
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

    it('a staff member cannot confirm their own work', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ ...mockDoc, status: DocumentStatus.InReview } as any);

      await expect(approveClearance(50, staffActor, { note: 'Approving myself' })).rejects.toThrow(
        'Only a supervisor or administrator can approve clearance.',
      );
    });

    it('confirms a submitted request: audit columns, Ready for Claim notices, note to the citizen', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ ...mockDoc, status: DocumentStatus.InReview } as any);
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      const commentSpy = vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as any);

      const result = await approveClearance(50, supervisorActor, {
        note: 'Claim at Window 3 with a valid ID.',
        claimDate: '2099-11-01',
      });

      expect(result.message).toBe('Confirmed SPD-20261025-CLEAR1. It is now Ready for Claim.');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 50n },
          data: expect.objectContaining({
            status: DocumentStatus.Completed,
            reviewed_by: 2n,
            decided_by: 2n,
            supervisor_notes: null,
          }),
        }),
      );

      expect(mailSend.queueMail).toHaveBeenCalledTimes(1);
      expect((mailSend.queueMail as any).mock.calls[0][0].to).toBe('juan@test.ph');
      expect(smsSend.queueSms).toHaveBeenCalledTimes(1);
      expect((smsSend.queueSms as any).mock.calls[0][0].text).toContain('Ready for Pickup / Claiming');

      // The public thread message carries the supervisor's note as claiming instructions.
      expect(commentSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          data: expect.objectContaining({
            visibility: 'public',
            body: expect.stringContaining('Claim at Window 3 with a valid ID.'),
          }),
        }),
      );
    });

    it('sends no SMS (and logs none) when no gateway is configured', async () => {
      vi.mocked(smsSend.smsConfigured).mockReturnValueOnce(false);
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ ...mockDoc, status: DocumentStatus.InReview } as any);
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);
      vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as any);

      await approveClearance(50, supervisorActor);

      expect(smsSend.queueSms).not.toHaveBeenCalled();
    });

    it('respects a citizen who opted out of notices', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        ...mockDoc,
        status: DocumentStatus.InReview,
        notify_citizen: false,
      } as any);
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);
      vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as any);

      await approveClearance(50, supervisorActor);

      expect(mailSend.queueMail).not.toHaveBeenCalled();
      expect(smsSend.queueSms).not.toHaveBeenCalled();
    });

    it.each([
      ['Ongoing (never submitted)', DocumentStatus.InProgress],
      ['Pending (nobody has worked on it)', DocumentStatus.Pending],
    ])('refuses to confirm a request that is %s', async (_name, status) => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ ...mockDoc, status } as any);
      const updateSpy = vi.spyOn(db.documents, 'update');

      await expect(approveClearance(50, supervisorActor)).rejects.toThrow(
        'It can be confirmed once its staff member submits it for review.',
      );
      expect(updateSpy).not.toHaveBeenCalled();
    });

    it("refuses another office's supervisor", async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        ...mockDoc,
        status: DocumentStatus.InReview,
        department_id: 9n,
      } as any);

      await expect(approveClearance(50, supervisorActor)).rejects.toThrow(
        "You can only confirm or return your own office's requests.",
      );
    });

    it('returning to staff keeps the note internal (supervisor_notes, never remarks)', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ ...mockDoc, status: DocumentStatus.InReview } as any);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as any);

      await returnToStaff(50, supervisorActor, 'Wrong venue on the permit.');

      const data = (updateSpy.mock.calls[0][0] as any).data;
      expect(data).toMatchObject({ status: DocumentStatus.InProgress, supervisor_notes: 'Wrong venue on the permit.' });
      expect(data).not.toHaveProperty('remarks');
    });
  });

  describe('Supervisor NavBadges & Clearance Queue', () => {
    it('counts submissions awaiting review in the home badge for supervisors', async () => {
      const countSpy = vi.spyOn(db.documents, 'count').mockResolvedValue(3);

      const badges = await badgesFor(supervisorActor);

      expect(badges.home).toBe(3);
      expect(countSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: expect.objectContaining({
            OR: expect.arrayContaining([
              expect.objectContaining({
                status: DocumentStatus.InReview,
              }),
            ]),
          }),
        }),
      );
    });
  });
});
