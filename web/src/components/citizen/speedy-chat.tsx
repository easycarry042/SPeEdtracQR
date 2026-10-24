'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { SpeedyContext, SpeedyInbox } from '@/lib/domain/ai/speedy-context';
import type { ThreadMessage } from '@/lib/domain/citizen-thread';
import { subscribeDocument } from '@/lib/client/realtime';
import * as SpeedQr from '@/lib/client/speed-qr';
import { routes } from '@/lib/routes';
import { QrCamera } from '@/components/qr-camera';

/**
 * Speedy — the one assistant on every citizen page, and the citizen's way to
 * talk to the office. Port of `components/speedy.blade.php`.
 *
 * Two tabs, kept apart on purpose: "Speedy" is automated and can be wrong;
 * "Office messages" is real staff, named by office with a Staff mark. The
 * conversation opens only after the citizen confirms the email or mobile on
 * the request, and the launcher badge counts replies waiting on any page.
 * `?speedy=messages` (the link in the office's reply email) opens straight on
 * the conversation.
 */
type BotMessage = { id: number; role: 'user' | 'assistant'; text: string };

const GENERAL_SUGGESTIONS = ['How do I file a request?', 'What do I need to bring?', 'Do I need an account?'];

const READER_ID = 'speedyQrReader';

async function postJson(url: string, body: unknown): Promise<Response> {
  return fetch(url, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
}

function firstError(data: { message?: string; errors?: Record<string, string[]> }, fallback: string): string {
  const fromFields = data.errors ? Object.values(data.errors).flat()[0] : undefined;

  return fromFields ?? data.message ?? fallback;
}

export function SpeedyChat({
  initialContext,
  initialInbox,
  accept,
}: {
  initialContext: SpeedyContext | null;
  initialInbox: SpeedyInbox;
  accept: string;
}) {
  const [context, setContextState] = useState<SpeedyContext | null>(initialContext);
  const [inbox, setInbox] = useState<SpeedyInbox>(initialInbox);
  const [tab, setTab] = useState<'bot' | 'office'>('bot');
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [scanError, setScanError] = useState('');
  const [draft, setDraft] = useState('');
  const [messages, setMessages] = useState<BotMessage[]>([]);
  const [thread, setThread] = useState<ThreadMessage[]>([]);
  const [detail, setDetail] = useState('');
  const [officeDraft, setOfficeDraft] = useState('');
  const [officeFileName, setOfficeFileName] = useState('');
  const [officeBusy, setOfficeBusy] = useState(false);
  const [officeError, setOfficeError] = useState('');

  const nextId = useRef(1);
  const contextRef = useRef(context);
  const stateRef = useRef({ open, tab });
  const logRef = useRef<HTMLDivElement>(null);
  const threadRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const detailRef = useRef<HTMLInputElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const subscriptions = useRef(new Map<string, () => void>());

  contextRef.current = context;
  stateRef.current = { open, tab };

  // Fixed when the page loads: switching requests later must not rewrite what
  // was already said.
  const greeting = useMemo(
    () =>
      initialContext
        ? `Hi! Ask me anything about ${initialContext.tracking} — when it will be ready, what happens next, or what to bring.`
        : 'Hi, I’m Speedy! Ask me how filing and tracking work, or give me your tracking number and I’ll check your request.',
    [initialContext],
  );

  const suggestions = context ? context.suggestions : GENERAL_SUGGESTIONS;
  const waitingElsewhere = inbox.threads.filter((t) => t.unread > 0 && t.tracking !== context?.tracking);

  const scrollToEnd = (box: HTMLDivElement | null): void => {
    requestAnimationFrame(() => {
      if (box) {
        box.scrollTop = box.scrollHeight;
      }
    });
  };

  const say = useCallback((text: string): void => {
    setMessages((list) => [...list, { id: nextId.current++, role: 'assistant', text }]);
    scrollToEnd(logRef.current);
  }, []);

  const patchContext = (patch: Partial<SpeedyContext>): void =>
    setContextState((current) => (current ? { ...current, ...patch } : current));

  const showThread = useCallback((list: ThreadMessage[]): void => {
    setThread(list);
    // Opening the tab reads them (the endpoint marks them read).
    setContextState((current) => (current ? { ...current, unread: 0 } : current));
    scrollToEnd(threadRef.current);
  }, []);

  const refreshInbox = useCallback(async (): Promise<void> => {
    try {
      const response = await fetch(routes.speedyInbox(), { headers: { Accept: 'application/json' } });

      if (!response.ok) {
        return;
      }

      const next = (await response.json()) as SpeedyInbox;
      setInbox(next);

      const current = next.threads.find((t) => t.tracking === contextRef.current?.tracking);

      if (current) {
        setContextState((c) => (c ? { ...c, unread: current.unread } : c));
      }
    } catch {
      // Offline: the badge keeps its last count.
    }
  }, []);

  const loadThread = useCallback(async (): Promise<void> => {
    const current = contextRef.current;

    if (!current?.verified) {
      return;
    }

    try {
      const response = await fetch(routes.trackMessagesIndex(current.tracking), {
        headers: { Accept: 'application/json' },
      });

      if (response.status === 403) {
        // The confirmation expired.
        setContextState((c) => (c ? { ...c, verified: false } : c));

        return;
      }

      if (response.ok) {
        showThread(((await response.json()) as { messages: ThreadMessage[] }).messages);
        void refreshInbox();
      }
    } catch {
      // The next ping or poll tries again.
    }
  }, [refreshInbox, showThread]);

  // The public channel only says that the conversation changed — never what
  // was said — so the thread is re-fetched through the confirmed endpoint.
  const listen = useCallback(
    (tracking: string): void => {
      if (subscriptions.current.has(tracking)) {
        return;
      }

      subscriptions.current.set(
        tracking,
        subscribeDocument(tracking, {
          on: {
            thread: () => {
              const { open: isOpen, tab: currentTab } = stateRef.current;

              if (isOpen && currentTab === 'office' && contextRef.current?.tracking === tracking) {
                void loadThread();
              } else {
                void refreshInbox();
              }
            },
          },
        }),
      );
    },
    [loadThread, refreshInbox],
  );

  const setContext = (next: SpeedyContext | null): void => {
    if (next?.tracking !== contextRef.current?.tracking) {
      setThread([]);
      setOfficeError('');
      setDetail('');
    }

    setContextState(next);

    if (next?.verified) {
      listen(next.tracking);
    }
  };

  useEffect(() => {
    inbox.threads.forEach((t) => listen(t.tracking));

    if (context?.verified) {
      listen(context.tracking);
    }
    // Subscriptions are added as threads appear and torn down on unmount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inbox.threads, listen]);

  useEffect(() => {
    const subs = subscriptions.current;

    return () => {
      subs.forEach((unsubscribe) => unsubscribe());
      subs.clear();
    };
  }, []);

  // Fallback when no live channel is up: check for replies now and then, only
  // for a browser that has confirmed a request.
  useEffect(() => {
    const timer = setInterval(() => {
      if (inbox.threads.length > 0 || contextRef.current?.verified) {
        void refreshInbox();
      }

      if (stateRef.current.open && stateRef.current.tab === 'office' && contextRef.current?.verified) {
        void loadThread();
      }
    }, 45_000);

    return () => clearInterval(timer);
  }, [inbox.threads.length, loadThread, refreshInbox]);

  const stopScan = useCallback((): void => {
    setScanning((was) => {
      if (was) {
        void SpeedQr.stop();
      }

      return false;
    });
  }, []);

  const show = (focusOffice = false): void => {
    setOpen(true);
    requestAnimationFrame(() => (focusOffice ? detailRef.current : inputRef.current)?.focus());
  };

  const close = useCallback((): void => {
    stopScan();
    setOpen(false);
  }, [stopScan]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        close();
      }
    };

    document.addEventListener('keydown', onKey);

    return () => document.removeEventListener('keydown', onKey);
  }, [close]);

  const askForTracking = (): void => {
    say(
      'What is your tracking number? It is on your confirmation email and printed slip, and looks like SPD-20261016-ABC123. You can also scan the QR code with the camera button.',
    );
    requestAnimationFrame(() => inputRef.current?.focus());
  };

  const ask = async (raw: string): Promise<SpeedyContext | null | undefined> => {
    const text = raw.trim();

    if (!text || loading) {
      return undefined;
    }

    setMessages((list) => [...list, { id: nextId.current++, role: 'user', text }]);
    setDraft('');
    setLoading(true);
    scrollToEnd(logRef.current);

    try {
      const response = await postJson(routes.speedyAsk(), {
        question: text,
        tracking: contextRef.current?.tracking ?? null,
      });

      if (response.status === 429) {
        say('You’ve asked a lot in a short time — please wait a moment and try again.');
      } else if (response.status === 422) {
        say('Please type a slightly longer question.');
      } else if (!response.ok) {
        say('Sorry, I couldn’t answer that right now. Please try again shortly.');
      } else {
        const data = (await response.json()) as { answer: string; context?: SpeedyContext | null };

        // A tracking number in the message switches the topic; an explicit
        // null means the old topic is gone.
        if (data.context !== undefined) {
          setContext(data.context);
        }

        say(data.answer);

        return data.context;
      }
    } catch {
      say('Sorry, something went wrong. Please try again.');
    } finally {
      setLoading(false);
    }

    return undefined;
  };

  const openMessages = (): void => {
    const current = contextRef.current;

    if (!current) {
      show();
      askForTracking();

      return;
    }

    setOpen(true);
    setTab('office');
    setOfficeError('');

    if (current.verified) {
      void loadThread();
    } else {
      requestAnimationFrame(() => detailRef.current?.focus());
    }
  };

  // The office's reply email links here with ?speedy=messages.
  useEffect(() => {
    if (initialContext && new URLSearchParams(window.location.search).get('speedy') === 'messages') {
      openMessages();
    }
    // Once, on the page that was opened from the email.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const openThread = async (tracking: string): Promise<void> => {
    const switched = await ask(tracking);

    if (switched?.tracking === tracking) {
      contextRef.current = switched;
      openMessages();
    }
  };

  const verify = async (): Promise<void> => {
    const current = contextRef.current;

    if (officeBusy || !detail.trim() || !current) {
      return;
    }

    setOfficeBusy(true);
    setOfficeError('');

    try {
      const response = await postJson(routes.trackMessagesVerify(current.tracking), { detail });
      const data = (await response.json().catch(() => ({}))) as {
        messages?: ThreadMessage[];
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (response.status === 429) {
        setOfficeError('Too many tries. Please wait a minute and try again.');
      } else if (!response.ok) {
        setOfficeError(firstError(data, 'That didn’t work. Please try again.'));
      } else {
        setDetail('');
        patchContext({ verified: true });
        contextRef.current = { ...current, verified: true };
        showThread(data.messages ?? []);
        listen(current.tracking);
        void refreshInbox();
      }
    } catch {
      setOfficeError('Something went wrong. Please try again.');
    } finally {
      setOfficeBusy(false);
    }
  };

  const sendToOffice = async (): Promise<void> => {
    const current = contextRef.current;

    if (officeBusy || !officeDraft.trim() || !current) {
      return;
    }

    setOfficeBusy(true);
    setOfficeError('');

    const form = new FormData();
    form.append('body', officeDraft);

    const file = fileRef.current?.files?.[0];

    if (file) {
      form.append('attachment', file);
    }

    try {
      const response = await fetch(routes.trackMessagesStore(current.tracking), {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: form,
      });
      const data = (await response.json().catch(() => ({}))) as {
        messages?: ThreadMessage[];
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (response.status === 429) {
        setOfficeError('You’ve sent a lot in a short time — please wait a moment.');
      } else if (response.status === 413) {
        setOfficeError('That file is too large to send here. Try a smaller photo or PDF.');
      } else if (!response.ok) {
        setOfficeError(firstError(data, 'Your message was not sent. Please try again.'));
      } else {
        setOfficeDraft('');
        setOfficeFileName('');

        if (fileRef.current) {
          fileRef.current.value = '';
        }

        showThread(data.messages ?? []);
      }
    } catch {
      setOfficeError('Something went wrong. Your message was not sent.');
    } finally {
      setOfficeBusy(false);
    }
  };

  const startScan = (): void => {
    setScanError('');
    setScanning(true);

    let handled = false;

    requestAnimationFrame(() => {
      void SpeedQr.start(
        READER_ID,
        (decoded) => {
          if (handled) {
            return;
          }

          const tracking = SpeedQr.extractTracking(decoded);

          if (!tracking) {
            setScanError(SpeedQr.FOREIGN_CODE_MESSAGE);

            return;
          }

          handled = true;
          stopScan();
          void ask(tracking);
        },
        (error) => setScanError(SpeedQr.describe(error)),
      );
    });
  };

  const clearContext = (): void => {
    setContextState(null);
    setTab('bot');
    setThread([]);
    say('Okay — ask me anything about the service, or give me another tracking number.');
  };

  return (
    <div className="speedy fixed bottom-5 right-5 z-50 flex flex-col items-end print:hidden sm:bottom-6 sm:right-6">
      {open ? (
        <section
          id="speedyPanel"
          className="mb-3 flex w-[calc(100vw-2.5rem)] max-w-[400px] flex-col overflow-hidden rounded-[20px] border border-[rgba(0,64,4,0.12)] bg-white shadow-[0_1px_2px_rgba(0,64,4,0.08),0_18px_48px_-16px_rgba(0,64,4,0.45)]"
          role="dialog"
          aria-modal="false"
          aria-labelledby="speedyTitle"
        >
          <header className="flex items-center gap-3 border-b border-[rgba(0,64,4,0.08)] bg-[#f5faf3] px-4 py-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#135226]">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src="/images/portal/speedy.png" alt="" className="h-[26px] w-[32px] object-contain" />
            </span>
            <div className="min-w-0 flex-1">
              <h2 id="speedyTitle" className="font-display text-[19px] font-black leading-none text-[#004004]">
                Speedy
              </h2>
              <p className="mt-1 text-[12.5px] leading-snug text-[#4b5a50]">
                {tab === 'office' ? 'Messages with City Hall staff' : 'Automated assistant · answers can be wrong'}
              </p>
            </div>
            <button
              type="button"
              onClick={close}
              aria-label="Close Speedy"
              className="flex h-11 w-11 shrink-0 touch-manipulation items-center justify-center rounded-full text-[#4b5a50] transition-colors hover:bg-white hover:text-[#004004] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#01721a]"
            >
              <CloseIcon className="h-5 w-5" />
            </button>
          </header>

          {context ? (
            <>
              <div className="flex items-center gap-2 border-b border-[rgba(0,64,4,0.08)] px-4 py-2.5">
                <div className="min-w-0 flex-1">
                  <p className="flex items-center gap-2 text-[13px] text-[#4b5a50]">
                    About <span className="cz-id px-2 py-0.5 text-[12.5px]">{context.tracking}</span>
                  </p>
                  <p className="mt-1 truncate text-[13.5px] font-semibold text-[#1a1b1a]">
                    {context.type} · {context.status}
                  </p>
                </div>
                <a href={context.url} className="shrink-0 text-[13.5px] font-bold text-[#01721a] underline-offset-2 hover:underline">
                  Open
                </a>
                <button
                  type="button"
                  onClick={clearContext}
                  aria-label="Talk about something else"
                  className="-mr-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#4b5a50] hover:bg-[#eef8ea]"
                >
                  <CloseIcon className="h-4 w-4" />
                </button>
              </div>

              <div className="speedy-tabs" role="tablist" aria-label="Who you are talking to">
                <button
                  type="button"
                  role="tab"
                  id="speedyTabBot"
                  aria-selected={tab === 'bot'}
                  aria-controls="speedyBotPane"
                  onClick={() => setTab('bot')}
                  className="speedy-tab"
                >
                  Speedy
                </button>
                <button
                  type="button"
                  role="tab"
                  id="speedyTabOffice"
                  aria-selected={tab === 'office'}
                  aria-controls="speedyOfficePane"
                  onClick={openMessages}
                  className="speedy-tab"
                >
                  Office messages
                  {context.unread > 0 ? <span className="speedy-count">{context.unread}</span> : null}
                </button>
              </div>
            </>
          ) : null}

          {tab === 'bot' ? (
            <div id="speedyBotPane" role="tabpanel" aria-labelledby="speedyTabBot" className="flex min-h-0 flex-1 flex-col gap-3 p-4">
              {waitingElsewhere.map((t) => (
                <button key={t.tracking} type="button" onClick={() => void openThread(t.tracking)} className="speedy-waiting">
                  <span className="speedy-count">{t.unread}</span>
                  <span className="min-w-0 flex-1 text-left">
                    <span className="block font-bold text-[#004004]">
                      {t.unread === 1 ? 'New reply from the office' : 'New replies from the office'}
                    </span>
                    <span className="block truncate text-[12.5px] text-[#4b5a50]">
                      {t.type} · {t.tracking}
                    </span>
                  </span>
                  <span className="shrink-0 font-bold text-[#01721a]">Read</span>
                </button>
              ))}

              <div className="speedy-log space-y-3 overflow-y-auto" ref={logRef} role="log" aria-live="polite" aria-label="Conversation with Speedy">
                <div className="speedy-msg-bot">
                  <span className="speedy-who">Speedy</span>
                  <p className="speedy-bubble">{greeting}</p>
                </div>

                {messages.map((message) => (
                  <div key={message.id} className={message.role === 'user' ? 'speedy-msg-user' : 'speedy-msg-bot'}>
                    {message.role !== 'user' ? <span className="speedy-who">Speedy</span> : null}
                    <p className="speedy-bubble whitespace-pre-line">{message.text}</p>
                  </div>
                ))}

                {loading ? (
                  <div className="flex items-center gap-1.5 pl-1" role="status" aria-label="Speedy is typing">
                    {[0, 200, 400].map((delay) => (
                      <span
                        key={delay}
                        className="cr-typing-dot h-1.5 w-1.5 rounded-full bg-[#01721a]"
                        style={{ animationDelay: `${delay}ms` }}
                      />
                    ))}
                  </div>
                ) : null}
              </div>

              {scanning ? (
                <div className="space-y-2">
                  <QrCamera id={READER_ID} />
                  {scanError ? (
                    <p className="rounded-[12px] bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-800" role="alert">
                      {scanError}
                    </p>
                  ) : null}
                  <button type="button" onClick={stopScan} className="cz-btn cz-btn-secondary cz-btn-sm w-full">
                    Stop camera
                  </button>
                </div>
              ) : null}

              {messages.length === 0 && !scanning ? (
                <div className="flex flex-wrap gap-2">
                  {!context ? (
                    <button type="button" onClick={askForTracking} className="speedy-chip speedy-chip-strong">
                      Check my request
                    </button>
                  ) : null}
                  {context?.canVerify ? (
                    <button type="button" onClick={openMessages} className="speedy-chip speedy-chip-strong">
                      Message the office
                    </button>
                  ) : null}
                  {suggestions.map((suggestion) => (
                    <button key={suggestion} type="button" onClick={() => void ask(suggestion)} className="speedy-chip">
                      {suggestion}
                    </button>
                  ))}
                </div>
              ) : null}

              {!scanning ? (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    void ask(draft);
                  }}
                  className="flex items-center gap-2"
                >
                  <label htmlFor="speedyInput" className="sr-only">
                    Message Speedy
                  </label>
                  <input
                    id="speedyInput"
                    type="text"
                    ref={inputRef}
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    disabled={loading}
                    maxLength={500}
                    autoComplete="off"
                    placeholder="Ask, or type a tracking number…"
                    className="req-field min-w-0 flex-1 !min-h-[44px] !py-2 !text-[15px]"
                  />
                  <button type="button" onClick={startScan} aria-label="Scan a QR code" className="speedy-icon-btn">
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
                      <rect x="3.5" y="3.5" width="6" height="6" rx="1" />
                      <rect x="14.5" y="3.5" width="6" height="6" rx="1" />
                      <rect x="3.5" y="14.5" width="6" height="6" rx="1" />
                      <path strokeLinecap="round" d="M14.5 14.5h2m4 0h-2m0 0v2m0 4h-6v-2" />
                    </svg>
                  </button>
                  <button
                    type="submit"
                    disabled={loading || !draft.trim()}
                    aria-label="Send"
                    className="cz-btn cz-btn-primary h-11 w-11 shrink-0 !min-h-[44px] !p-0"
                  >
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                  </button>
                </form>
              ) : null}
            </div>
          ) : (
            <div id="speedyOfficePane" role="tabpanel" aria-labelledby="speedyTabOffice" className="flex min-h-0 flex-1 flex-col gap-3 p-4">
              {context && !context.canVerify ? (
                <p className="text-[14.5px] leading-relaxed text-[#353535]">
                  This request has no email or mobile number on file, so messaging is not available for it. Please
                  contact the handling office directly.
                </p>
              ) : null}

              {context && context.canVerify && !context.verified ? (
                <form
                  onSubmit={(event) => {
                    event.preventDefault();
                    void verify();
                  }}
                  className="space-y-3"
                >
                  <p className="text-[14.5px] leading-relaxed text-[#353535]">
                    Your messages with the office are private. To open them, type the{' '}
                    <strong>email address or mobile number</strong> you filed this request with.
                  </p>
                  <label htmlFor="speedyDetail" className="sr-only">
                    Email or mobile number on this request
                  </label>
                  <input
                    id="speedyDetail"
                    type="text"
                    ref={detailRef}
                    value={detail}
                    onChange={(event) => setDetail(event.target.value)}
                    autoComplete="off"
                    required
                    placeholder="Email or mobile number"
                    className="req-field !min-h-[44px] !py-2 !text-[15px]"
                  />
                  <OfficeError message={officeError} />
                  <button type="submit" disabled={officeBusy || !detail.trim()} className="cz-btn cz-btn-primary cz-btn-sm w-full">
                    Open messages
                  </button>
                </form>
              ) : null}

              {context?.verified ? (
                <div className="flex min-h-0 flex-1 flex-col gap-3">
                  <p className="text-[12.5px] leading-snug text-[#4b5a50]">
                    Real City Hall staff reply here, usually during office hours (Mon–Fri, 8 AM–5 PM).
                  </p>

                  <div className="speedy-log space-y-3 overflow-y-auto" ref={threadRef} role="log" aria-live="polite" aria-label="Messages with the office">
                    {thread.length === 0 ? (
                      <p className="py-4 text-center text-[14px] text-[#4b5a50]">
                        No messages yet. Ask the office anything about this request.
                      </p>
                    ) : null}
                    {thread.map((message) => (
                      <div
                        key={message.id}
                        className={
                          message.from === 'citizen'
                            ? 'speedy-msg-user'
                            : message.from === 'system'
                              ? 'speedy-msg-update'
                              : 'speedy-msg-staff'
                        }
                      >
                        {message.from === 'staff' ? (
                          <span className="speedy-who-staff">
                            <span>{message.name}</span>
                            <span className="speedy-staff-mark">Staff</span>
                          </span>
                        ) : null}
                        <div className="speedy-bubble speedy-bubble-rich">
                          <p className="whitespace-pre-line">{message.body}</p>
                          {message.attachment ? (
                            <a href={message.attachment.url} className="speedy-attachment">
                              <ClipIcon />
                              <span className="truncate">{message.attachment.name}</span>
                            </a>
                          ) : null}
                        </div>
                        <span className="speedy-time">
                          {message.from === 'citizen' ? 'You · ' : message.from === 'system' ? 'Update · ' : ''}
                          {message.at}
                        </span>
                      </div>
                    ))}
                  </div>

                  <form
                    onSubmit={(event) => {
                      event.preventDefault();
                      void sendToOffice();
                    }}
                    className="space-y-2"
                  >
                    <label htmlFor="speedyOfficeBody" className="sr-only">
                      Message to the office
                    </label>
                    <textarea
                      id="speedyOfficeBody"
                      value={officeDraft}
                      onChange={(event) => setOfficeDraft(event.target.value)}
                      rows={2}
                      maxLength={5000}
                      disabled={officeBusy}
                      placeholder="Write to the office…"
                      className="req-field !min-h-[64px] !py-2 !text-[15px]"
                    />
                    <OfficeError message={officeError} />
                    <div className="flex items-center gap-2">
                      <label className="speedy-icon-btn cursor-pointer" title="Attach a file">
                        <span className="sr-only">Attach a file</span>
                        <input
                          type="file"
                          className="sr-only"
                          ref={fileRef}
                          accept={accept}
                          onChange={(event) => setOfficeFileName(event.target.files?.[0]?.name ?? '')}
                        />
                        <ClipIcon className="h-5 w-5" />
                      </label>
                      <span className="min-w-0 flex-1 truncate text-[12.5px] text-[#4b5a50]">{officeFileName}</span>
                      <button type="submit" disabled={officeBusy || !officeDraft.trim()} className="cz-btn cz-btn-primary cz-btn-sm">
                        Send to office
                      </button>
                    </div>
                  </form>
                </div>
              ) : null}
            </div>
          )}
        </section>
      ) : null}

      {/* New replies, announced without opening the panel. */}
      <p className="sr-only" role="status">
        {inbox.total > 0
          ? `${inbox.total} ${inbox.total === 1 ? 'new reply' : 'new replies'} from the office in Speedy`
          : ''}
      </p>

      <button
        type="button"
        onClick={() => (open ? close() : show(tab === 'office'))}
        aria-expanded={open}
        aria-controls="speedyPanel"
        aria-label="Ask Speedy"
        className="group relative flex h-[69px] w-[69px] touch-manipulation items-center justify-center rounded-full bg-[#135226] shadow-[0_8px_24px_-8px_rgba(0,64,4,0.7)] transition-transform hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c] motion-reduce:transition-none sm:h-[80px] sm:w-[80px]"
      >
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/images/portal/speedy.png" alt="" className="h-[48px] w-[59px] object-contain sm:h-[56px] sm:w-[69px]" />
        {inbox.total > 0 ? (
          <span className="speedy-badge" aria-hidden="true">
            {inbox.total > 9 ? '9+' : inbox.total}
          </span>
        ) : null}
      </button>
    </div>
  );
}

function OfficeError({ message }: { message: string }) {
  return message ? (
    <p className="rounded-[12px] bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-800" role="alert">
      {message}
    </p>
  ) : null;
}

function CloseIcon({ className }: { className: string }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24" aria-hidden="true">
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
    </svg>
  );
}

function ClipIcon({ className = 'h-4 w-4 shrink-0' }: { className?: string }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true">
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        d="M18.4 12.8 12 19.2a4.5 4.5 0 0 1-6.4-6.4l7.1-7.1a3 3 0 0 1 4.3 4.3l-7.1 7.1a1.5 1.5 0 0 1-2.2-2.2l6.4-6.3"
      />
    </svg>
  );
}
