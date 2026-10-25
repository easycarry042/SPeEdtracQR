import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';
import { addDays, now } from '@/lib/time';

/**
 * Handing borrowed items over is the citizen claiming them, so it waits for
 * the supervisor's confirmation (Ready for Claim). It stamps the claim and
 * never moves the status itself.
 */
const bookingFindUnique = vi.fn();
const bookingUpdate = vi.fn();
const documentUpdate = vi.fn();
const unverifiedMandatoryRequirements = vi.fn();
const notifyOfficeOfProgress = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    bookings: { findUnique: bookingFindUnique, update: bookingUpdate },
    documents: { update: documentUpdate },
  },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/domain/document', async () => ({
  ...(await vi.importActual<typeof import('@/lib/domain/document')>('@/lib/domain/document')),
  unverifiedMandatoryRequirements,
}));
vi.mock('@/lib/domain/office-progress', () => ({ notifyOfficeOfProgress }));
vi.mock('@/lib/activity', () => ({ logOnDocument: vi.fn(), logOnResource: vi.fn() }));
vi.mock('@/lib/realtime', () => ({ broadcastStatusUpdated: vi.fn() }));
vi.mock('@/lib/domain/comments', () => ({ logSystemComment: vi.fn() }));
vi.mock('@/lib/mail/send', () => ({ queueMail: vi.fn() }));

const { release } = await import('@/lib/domain/lending-desk');

const ana = { id: 41, name: 'Ana Cruz' };

function booking(documentStatus: DocumentStatus) {
  const pickup = now();

  return {
    id: 5n,
    document_id: 30n,
    resource_id: 9n,
    quantity: 1,
    starts_at: pickup,
    ends_at: addDays(pickup, 3),
    status: 'approved',
    released_at: null,
    returned_at: null,
    returned_quantity: 0,
    damaged_quantity: 0,
    lost_quantity: 0,
    return_notes: null,
    // A facility, so the shelf count (equipment only) stays out of this test.
    resource: { id: 9n, name: 'Gateway Park', kind: 'facility', total_quantity: null, damaged_quantity: 0, capacity: null },
    document: {
      id: 30n,
      tracking_number: 'SPD-20261024-GATE01',
      document_type: 'Gateway Park Reservation',
      department_id: 8n,
      status: documentStatus,
      quantity: null,
      assigned_to: 41n,
      assigned_at: now(),
      accepted_at: now(),
      claimed_at: null,
      released_by: null,
      citizen_name: 'Juan Dela Cruz',
      citizen_email: null,
      notify_citizen: false,
      deleted_at: null,
      assignee: { name: 'Ana Cruz' },
    },
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  unverifiedMandatoryRequirements.mockResolvedValue([]);
});

describe('handing the items over', () => {
  it.each([
    ['Pending', DocumentStatus.Pending],
    ['Ongoing', DocumentStatus.InProgress],
    ['Pending Approval', DocumentStatus.InReview],
  ])('waits for the supervisor to confirm a request that is %s', async (_name, status) => {
    bookingFindUnique.mockResolvedValue(booking(status));

    expect(await release(5, ana)).toMatch(/Items are handed over once a supervisor confirms it as Ready for Claim\.$/);
    expect(bookingUpdate).not.toHaveBeenCalled();
    expect(documentUpdate).not.toHaveBeenCalled();
  });

  it('hands over a Ready for Claim request: stamps the claim, leaves the status alone', async () => {
    bookingFindUnique.mockResolvedValue(booking(DocumentStatus.Completed));

    expect(await release(5, ana)).toBeNull();

    expect(bookingUpdate).toHaveBeenCalled();
    expect(documentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({ claimed_at: expect.any(Date), released_by: 41n }),
      }),
    );
    expect(documentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.not.objectContaining({ status: expect.anything() }) }),
    );
    // The office already heard at confirmation; handing over is not a second "done".
    expect(notifyOfficeOfProgress).not.toHaveBeenCalled();
  });
});
