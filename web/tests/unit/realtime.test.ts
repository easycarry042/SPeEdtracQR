import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The Supabase relay for live updates. A Supabase public topic can be read by
 * anyone holding the anon key — which every browser has — so the property
 * that matters is that only the citizen channel (`documents.SPD-…`, whose
 * payload is office-only by design) is ever relayed. The staff collaboration
 * feed and per-user channels must stay in-process.
 */
const configured = vi.fn();
const httpSend = vi.fn();
const removeChannel = vi.fn();
const channel = vi.fn(() => ({ httpSend }));

vi.mock('@/lib/supabase', () => ({
  supabaseConfigured: configured,
  supabase: () => ({ channel, removeChannel }),
}));

vi.mock('@/lib/db', () => ({ db: {}, id: BigInt, toId: Number }));

// Run the deferred send straight away so the test can observe it.
vi.mock('next/server', () => ({ after: (task: () => Promise<void>) => void task() }));

const realtime = await import('@/lib/realtime');

const CHANNEL = 'documents.SPD-20261022-ABC234';

const moved = {
  status: 'in_progress',
  status_label: 'In Progress',
  current_department: 'Business Permits',
  event: 'Updated to In Progress',
  timestamp: null,
};

beforeEach(() => {
  vi.clearAllMocks();
  configured.mockReturnValue(true);
  httpSend.mockResolvedValue({ success: true });
});

describe('isPublicChannel', () => {
  it('accepts a tracking-number channel', () => {
    expect(realtime.isPublicChannel(CHANNEL)).toBe(true);
    expect(realtime.isPublicChannel(realtime.publicChannel('INT-20261022-XYZ789'))).toBe(true);
  });

  it('rejects the staff and user channels', () => {
    expect(realtime.isPublicChannel(realtime.staffChannel(19))).toBe(false);
    expect(realtime.isPublicChannel(realtime.userChannel(4))).toBe(false);
    expect(realtime.isPublicChannel('documents.19')).toBe(false);
  });
});

describe('publish', () => {
  it('relays a public event through Supabase broadcast and lets go of the channel', async () => {
    realtime.publish({ channel: CHANNEL, event: 'moved', data: moved });
    await vi.waitFor(() => expect(removeChannel).toHaveBeenCalled());

    expect(channel).toHaveBeenCalledWith(CHANNEL);
    expect(httpSend).toHaveBeenCalledWith('moved', moved);
  });

  it('never relays the staff collaboration feed', () => {
    realtime.broadcastComment(19, {
      id: 1,
      author: 'Ana Santos',
      author_type: 'staff',
      body: 'internal note',
      visibility: 'internal',
      parent_id: null,
      timestamp: null,
    });

    expect(channel).not.toHaveBeenCalled();
  });

  it('stays in-process when Supabase is not configured', () => {
    configured.mockReturnValue(false);

    realtime.publish({ channel: CHANNEL, event: 'moved', data: moved });

    expect(channel).not.toHaveBeenCalled();
  });

  it('still delivers to local subscribers when the relay fails', async () => {
    httpSend.mockRejectedValue(new Error('Realtime down'));
    const errors = vi.spyOn(console, 'error').mockImplementation(() => {});
    const heard = vi.fn();
    const unsubscribe = realtime.subscribe(CHANNEL, heard);

    realtime.publish({ channel: CHANNEL, event: 'moved', data: moved });
    await vi.waitFor(() => expect(removeChannel).toHaveBeenCalled());

    expect(heard).toHaveBeenCalledOnce();
    expect(errors).toHaveBeenCalled();
    unsubscribe();
    errors.mockRestore();
  });
});
