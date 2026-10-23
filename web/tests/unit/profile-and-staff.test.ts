import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * Port of `ProfileTest`, `AvatarTest`, `SignatureTest`, `StaffProfileTest`.
 *
 * The rules worth guarding: an upload is checked by its bytes, not its name
 * (a ".png" script is refused); the signature must really be a PNG; the
 * password changes only with the current one; a highlight may only point at
 * the author's own request; and an unclaimed request is taken only once.
 */
const usersFindFirst = vi.fn();
const usersFindUnique = vi.fn();
const usersUpdate = vi.fn();
const documentsFindFirst = vi.fn();
const documentsUpdateMany = vi.fn();
const highlightsCreate = vi.fn();
const putPrivate = vi.fn();
const deletePrivate = vi.fn();
const verifyPassword = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    users: { findFirst: usersFindFirst, findUnique: usersFindUnique, update: usersUpdate },
    documents: { findFirst: documentsFindFirst, updateMany: documentsUpdateMany },
    staff_highlights: { create: highlightsCreate },
  },
  toId: Number,
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/storage', () => ({ putPrivate, deletePrivate }));
vi.mock('@/lib/auth/password', () => ({ verifyPassword, hashPassword: async (p: string) => `hashed:${p}` }));
vi.mock('@/lib/activity', () => ({ logActivity: vi.fn(), logOnDocument: vi.fn(), SUBJECT: { User: 'App\\Models\\User', Document: 'App\\Models\\Document' } }));
vi.mock('@/lib/domain/comments', () => ({ logSystemComment: vi.fn() }));
vi.mock('@/lib/realtime', () => ({ broadcastStatusUpdated: vi.fn() }));

const profile = await import('@/lib/domain/profile');
const staff = await import('@/lib/domain/staff-profile');
const { narrate } = await import('@/lib/domain/activity-narrative');

const actor = (permissions: string[] = []) =>
  ({ id: 5, name: 'Ana', can: (p: string) => permissions.includes(p), hasRole: () => false }) as unknown as Actor;

const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0, 0, 0, 0]);
const pngFile = (name = 'me.png') => new File([PNG], name, { type: 'image/png' });

beforeEach(() => {
  vi.clearAllMocks();
  usersFindUnique.mockResolvedValue({ avatar_path: null, password: 'hash', email: 'ana@city.gov' });
  usersFindFirst.mockResolvedValue(null);
});

describe('profile', () => {
  it('reads the file type from its bytes', () => {
    expect(profile.imageKind(PNG)).toBe('png');
    expect(profile.imageKind(Buffer.from([0xff, 0xd8, 0xff, 0xe0]))).toBe('jpg');
    expect(profile.imageKind(Buffer.from('<?php echo 1; ?>'))).toBeNull();
  });

  it('refuses a script named like a picture', async () => {
    const fake = new File(['<script>alert(1)</script>'], 'me.png', { type: 'image/png' });

    await expect(profile.storeAvatar(actor(), fake)).rejects.toThrow('JPG, PNG, or WebP');
    expect(putPrivate).not.toHaveBeenCalled();
  });

  it('stores a real picture under the user and drops the old one', async () => {
    usersFindUnique.mockResolvedValue({ avatar_path: 'avatars/user-5-1.png' });

    await profile.storeAvatar(actor(), pngFile());

    expect(putPrivate.mock.calls[0][0]).toMatch(/^avatars\/user-5-\d+\.png$/);
    expect(deletePrivate).toHaveBeenCalledWith('avatars/user-5-1.png');
  });

  it('only takes a PNG data URL as the signature', async () => {
    await expect(profile.storeSignature(actor(), 'data:image/jpeg;base64,AAAA')).rejects.toThrow('PNG drawing');
    await expect(
      profile.storeSignature(actor(), `data:image/png;base64,${Buffer.from('not a png').toString('base64')}`),
    ).rejects.toThrow('could not be read');

    await profile.storeSignature(actor(), `data:image/png;base64,${PNG.toString('base64')}`);
    expect(putPrivate).toHaveBeenCalledWith('signatures/user-5.png', expect.any(Buffer), 'image/png');
  });

  it('changes the password only with the current one, confirmed', async () => {
    verifyPassword.mockResolvedValue(false);
    await expect(
      profile.changePassword(actor(), { current: 'x', password: 'longenough', confirmation: 'longenough' }),
    ).rejects.toThrow('The password is incorrect.');

    verifyPassword.mockResolvedValue(true);
    await expect(
      profile.changePassword(actor(), { current: 'x', password: 'longenough', confirmation: 'different1' }),
    ).rejects.toThrow('does not match');
    await expect(
      profile.changePassword(actor(), { current: 'x', password: 'short', confirmation: 'short' }),
    ).rejects.toThrow('at least 8');

    await profile.changePassword(actor(), { current: 'x', password: 'longenough', confirmation: 'longenough' });
    expect(usersUpdate.mock.calls[0][0].data.password).toBe('hashed:longenough');
  });

  it('refuses an email another account uses, whatever its case', async () => {
    usersFindFirst.mockResolvedValue({ id: 9n });

    await expect(profile.updateDetails(actor(), { name: 'Ana', email: 'BEN@city.gov' })).rejects.toThrow('already been taken');
    expect(usersFindFirst.mock.calls[0][0].where.email).toEqual({ equals: 'ben@city.gov', mode: 'insensitive' });
  });
});

describe('staff profile', () => {
  it('counts a streak that ends yesterday as still running', () => {
    expect(staff.streaks(['2026-10-20', '2026-10-21', '2026-10-22'], '2026-10-23', '2026-10-22')).toEqual([3, 3]);
    expect(staff.streaks(['2026-10-01', '2026-10-02', '2026-10-20'], '2026-10-23', '2026-10-22')).toEqual([0, 2]);
    expect(staff.streaks([], '2026-10-23', '2026-10-22')).toEqual([0, 0]);
  });

  it('only lets a highlight point at the author’s own request', async () => {
    documentsFindFirst.mockResolvedValue(null);

    await expect(staff.postHighlight(actor(), { body: 'Done!', type: 'milestone', documentId: 19 })).rejects.toThrow(
      'assigned to or created by you',
    );
    expect(highlightsCreate).not.toHaveBeenCalled();
  });

  it('refuses an unknown highlight type', async () => {
    await expect(staff.postHighlight(actor(), { body: 'x', type: 'brag', documentId: null })).rejects.toThrow('kind of highlight');
  });

  it('takes an unclaimed request once, moving Pending to In Progress', async () => {
    documentsFindFirst.mockResolvedValue({ id: 19n, tracking_number: 'SPD-1', status: 'pending', assigned_to: null });
    documentsUpdateMany.mockResolvedValue({ count: 1 });

    const result = await staff.claimUnassigned(actor(['accept documents']), 19);

    expect(documentsUpdateMany.mock.calls[0][0].where).toEqual({ id: 19n, assigned_to: null });
    expect(documentsUpdateMany.mock.calls[0][0].data.status).toBe('in_progress');
    expect(result.message).toBe('Accepted SPD-1.');
  });

  it('refuses a request someone else took first', async () => {
    documentsFindFirst.mockResolvedValue({ id: 19n, tracking_number: 'SPD-1', status: 'pending', assigned_to: null });
    documentsUpdateMany.mockResolvedValue({ count: 0 });

    await expect(staff.claimUnassigned(actor(['accept documents']), 19)).rejects.toThrow('already assigned');
  });
});

describe('activity narrative', () => {
  it('splits at the dash and colours trouble red', () => {
    expect(narrate('Returned for revision at Engineering — reason: attach the list')).toEqual({
      tone: 'red',
      headline: 'Returned for revision at Engineering',
      detail: 'Attach the list',
    });
  });

  it('keeps a quoted name whole and matches whole words only', () => {
    expect(narrate('Reviewed requirement "Cedula: copy": Approved')).toEqual({
      tone: 'green',
      headline: 'Reviewed requirement "Cedula: copy"',
      detail: 'Approved',
    });
    expect(narrate('Assigned to Paolo').tone).toBe('blue');
    expect(narrate('updated').headline).toBe('Record updated');
  });
});
