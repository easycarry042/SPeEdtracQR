import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus, citizenLabel, CITIZEN_LABELS, CITIZEN_RAIL_STAGES } from '@/lib/domain/document-status';
import { publicAuthorLabel, AUTHOR, VISIBILITY, hydrateComment } from '@/lib/domain/comments';
import { approveClearance, submitForClearance, returnToStaff } from '@/lib/domain/review-actions';
import * as mailSend from '@/lib/mail/send';
import * as smsSend from '@/lib/sms/send';
import * as comments from '@/lib/domain/comments';
import { db } from '@/lib/db';
import type { Actor } from '@/lib/auth/actor';

vi.mock('@/lib/mail/send', () => ({
  queueMail: vi.fn(),
  absoluteUrl: (url: string) => `https://speedtraqr.gov.ph${url}`,
  layout: vi.fn((opts) => `<div>${opts.heading}</div>`),
  e: (val: unknown) => String(val ?? ''),
}));

vi.mock('@/lib/sms/send', () => ({
  queueSms: vi.fn(),
  sendSms: vi.fn(),
  smsConfigured: vi.fn(() => true),
}));

vi.mock('@/lib/realtime', () => ({
  broadcastStatusUpdated: vi.fn().mockResolvedValue(undefined),
  broadcastComment: vi.fn(),
  broadcastThreadChanged: vi.fn(),
}));

vi.mock('@/lib/activity', () => ({
  logOnDocument: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/domain/status-gate', () => ({
  validate: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/domain/notifications', () => ({
  notifyMany: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/domain/document', async () => {
  const actual = await vi.importActual<typeof import('@/lib/domain/document')>('@/lib/domain/document');
  return {
    ...actual,
    unverifiedMandatoryRequirements: vi.fn().mockResolvedValue([]),
  };
});

describe('Citizen Experience & Tracking (Item 4)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Citizen-Facing Status Labels', () => {
    it('provides the exact citizen labels requested', () => {
      expect(citizenLabel(DocumentStatus.Pending)).toBe('Received & Queued');
      expect(citizenLabel(DocumentStatus.InProgress)).toBe('Processing with Office');
      expect(citizenLabel(DocumentStatus.InReview)).toBe('Under Final Review & Quality Check');
      expect(citizenLabel(DocumentStatus.Completed)).toBe('Ready for Pickup / Claiming');
    });

    it('has the 4 citizen stages in the progress rail', () => {
      expect(CITIZEN_RAIL_STAGES).toHaveLength(4);
      expect(CITIZEN_RAIL_STAGES[0].label).toBe('Received & Queued');
      expect(CITIZEN_RAIL_STAGES[1].label).toBe('Processing with Office');
      expect(CITIZEN_RAIL_STAGES[2].label).toBe('Under Final Review & Quality Check');
      expect(CITIZEN_RAIL_STAGES[3].label).toBe('Ready for Pickup / Claiming');
    });
  });

  describe('Public Legal Anonymity', () => {
    it('publicAuthorLabel attributes staff/supervisor comments to office name, never personal identity', () => {
      const staffComment = {
        id: 1,
        document_id: 10,
        author_id: 42,
        author_type: AUTHOR.Staff,
        author_name: 'Elena Marquez (Supervisor)',
        body: 'Your document is ready for claiming.',
        visibility: VISIBILITY.Public,
        parent_id: null,
        staff_read_at: null,
        citizen_read_at: null,
        attachment_path: null,
        attachment_name: null,
        created_at: new Date(),
        authorName: 'Elena Marquez',
        authorDepartmentName: 'City Tourism and Cultural Affairs Office',
      };

      const label = publicAuthorLabel(staffComment, 'City Tourism and Cultural Affairs Office');
      expect(label).toBe('City Tourism and Cultural Affairs Office');
      expect(label).not.toContain('Elena');
      expect(label).not.toContain('Marquez');
    });

    it('falls back to document department name or City Hall when author has no department', () => {
      const superAdminComment = {
        id: 2,
        document_id: 10,
        author_id: 1,
        author_type: AUTHOR.Staff,
        author_name: 'Super Admin',
        body: 'Ready for pickup.',
        visibility: VISIBILITY.Public,
        parent_id: null,
        staff_read_at: null,
        citizen_read_at: null,
        attachment_path: null,
        attachment_name: null,
        created_at: new Date(),
        authorName: 'Super Admin',
        authorDepartmentName: null,
      };

      expect(publicAuthorLabel(superAdminComment, 'Office of the Mayor')).toBe('Office of the Mayor');
      expect(publicAuthorLabel(superAdminComment, null)).toBe('City Hall');
    });
  });

  describe('Claim Notification Dispatch on Supervisor Approval', () => {
    const supervisorActor = {
      id: 2,
      name: 'Maria Santos',
      email: 'maria.santos@speedtraqr.com',
      departmentId: 1,
      roles: ['Supervisor'],
      permissions: ['assign documents', 'view department documents'],
      can: (perm: string) => ['assign documents', 'view department documents'].includes(perm),
      hasRole: (role: string) => role === 'Supervisor',
    } as unknown as Actor;

    const mockDoc = {
      id: 100n,
      tracking_number: 'SPD-20261025-CLMRDY',
      document_type: 'Business Permit',
      status: DocumentStatus.InReview,
      assigned_to: 5n,
      accepted_at: new Date(),
      department_id: 1n,
      department: { name: 'City Tourism and Cultural Affairs Office' },
      citizen_name: 'Juan Dela Cruz',
      citizen_email: 'juan@example.com',
      citizen_contact: '09171234567',
      notify_citizen: true,
      created_at: new Date(),
      claim_date: new Date('2026-10-30'),
      remarks: null,
    };

    it('dispatches claim notifications via Email, SMS, and Speedy message thread ONLY upon supervisor approval', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(mockDoc as any);
      vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      const commentCreateSpy = vi.spyOn(db.document_comments, 'create').mockResolvedValue({
        id: 1n,
        document_id: 100n,
        body: 'test',
        visibility: VISIBILITY.Public,
        author_type: AUTHOR.Staff,
        created_at: new Date(),
      } as any);

      await approveClearance(100, supervisorActor, {
        claimDate: '2026-10-30',
        note: 'Verified requirements and approved release.',
      });

      // 1. Email claim notification
      expect(mailSend.queueMail).toHaveBeenCalledTimes(1);
      const mailCall = (mailSend.queueMail as any).mock.calls[0][0];
      expect(mailCall.to).toBe('juan@example.com');
      expect(mailCall.subject).toContain('ready for pickup / claiming');

      // 2. SMS claim notification
      expect(smsSend.queueSms).toHaveBeenCalledTimes(1);
      const smsCall = (smsSend.queueSms as any).mock.calls[0][0];
      expect(smsCall.to).toBe('09171234567');
      expect(smsCall.text).toContain('Ready for Pickup / Claiming');
      expect(smsCall.text).toContain('SPD-20261025-CLMRDY');

      // 3. Speedy message thread (public visibility)
      expect(commentCreateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          data: expect.objectContaining({
            document_id: 100n,
            author_type: AUTHOR.Staff,
            visibility: VISIBILITY.Public,
            body: expect.stringContaining('Ready for Pickup / Claiming'),
          }),
        }),
      );
    });

    it('does NOT dispatch claim notifications when staff submits for clearance', async () => {
      const staffActor = {
        id: 5,
        name: 'Ana Cruz',
        email: 'ana.cruz@speedtraqr.com',
        departmentId: 1,
        roles: ['staff'],
        permissions: ['advance documents'],
        can: (perm: string) => perm === 'advance documents',
        hasRole: (role: string) => role === 'staff',
      } as unknown as Actor;

      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        ...mockDoc,
        status: DocumentStatus.InProgress,
      } as any);
      vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 2n } as any);

      await submitForClearance(100, staffActor, 'Ready for supervisor review');

      expect(mailSend.queueMail).not.toHaveBeenCalled();
      expect(smsSend.queueSms).not.toHaveBeenCalled();
    });

    it('does NOT dispatch claim notifications when supervisor returns request for revision', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(mockDoc as any);
      vi.spyOn(db.documents, 'update').mockResolvedValue({} as any);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 3n } as any);

      await returnToStaff(100, supervisorActor, 'Missing valid ID attachment');

      expect(mailSend.queueMail).not.toHaveBeenCalled();
      expect(smsSend.queueSms).not.toHaveBeenCalled();
    });
  });
});
