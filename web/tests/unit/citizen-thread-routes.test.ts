import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Port of `CitizenMessageTest` / `CitizenThreadAccessTest` at the HTTP edge.
 *
 * The thread is read and written only after the citizen confirms a detail on
 * file; a wrong guess never says which detail exists; replies may only hang
 * off this request's PUBLIC messages; and the guessing endpoint is throttled.
 */
const findTrackable = vi.fn();
const messages = vi.fn();
const markRead = vi.fn();
const post = vi.fn();
const isReplyTarget = vi.fn();
const isVerified = vi.fn();
const markVerified = vi.fn();
const throttle = vi.fn();
const hit = vi.fn();

vi.mock('@/lib/domain/citizen-thread', () => ({ findTrackable, messages, markRead, post, isReplyTarget }));
vi.mock('@/lib/domain/citizen-thread-access', async (original) => ({
  ...(await original<typeof import('@/lib/domain/citizen-thread-access')>()),
  isVerified,
  markVerified,
}));
vi.mock('@/lib/rate-limit', async (original) => ({
  ...(await original<typeof import('@/lib/rate-limit')>()),
  throttle,
  hit,
}));

const verifyRoute = await import('@/app/(citizen)/track/[trackingNumber]/verify-contact/route');
const messagesRoute = await import('@/app/(citizen)/track/[trackingNumber]/messages/route');

const TN = 'SPD-20261023-ABC234';
const params = { params: Promise.resolve({ trackingNumber: TN }) };

const document = {
  id: 19n,
  tracking_number: TN,
  citizen_email: 'juan@example.com',
  citizen_contact: '0917 123 4567',
};

function json(body: unknown, headers: Record<string, string> = {}) {
  return new Request(`http://localhost/track/${TN}/x`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', host: 'localhost', ...headers },
    body: JSON.stringify(body),
  }) as never;
}

function form(fields: Record<string, string>) {
  const data = new FormData();
  Object.entries(fields).forEach(([key, value]) => data.append(key, value));

  return new Request(`http://localhost/track/${TN}/messages`, { method: 'POST', body: data }) as never;
}

beforeEach(() => {
  vi.clearAllMocks();
  findTrackable.mockResolvedValue(document);
  messages.mockResolvedValue([]);
  throttle.mockResolvedValue(true);
  hit.mockResolvedValue(true);
  isVerified.mockResolvedValue(true);
  isReplyTarget.mockResolvedValue(true);
});

describe('verify-contact', () => {
  it('unlocks the thread for the mobile number on file, however it is typed', async () => {
    const response = await verifyRoute.POST(json({ detail: '+63 917-123-4567' }), params);

    expect(response.status).toBe(200);
    expect(markVerified).toHaveBeenCalledWith(TN);
    expect(await response.json()).toEqual({ verified: true, messages: [] });
  });

  it('refuses a wrong detail without saying which one is on file', async () => {
    const response = await verifyRoute.POST(json({ detail: 'someone@else.com' }), params);
    const body = await response.json();

    expect(response.status).toBe(422);
    expect(body.errors.detail[0]).toContain("doesn't match the contact details");
    expect(markVerified).not.toHaveBeenCalled();
  });

  it('is throttled per caller and per request', async () => {
    throttle.mockResolvedValueOnce(false);
    expect((await verifyRoute.POST(json({ detail: 'x' }), params)).status).toBe(429);

    hit.mockResolvedValueOnce(false);
    expect((await verifyRoute.POST(json({ detail: 'x' }), params)).status).toBe(429);
    expect(hit).toHaveBeenCalledWith(`verifyContact:doc:${TN}`, expect.anything());
  });

  it('refuses a post from another site', async () => {
    const response = await verifyRoute.POST(json({ detail: 'x' }, { origin: 'https://evil.example' }), params);

    expect(response.status).toBe(403);
  });

  it('answers an internal or unknown request as not found', async () => {
    findTrackable.mockResolvedValue(null);

    expect((await verifyRoute.POST(json({ detail: 'x' }), params)).status).toBe(404);
  });
});

describe('messages', () => {
  it('refuses to read the thread before the citizen is confirmed', async () => {
    isVerified.mockResolvedValue(false);

    const response = await messagesRoute.GET(new Request('http://localhost') as never, params);

    expect(response.status).toBe(403);
    expect(await response.json()).toEqual({ verified: false, can_verify: true });
    expect(messages).not.toHaveBeenCalled();
  });

  it('reading marks the office replies read', async () => {
    await messagesRoute.GET(new Request('http://localhost') as never, params);

    expect(markRead).toHaveBeenCalledWith(19n);
  });

  it('refuses to post before the citizen is confirmed', async () => {
    isVerified.mockResolvedValue(false);

    expect((await messagesRoute.POST(form({ body: 'Hello' }), params)).status).toBe(403);
    expect(post).not.toHaveBeenCalled();
  });

  it('needs a message body', async () => {
    const response = await messagesRoute.POST(form({ body: '   ' }), params);

    expect(response.status).toBe(422);
    expect((await response.json()).errors.body[0]).toBe('Write your message first.');
  });

  it('will not thread a reply under a message outside this public thread', async () => {
    isReplyTarget.mockResolvedValue(false);

    const response = await messagesRoute.POST(form({ body: 'Re', parent_id: '77' }), params);

    expect(response.status).toBe(422);
    expect(post).not.toHaveBeenCalled();
  });

  it('posts and returns the thread', async () => {
    const response = await messagesRoute.POST(form({ body: 'When can I claim it?' }), params);

    expect(response.status).toBe(200);
    expect(post).toHaveBeenCalledWith(document, 'When can I claim it?', null, null);
  });
});
