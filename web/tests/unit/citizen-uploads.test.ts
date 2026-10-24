import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * Port of `CitizenDocumentUploadTest` / `RequirementReuploadTest`.
 *
 * A re-upload is only for an item staff returned, and the new file must be
 * opened and checked again — leaving the old verification on it would let an
 * unchecked file through the Approved gate. The confirmation names the
 * office, never the staff member.
 */
const documentFindFirst = vi.fn();
const documentUpdate = vi.fn();
const requirementFindFirst = vi.fn();
const requirementUpdate = vi.fn();
const attachmentAggregate = vi.fn();
const attachmentCreate = vi.fn();
const storePrivate = vi.fn();
const notify = vi.fn();
const queueMail = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    documents: { findFirst: documentFindFirst, update: documentUpdate },
    document_requirements: { findFirst: requirementFindFirst, update: requirementUpdate },
    document_attachments: { aggregate: attachmentAggregate, create: attachmentCreate },
  },
  toId: Number,
  id: (value: number | bigint) => BigInt(value),
}));
vi.mock('@/lib/storage', () => ({ storePrivate }));
vi.mock('@/lib/activity', () => ({ logOnDocument: vi.fn() }));
vi.mock('@/lib/domain/notifications', () => ({ notify }));
vi.mock('@/lib/mail/send', async (original) => ({
  ...(await original<typeof import('@/lib/mail/send')>()),
  queueMail,
}));

const uploads = await import('@/lib/domain/citizen-uploads');

const TN = 'SPD-20261023-ABC234';
const file = (name = 'id.jpg', size = 2048) => new File([new Uint8Array(size)], name, { type: 'image/jpeg' });

function request(status: string = DocumentStatus.InReview) {
  return {
    id: 19n,
    tracking_number: TN,
    document_type: 'Business Permit',
    citizen_name: 'Juan',
    status,
    assigned_to: 5n,
    assignee: { name: 'Ana Staff', email: 'ana@city.gov' },
    department: { name: 'Business Permits Office' },
  };
}

beforeEach(() => {
  vi.clearAllMocks();
  documentFindFirst.mockResolvedValue(request());
  attachmentAggregate.mockResolvedValue({ _max: { sort_order: 2 } });
  storePrivate.mockImplementation(async (_file: File, dir: string) => `${dir}/stored.jpg`);
});

describe('uploadFiles', () => {
  it('stores each file after the existing ones and names the office, not the staff member', async () => {
    const result = await uploads.uploadFiles(TN, [file('a.jpg'), file('b.jpg')], 'Missing ID');

    expect(attachmentCreate).toHaveBeenCalledTimes(2);
    expect(attachmentCreate.mock.calls.map((call) => call[0].data.sort_order)).toEqual([3, 4]);
    expect(result.message).toBe('Your file(s) were sent to the Business Permits Office. They will be notified.');
    expect(result.message).not.toContain('Ana');
    expect(queueMail).toHaveBeenCalledWith(expect.objectContaining({ to: 'ana@city.gov' }));
  });

  it('refuses uploads on a completed request', async () => {
    documentFindFirst.mockResolvedValue(request(DocumentStatus.Completed));

    await expect(uploads.uploadFiles(TN, [file()], null)).rejects.toThrow('already completed');
  });

  it('caps an upload at five files', async () => {
    const six = Array.from({ length: 6 }, (_, i) => file(`f${i}.jpg`));

    await expect(uploads.uploadFiles(TN, six, null)).rejects.toThrow('Send up to 5 files at a time.');
    expect(storePrivate).not.toHaveBeenCalled();
  });

  it('refuses a file type the rules do not allow', async () => {
    await expect(uploads.uploadFiles(TN, [file('run.exe')], null)).rejects.toThrow('run.exe');
  });

  it('answers an unknown or internal request as not found', async () => {
    documentFindFirst.mockResolvedValue(null);

    await expect(uploads.uploadFiles(TN, [file()], null)).rejects.toThrow('could not be found');
    expect(JSON.stringify(documentFindFirst.mock.calls[0][0].where)).toContain('"not":"internal"');
  });
});

describe('reuploadRequirement', () => {
  it('only takes a file for an item returned for revision', async () => {
    requirementFindFirst.mockResolvedValue({ id: 11n, label: 'Cedula', review_status: 'approved' });

    await expect(uploads.reuploadRequirement(TN, 11, file())).rejects.toThrow('not awaiting revision');
    expect(storePrivate).not.toHaveBeenCalled();
  });

  it('puts the item back to pending and clears the old open and check', async () => {
    requirementFindFirst.mockResolvedValue({ id: 11n, label: 'Cedula', review_status: 'needs_revision' });

    const result = await uploads.reuploadRequirement(TN, 11, file());

    expect(requirementUpdate.mock.calls[0][0].data).toMatchObject({
      uploaded_file_path: 'document-requirements/stored.jpg',
      review_status: 'pending',
      opened_at: null,
      opened_by: null,
      verified_at: null,
      verified_by: null,
    });
    expect(notify).toHaveBeenCalledWith(5, expect.objectContaining({ event: 'revision_resubmitted' }));
    expect(result.message).toBe('Your revised "Cedula" was sent for re-review.');
  });

  it('needs a file', async () => {
    await expect(uploads.reuploadRequirement(TN, 11, null)).rejects.toThrow('Please choose a file.');
  });
});
