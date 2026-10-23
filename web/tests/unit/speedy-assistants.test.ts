import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DocumentStatus } from '@/lib/domain/document-status';

/**
 * Port of `SpeedyAssistantTest` / `DocumentAssistantTest`.
 *
 * Deterministic first, the model last: the questions Speedy can answer from
 * its own sources must never wait on (or be invented by) the LLM, and the
 * request-scoped answers must name the OFFICE, never a staff member.
 */
const requestTypesFindMany = vi.fn();
const requestTypesFindFirst = vi.fn();
const documentsFindFirst = vi.fn();
const predict = vi.fn();

vi.mock('@/lib/db', () => ({
  db: {
    request_types: { findMany: requestTypesFindMany, findFirst: requestTypesFindFirst },
    documents: { findFirst: documentsFindFirst },
  },
  toId: Number,
  id: BigInt,
}));
vi.mock('@/lib/domain/completion-predictor', () => ({ predict }));

const SiteAssistant = await import('@/lib/domain/ai/site-assistant');
const DocumentAssistant = await import('@/lib/domain/ai/document-assistant');
const RequirementsAnswer = await import('@/lib/domain/ai/requirements-answer');

const neverCalled = { chat: vi.fn(async () => 'MODEL') };
const model = (reply: string | null) => ({ chat: vi.fn(async (_system: string, _question: string) => reply) });

const PERMIT = {
  name: 'Business Permit',
  request_type_requirements: [
    { label: 'Barangay clearance', is_mandatory: true },
    { label: 'Lease contract', is_mandatory: false },
  ],
};

beforeEach(() => {
  vi.clearAllMocks();
  requestTypesFindMany.mockResolvedValue([PERMIT, { name: 'Permit', request_type_requirements: [] }]);
  requestTypesFindFirst.mockResolvedValue(null);
  predict.mockResolvedValue({ available: false, eta: null, remainingHours: 0 });
});

describe('RequirementsAnswer', () => {
  it('treats pickup questions as not about filing requirements', () => {
    expect(RequirementsAnswer.asks('What do I bring for pickup?')).toBe(false);
    expect(RequirementsAnswer.asks('What are the requirements?')).toBe(true);
  });

  it('prefers the longest service name the question mentions', async () => {
    const found = await RequirementsAnswer.resolve('requirements for a business permit?');

    expect(found?.service).toBe('Business Permit');
  });

  it('lists the mandatory items, then the optional ones', () => {
    const text = RequirementsAnswer.text({
      service: 'Business Permit',
      isCurrent: true,
      items: [
        { label: 'Barangay clearance', mandatory: true },
        { label: 'Lease contract', mandatory: false },
      ],
    });

    expect(text).toContain('For your Business Permit');
    expect(text.indexOf('Barangay clearance')).toBeLessThan(text.indexOf('Optional'));
    expect(text).toContain('• Lease contract');
  });
});

describe('SiteAssistant', () => {
  it('answers a greeting without the model', async () => {
    const result = await SiteAssistant.answer('hello!', neverCalled);

    expect(result.source).toBe('fallback');
    expect(result.answer).toContain("I'm Speedy");
    expect(neverCalled.chat).not.toHaveBeenCalled();
  });

  it('asks which service when the requirements question names none', async () => {
    requestTypesFindMany
      .mockResolvedValueOnce([PERMIT])
      .mockResolvedValueOnce([{ name: 'Business Permit' }, { name: 'Cedula' }]);

    const result = await SiteAssistant.answer('what are the requirements?', neverCalled);

    expect(result.answer).toBe('Which service is it for? For example: Business Permit, Cedula.');
  });

  it('answers from the FAQ before the model', async () => {
    const result = await SiteAssistant.answer('do i need an account to file a request?', neverCalled);

    expect(result.source).toBe('faq');
    expect(neverCalled.chat).not.toHaveBeenCalled();
  });

  it('falls back to its introduction when the model is down', async () => {
    requestTypesFindMany.mockResolvedValue([]);

    const result = await SiteAssistant.answer('zxqv plorb', model(null));

    expect(result).toEqual({ answer: expect.stringContaining("I'm not sure about that one."), source: 'fallback' });
  });

  it('uses the model only for what nothing else covers', async () => {
    requestTypesFindMany.mockResolvedValue([]);

    expect(await SiteAssistant.answer('zxqv plorb', model('From the FAQ.'))).toEqual({
      answer: 'From the FAQ.',
      source: 'ai',
    });
  });
});

describe('DocumentAssistant', () => {
  const request = (status: DocumentStatus, departmentName: string | null = 'Business Permits Office') => ({
    tracking_number: 'SPD-20261023-ABC234',
    document_type: 'Business Permit',
    citizen_name: 'Juan',
    status,
    created_at: new Date('2026-10-20T00:00:00Z'),
    updated_at: null,
    status_changed_at: new Date('2026-10-21T00:00:00Z'),
    completed_at: null,
    department: departmentName ? { name: departmentName } : null,
  });

  it('names the handling office for a status question, never a person', async () => {
    documentsFindFirst.mockResolvedValue(request(DocumentStatus.InReview));

    const result = await DocumentAssistant.answer(
      { tracking_number: 'SPD-20261023-ABC234', document_type: 'Business Permit' },
      'who is handling it?',
      neverCalled,
    );

    expect(result.answer).toBe(
      'Document SPD-20261023-ABC234 (Business Permit) is in review. It is being handled by the Business Permits Office.',
    );
    expect(neverCalled.chat).not.toHaveBeenCalled();
  });

  it('tells a finished request how to claim it', async () => {
    documentsFindFirst.mockResolvedValue(request(DocumentStatus.Completed));

    const result = await DocumentAssistant.answer(
      { tracking_number: 'SPD-20261023-ABC234', document_type: 'Business Permit' },
      'tell me a story',
      neverCalled,
    );

    expect(result.answer).toContain('already completed and ready');
  });

  it('answers requirements from the catalogue even for its own type', async () => {
    const result = await DocumentAssistant.answer(
      { tracking_number: 'SPD-20261023-ABC234', document_type: 'Business Permit' },
      'what do i need to bring?',
      neverCalled,
    );

    expect(result.answer).toContain('For your Business Permit');
  });

  it('grounds the model in this request only', async () => {
    documentsFindFirst.mockResolvedValue(request(DocumentStatus.Pending, null));
    const llm = model('It is with the office.');

    await DocumentAssistant.answer(
      { tracking_number: 'SPD-20261023-ABC234', document_type: 'Business Permit' },
      'may my cousin go instead of me',
      llm,
    );

    const system = llm.chat.mock.calls[0][0] as string;
    expect(system).toContain('Tracking number: SPD-20261023-ABC234');
    expect(system).toContain('Handled by (office): Not yet routed to an office');
  });
});
