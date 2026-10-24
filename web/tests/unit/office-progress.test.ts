import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The "notify the admin" boxes on CTCAO's workflow: the office's supervisors
 * hear when a request is accepted and when it is finished — bell and email,
 * never the person who made the move.
 */
const departmentTriagers = vi.fn();
const notifyMany = vi.fn();
const queueMail = vi.fn();

vi.mock('@/lib/db', () => ({ toId: (value: bigint | number) => Number(value) }));
vi.mock('@/lib/domain/triagers', () => ({ departmentTriagers }));
vi.mock('@/lib/domain/notifications', () => ({ notifyMany }));
vi.mock('@/lib/mail/send', async () => ({
  ...(await vi.importActual<typeof import('@/lib/mail/send')>('@/lib/mail/send')),
  queueMail,
}));

const { notifyOfficeOfProgress } = await import('@/lib/domain/office-progress');

const request = { tracking_number: 'SPD-20261024-LEI7MK', document_type: 'Lei Making', department_id: 8n };
const ana = { id: 41, name: 'Ana Cruz' };

beforeEach(() => {
  vi.clearAllMocks();
  departmentTriagers.mockResolvedValue([
    { id: 2, name: 'Maria Santos', email: 'maria.santos@speedtraqr.com', department_id: 8 },
    { id: 3, name: 'Carmela Torres', email: 'carmela.torres@speedtraqr.com', department_id: 8 },
  ]);
});

describe('notifyOfficeOfProgress', () => {
  it('bells and emails the office supervisors when a request is accepted', async () => {
    const told = await notifyOfficeOfProgress(request, ana, 'accepted');

    expect(departmentTriagers).toHaveBeenCalledWith(8, 41);
    expect(told).toEqual([2, 3]);
    expect(notifyMany).toHaveBeenCalledWith(
      [2, 3],
      expect.objectContaining({
        event: 'accepted',
        title: 'Accepted: SPD-20261024-LEI7MK',
        body: 'Ana Cruz accepted Lei Making. It is now Ongoing.',
        url: '/dashboard?tab=inprogress&open=SPD-20261024-LEI7MK',
      }),
    );
    expect(queueMail).toHaveBeenCalledTimes(2);
    expect(queueMail.mock.calls[0][0]).toMatchObject({
      to: 'maria.santos@speedtraqr.com',
      subject: 'Accepted: SPD-20261024-LEI7MK is now Ongoing',
    });
  });

  it('says Done when it is finished, and links to the Done tab', async () => {
    await notifyOfficeOfProgress(request, ana, 'finished');

    expect(notifyMany).toHaveBeenCalledWith(
      [2, 3],
      expect.objectContaining({
        event: 'finished',
        title: 'Done: SPD-20261024-LEI7MK',
        url: '/dashboard?tab=completed&open=SPD-20261024-LEI7MK',
      }),
    );
    expect(queueMail.mock.calls[1][0]).toMatchObject({ subject: 'Done: SPD-20261024-LEI7MK' });
  });

  it('does nothing when there is nobody to tell', async () => {
    departmentTriagers.mockResolvedValue([]);

    expect(await notifyOfficeOfProgress(request, ana, 'finished')).toEqual([]);
    expect(notifyMany).not.toHaveBeenCalled();
    expect(queueMail).not.toHaveBeenCalled();
  });
});
