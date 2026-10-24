import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';
import { addDays, now } from '@/lib/time';

/**
 * Handing borrowed items over finishes the request. On CTCAO's line there is
 * no Approved stage any more: an Ongoing request with its required files
 * checked can be handed over, and the hand-over marks it Done and tells the
 * office — the same as Finish request.
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
  it('finishes an Ongoing request and tells the office', async () => {
    bookingFindUnique.mockResolvedValue(booking(DocumentStatus.InProgress));

    expect(await release(5, ana)).toBeNull();

    expect(documentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.objectContaining({ status: DocumentStatus.Completed }) }),
    );
    expect(notifyOfficeOfProgress).toHaveBeenCalledWith(
      expect.objectContaining({ tracking_number: 'SPD-20261024-GATE01', department_id: 8 }),
      ana,
      'finished',
    );
  });

  it('waits for the required files to be checked', async () => {
    bookingFindUnique.mockResolvedValue(booking(DocumentStatus.InProgress));
    unverifiedMandatoryRequirements.mockResolvedValue([{ id: 1, label: 'Letter of Request' }]);

    expect(await release(5, ana)).toBe('Check the required files on the request first: Letter of Request.');
    expect(bookingUpdate).not.toHaveBeenCalled();
  });

  it('waits for the request to be accepted', async () => {
    bookingFindUnique.mockResolvedValue(booking(DocumentStatus.Pending));

    expect(await release(5, ana)).toBe(
      'The request is still Pending. It must be accepted (Ongoing) before the items are handed over.',
    );
    expect(bookingUpdate).not.toHaveBeenCalled();
  });

  it('only stamps the release on a request already Done', async () => {
    bookingFindUnique.mockResolvedValue(booking(DocumentStatus.Completed));

    expect(await release(5, ana)).toBeNull();

    expect(documentUpdate).toHaveBeenCalledWith(
      expect.objectContaining({ data: expect.not.objectContaining({ status: expect.anything() }) }),
    );
    expect(notifyOfficeOfProgress).not.toHaveBeenCalled();
  });
});
