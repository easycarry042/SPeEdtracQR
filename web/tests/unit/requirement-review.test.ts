import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * Port of `RequirementOpenAndCheckTest`.
 *
 * The rule being guarded: an uploaded file must be OPENED by someone who can
 * check it before it can be marked "Looks good", and only an open through the
 * View link counts. Sending a file back needs a reason, emails the citizen,
 * and withdraws any earlier approval — otherwise a returned file could still
 * leave the request advanceable.
 */
const findFirst = vi.fn();
const update = vi.fn();
const updateMany = vi.fn();
const logOnDocument = vi.fn();
const queueMail = vi.fn();

vi.mock('@/lib/db', () => ({
  db: { document_requirements: { findFirst, update, updateMany } },
  toId: (value: bigint | number) => Number(value),
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/activity', () => ({ logOnDocument }));
vi.mock('@/lib/mail/send', async (original) => ({
  ...(await original<typeof import('@/lib/mail/send')>()),
  queueMail,
}));

const review = await import('@/lib/domain/requirement-review');

const ASSIGNEE = 5;

function actor(overrides: Partial<Actor> = {}): Actor {
  return {
    id: ASSIGNEE,
    name: 'Ana Staff',
    can: (permission: string) => permission === 'advance documents',
    hasRole: () => false,
    ...overrides,
  } as unknown as Actor;
}

function requirement(overrides: Record<string, unknown> = {}) {
  return {
    id: 11n,
    label: 'Cedula',
    is_mandatory: true,
    review_status: 'pending',
    review_comment: null,
    uploaded_file_path: 'requirements/abc.pdf',
    opened_at: null,
    opened_by: null,
    verified_at: null,
    verified_by: null,
    opener: null,
    verifier: null,
    document: {
      tracking_number: 'SPD-20261023-ABC234',
      assigned_to: BigInt(ASSIGNEE),
      created_by: 2n,
      citizen_name: 'Juan',
      citizen_email: 'juan@example.com',
    },
    ...overrides,
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  update.mockImplementation(async ({ data }) => ({ ...requirement(), ...data }));
  updateMany.mockResolvedValue({ count: 1 });
});

describe('payload', () => {
  it('gives the View link ?open=1 and the thumbnail the plain stream', () => {
    const payload = review.requirementPayload(19, requirement());

    expect(payload.url).toBe('/documents/19/requirements/11/file');
    expect(payload.openUrl).toBe('/documents/19/requirements/11/file?open=1');
    expect(payload.opened).toBe(false);
    expect(payload.ext).toBe('pdf');
  });

  it('counts a requirement with nothing uploaded as opened (checked at the counter)', () => {
    const payload = review.requirementPayload(19, requirement({ uploaded_file_path: null }));

    expect(payload.opened).toBe(true);
    expect(payload.url).toBeNull();
    expect(payload.openUrl).toBeNull();
  });
});

describe('markOpened', () => {
  it('stamps the first open by the person who can check it', async () => {
    findFirst.mockResolvedValue(requirement());

    await review.markOpened(19, 11, actor());

    expect(updateMany).toHaveBeenCalledWith(
      expect.objectContaining({
        where: { id: 11n, opened_at: null },
        data: expect.objectContaining({ opened_by: 5n }),
      }),
    );
    expect(logOnDocument).toHaveBeenCalledWith(19, 'Opened requirement "Cedula"', ASSIGNEE);
  });

  it('leaves no stamp for someone who cannot check it', async () => {
    findFirst.mockResolvedValue(requirement());

    await review.markOpened(19, 11, actor({ id: 9, can: () => false } as Partial<Actor>));

    expect(updateMany).not.toHaveBeenCalled();
  });

  it('does not re-stamp an already opened file', async () => {
    findFirst.mockResolvedValue(requirement({ opened_at: new Date() }));

    await review.markOpened(19, 11, actor());

    expect(updateMany).not.toHaveBeenCalled();
  });

  it('logs nothing when another tab stamped it first', async () => {
    findFirst.mockResolvedValue(requirement());
    updateMany.mockResolvedValue({ count: 0 });

    await review.markOpened(19, 11, actor());

    expect(logOnDocument).not.toHaveBeenCalled();
  });
});

describe('reviewRequirement', () => {
  it('refuses "Looks good" on a file nobody has opened', async () => {
    findFirst.mockResolvedValue(requirement());

    await expect(
      review.reviewRequirement(19, 11, actor(), { reviewStatus: 'approved' }),
    ).rejects.toThrow('Open "Cedula" first, then mark it checked.');
    expect(update).not.toHaveBeenCalled();
  });

  it('approves an opened file and records the verification', async () => {
    findFirst.mockResolvedValue(requirement({ opened_at: new Date() }));

    const result = await review.reviewRequirement(19, 11, actor(), { reviewStatus: 'approved' });

    const data = update.mock.calls[0][0].data;
    expect(data.review_status).toBe('approved');
    expect(data.verified_by).toBe(5n);
    expect(data.verified_at).toBeInstanceOf(Date);
    expect(result.message).toBe('Marked "Cedula" as Approved.');
    expect(queueMail).not.toHaveBeenCalled();
  });

  it('approves a requirement with nothing uploaded without an open', async () => {
    findFirst.mockResolvedValue(requirement({ uploaded_file_path: null }));

    await review.reviewRequirement(19, 11, actor(), { reviewStatus: 'approved' });

    expect(update).toHaveBeenCalled();
  });

  it('needs a reason to send a file back', async () => {
    findFirst.mockResolvedValue(requirement());

    await expect(
      review.reviewRequirement(19, 11, actor(), { reviewStatus: 'needs_revision', reviewComment: '  ' }),
    ).rejects.toThrow('Please explain what needs to be corrected.');
  });

  it('sending back clears an earlier approval and emails the citizen', async () => {
    findFirst.mockResolvedValue(
      requirement({ opened_at: new Date(), verified_at: new Date(), verified_by: 5n, review_status: 'approved' }),
    );

    await review.reviewRequirement(19, 11, actor(), {
      reviewStatus: 'needs_revision',
      reviewComment: 'The photo is blurred.',
    });

    const data = update.mock.calls[0][0].data;
    expect(data.verified_at).toBeNull();
    expect(data.verified_by).toBeNull();
    expect(queueMail).toHaveBeenCalledWith(
      expect.objectContaining({ to: 'juan@example.com', subject: expect.stringContaining('needs revision') }),
    );
  });

  it('refuses anyone who cannot advance the request', async () => {
    findFirst.mockResolvedValue(requirement({ opened_at: new Date() }));

    await expect(
      review.reviewRequirement(19, 11, actor({ id: 9 }), { reviewStatus: 'approved' }),
    ).rejects.toThrow('Only the assigned staff member or an admin can review requirements.');
  });

  it('refuses an unknown outcome', async () => {
    findFirst.mockResolvedValue(requirement({ opened_at: new Date() }));

    await expect(
      review.reviewRequirement(19, 11, actor(), { reviewStatus: 'maybe' }),
    ).rejects.toThrow('Choose how this document checks out.');
  });

  it('answers a requirement from another request as gone', async () => {
    findFirst.mockResolvedValue(null);

    await expect(
      review.reviewRequirement(19, 11, actor(), { reviewStatus: 'approved' }),
    ).rejects.toThrow('That requirement is no longer on this request.');
    expect(findFirst.mock.calls[0][0].where).toMatchObject({ id: 11n, document_id: 19n });
  });
});
