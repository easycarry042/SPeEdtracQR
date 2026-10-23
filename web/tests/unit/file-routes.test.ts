import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Actor } from '@/lib/auth/actor';

/**
 * The private file routes: requirement uploads and request attachments.
 *
 * Citizen uploads are routinely IDs and clearances, so what matters is that a
 * file is only ever streamed to staff who may see its request, and that only
 * the View link (`?open=1`) counts as a reviewer having opened it.
 */
const currentActor = vi.fn();
const canAccessDocument = vi.fn();
const requirementFileFor = vi.fn();
const markOpened = vi.fn();
const attachmentFor = vi.fn();
const removeAttachment = vi.fn();
const readPrivate = vi.fn();

vi.mock('@/lib/auth/actor', () => ({ currentActor }));
vi.mock('@/lib/domain/scope', () => ({ canAccessDocument }));
vi.mock('@/lib/domain/requirement-review', () => ({ requirementFileFor, markOpened }));
vi.mock('@/lib/domain/attachments', () => ({ attachmentFor, removeAttachment }));
vi.mock('@/lib/storage', () => ({ readPrivate }));
vi.mock('next/cache', () => ({ revalidatePath: vi.fn() }));

const requirementRoute = await import('@/app/documents/[document]/requirements/[requirement]/file/route');
const attachmentRoute = await import('@/app/attachments/[attachment]/route');
const { ActionError } = await import('@/lib/domain/status-actions');

const staff = { id: 5 } as Actor;

function getRequirement(query = '', document = '19', requirement = '11'): Promise<Response> {
  const url = new URL(`http://localhost/documents/${document}/requirements/${requirement}/file${query}`);

  return requirementRoute.GET({ nextUrl: url } as never, {
    params: Promise.resolve({ document, requirement }),
  });
}

function attachmentRequest(query = '') {
  return { nextUrl: new URL(`http://localhost/attachments/3${query}`) } as never;
}

const attachmentParams = { params: Promise.resolve({ attachment: '3' }) };

beforeEach(() => {
  vi.clearAllMocks();
  currentActor.mockResolvedValue(staff);
  canAccessDocument.mockReturnValue(true);
  requirementFileFor.mockResolvedValue({ path: 'requirements/a.pdf', createdBy: 2, assignedTo: 5 });
  attachmentFor.mockResolvedValue({
    filePath: 'attachments/b.jpg',
    document: { created_by: 2, assigned_to: 5 },
  });
  readPrivate.mockResolvedValue(Buffer.from('bytes'));
});

describe('requirement file', () => {
  it('streams the file inline with its type, never cached by a shared cache', async () => {
    const response = await getRequirement();

    expect(response.status).toBe(200);
    expect(response.headers.get('content-type')).toBe('application/pdf');
    expect(response.headers.get('cache-control')).toBe('private, no-store');
    expect(markOpened).not.toHaveBeenCalled();
  });

  it('records the open only through ?open=1', async () => {
    await getRequirement('?open=1');

    expect(markOpened).toHaveBeenCalledWith(19, 11, staff);
  });

  it('refuses a signed-out visitor', async () => {
    currentActor.mockResolvedValue(null);

    expect((await getRequirement()).status).toBe(403);
    expect(readPrivate).not.toHaveBeenCalled();
  });

  it('refuses staff who may not see the request', async () => {
    canAccessDocument.mockReturnValue(false);

    expect((await getRequirement('?open=1')).status).toBe(403);
    expect(readPrivate).not.toHaveBeenCalled();
    expect(markOpened).not.toHaveBeenCalled();
  });

  it('answers 404 for no upload, a missing file or a bad id', async () => {
    requirementFileFor.mockResolvedValueOnce(null);
    expect((await getRequirement()).status).toBe(404);

    readPrivate.mockResolvedValueOnce(null);
    expect((await getRequirement()).status).toBe(404);

    expect((await getRequirement('', 'abc')).status).toBe(404);
  });
});

describe('attachment', () => {
  it('streams inline, or as a download with ?download=1', async () => {
    const inline = await attachmentRoute.GET(attachmentRequest(), attachmentParams);
    expect(inline.headers.get('content-type')).toBe('image/jpeg');
    expect(inline.headers.get('content-disposition')).toBeNull();

    const saved = await attachmentRoute.GET(attachmentRequest('?download=1'), attachmentParams);
    expect(saved.headers.get('content-disposition')).toBe('attachment; filename="b.jpg"');
  });

  it('refuses staff who may not see the request', async () => {
    canAccessDocument.mockReturnValue(false);

    expect((await attachmentRoute.GET(attachmentRequest(), attachmentParams)).status).toBe(403);
    expect(readPrivate).not.toHaveBeenCalled();
  });

  it('passes a refused delete back with its status', async () => {
    removeAttachment.mockRejectedValue(new ActionError('You cannot remove files from this request.', 403));

    const response = await attachmentRoute.DELETE(attachmentRequest(), attachmentParams);

    expect(response.status).toBe(403);
    expect(await response.json()).toEqual({ message: 'You cannot remove files from this request.' });
  });
});
