import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';
import { DocumentStatus } from '@/lib/domain/document-status';
import { claimDatePolicyFor } from '@/lib/domain/request-kinds';
import { db } from '@/lib/db';
import { today, toDateString } from '@/lib/time';

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

vi.mock('@/lib/domain/office-progress', () => ({
  notifyOfficeOfProgress: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/lib/mail/send', () => ({
  queueMail: vi.fn(),
  absoluteUrl: (url: string) => `https://speedtraqr.gov.ph${url}`,
  layout: vi.fn((opts) => `<div>${opts.heading}</div>`),
  e: (val: unknown) => String(val ?? ''),
}));

vi.mock('@/lib/sms/send', () => ({
  queueSms: vi.fn(),
  smsConfigured: vi.fn(() => false),
}));

vi.mock('@/lib/storage', () => ({
  publicObjectUrl: vi.fn(() => null),
  readPublic: vi.fn().mockResolvedValue(null),
}));

const { checks } = await import('@/lib/domain/status-gate');
const { acceptAssignment, setClaimDate } = await import('@/lib/domain/review-actions');
const { GET: getStorage } = await import('@/app/api/storage/[...path]/route');

const bea = {
  id: 12,
  name: 'Bea Santiago',
  email: 'bea.santiago@speedtraqr.com',
  departmentId: 1,
  can: (perm: string) => perm === 'advance documents',
  hasRole: (role: string) => role === 'staff',
} as unknown as Actor;

const supervisor = {
  id: 2,
  name: 'Maria Santos',
  email: 'maria.santos@speedtraqr.com',
  departmentId: 1,
  can: (perm: string) => perm === 'assign documents' || perm === 'advance documents',
  hasRole: (role: string) => role === 'Supervisor',
} as unknown as Actor;

describe('Pre-defense fixes (Issues 1–6)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Claiming date policy per CTCAO request kind', () => {
    it.each([
      ['booking', 'City Plaza Reservation', 'optional'],
      ['booking', 'Gateway Park Reservation', 'optional'],
      ['equipment', 'Spider Banner Borrowing', 'required'],
      ['equipment', 'Philippine Flag Borrowing', 'required'],
      ['equipment', 'San Pedro Seal Flag Borrowing', 'required'],
      ['service', 'Philippine Flag Requisition', 'required'],
      ['service', 'Lei Making', 'none'],
      ['service', 'Layout Request', 'none'],
      ['service', 'Social Media Posting', 'none'],
      ['service', 'Events Set-up', 'none'],
      ['service', 'Program Assistance', 'none'],
      ['service', 'Other Venue Request', 'none'],
    ] as const)('%s / %s -> %s', (kind, name, expected) => {
      expect(claimDatePolicyFor(kind, name)).toBe(expected);
    });
  });

  describe('setClaimDate enforcement by request kind', () => {
    const baseDoc = {
      id: 77n,
      tracking_number: 'SPD-20261126-TEST77',
      document_type: 'City Plaza Reservation',
      status: DocumentStatus.InProgress,
      assigned_to: 12n,
      accepted_at: new Date(),
      department_id: 1n,
      department: { name: 'City Tourism, Culture and Arts Office' },
      citizen_name: 'Juan',
      citizen_email: null,
      citizen_contact: null,
      notify_citizen: false,
      created_at: new Date(),
      claim_date: new Date(),
      remarks: null,
    };

    it('allows clearing the optional claiming date for a facility reservation', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(baseDoc as never);
      vi.spyOn(db.request_types, 'findFirst').mockResolvedValue({ kind: 'booking' } as never);
      const updateSpy = vi.spyOn(db.documents, 'update').mockResolvedValue({} as never);

      const res = await setClaimDate(77, bea, null);

      expect(res.message).toBe('Claiming date cleared.');
      expect(updateSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 77n },
          data: expect.objectContaining({ claim_date: null }),
        }),
      );
    });

    it('requires a claiming date for equipment borrowing', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        ...baseDoc,
        document_type: 'Spider Banner Borrowing',
      } as never);
      vi.spyOn(db.request_types, 'findFirst').mockResolvedValue({ kind: 'equipment' } as never);

      await expect(setClaimDate(77, bea, '')).rejects.toThrow('Pick a claiming date.');
    });

    it('refuses setting a claiming date on service requests without pickup (e.g. Lei Making)', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        ...baseDoc,
        document_type: 'Lei Making',
      } as never);
      vi.spyOn(db.request_types, 'findFirst').mockResolvedValue({ kind: 'service' } as never);

      await expect(
        setClaimDate(77, supervisor, toDateString(today())),
      ).rejects.toThrow('This service request does not use a claiming date.');
    });
  });

  describe('StatusGate claiming date check before Pending Approval', () => {
    it('requires a claiming date for Philippine Flag Requisition but not City Plaza Reservation', async () => {
      vi.spyOn(db.document_requirements, 'findMany').mockResolvedValue([]);
      vi.spyOn(db.bookings, 'findFirst').mockResolvedValue(null);

      vi.spyOn(db.documents, 'findUnique').mockResolvedValueOnce({
        document_type: 'Philippine Flag Requisition',
        claim_date: null,
      } as never);
      vi.spyOn(db.request_types, 'findFirst').mockResolvedValueOnce({ kind: 'service' } as never);
      const reqChecks = await checks({ id: 77 }, DocumentStatus.InReview, null);
      expect(reqChecks.find((c) => c.key === 'claim_date_set')?.passed).toBe(false);

      vi.spyOn(db.documents, 'findUnique').mockResolvedValueOnce({
        document_type: 'City Plaza Reservation',
        claim_date: null,
      } as never);
      vi.spyOn(db.request_types, 'findFirst').mockResolvedValueOnce({ kind: 'booking' } as never);
      const facChecks = await checks({ id: 77 }, DocumentStatus.InReview, null);
      expect(facChecks.find((c) => c.key === 'claim_date_set')).toBeUndefined();
      expect(facChecks.every((c) => c.passed)).toBe(true);
    });
  });

  describe('Co-handler visibility & acceptance (Issue 6)', () => {
    it('allows a co-handler (Bea) to accept an unaccepted City Plaza request initially routed to Ana', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({
        id: 88n,
        tracking_number: 'SPD-20261126-PLAZA2',
        document_type: 'City Plaza Reservation',
        status: DocumentStatus.Pending,
        assigned_to: 11n, // Ana Cruz
        accepted_at: null,
        department_id: 1n,
        department: { name: 'City Tourism, Culture and Arts Office' },
        citizen_name: 'Francisco',
        citizen_email: null,
        citizen_contact: null,
        notify_citizen: false,
        created_at: new Date(),
        claim_date: null,
        remarks: null,
      } as never);
      vi.spyOn(db.request_type_handlers, 'findFirst').mockResolvedValue({ id: 5n } as never);
      const updateManySpy = vi.spyOn(db.documents, 'updateMany').mockResolvedValue({ count: 1 } as never);
      vi.spyOn(db.document_comments, 'create').mockResolvedValue({ id: 1n } as never);

      const res = await acceptAssignment(88, bea);

      expect(res.message).toBe('Accepted SPD-20261126-PLAZA2. It is now Ongoing.');
      expect(updateManySpy).toHaveBeenCalledWith(
        expect.objectContaining({
          where: { id: 88n, accepted_at: null },
          data: expect.objectContaining({
            assigned_to: 12n,
            status: DocumentStatus.InProgress,
          }),
        }),
      );
    });
  });

  describe('QR storage regeneration fallback (Issue 1)', () => {
    it('renders a valid QR PNG when file is missing on storage but tracking exists in DB', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue({ id: 99n } as never);

      const res = await getStorage(
        new Request('http://localhost/storage/qrcodes/INT-20261126-THHP9B.png?inline=1&regenerate=1') as never,
        { params: Promise.resolve({ path: ['qrcodes', 'INT-20261126-THHP9B.png'] }) },
      );

      expect(res.status).toBe(200);
      expect(res.headers.get('content-type')).toBe('image/png');
      const buf = Buffer.from(await res.arrayBuffer());
      expect(buf.subarray(0, 4)).toEqual(Buffer.from([0x89, 0x50, 0x4e, 0x47]));
    });

    it('returns 404 when the tracking number does not exist in DB', async () => {
      vi.spyOn(db.documents, 'findFirst').mockResolvedValue(null);

      const res = await getStorage(
        new Request('http://localhost/storage/qrcodes/INT-20261126-ZZZZ99.png?inline=1&regenerate=1') as never,
        { params: Promise.resolve({ path: ['qrcodes', 'INT-20261126-ZZZZ99.png'] }) },
      );

      expect(res.status).toBe(404);
    });
  });
});
