{{-- Speedy — the one assistant on every citizen page, and the citizen's way to
     talk to the office.

     It replaces two that did not know about each other: a mascot bubble on the
     portal pages that could only ask for a tracking number and send you to the
     tracking page, and an "Ask a question" chat on that page that knew only the
     one request. Now there is one window: ask about the service (the site FAQ,
     the requirements list), then give a tracking number — typed, or scanned
     with the camera right here — and Speedy talks about that request.

     With a request in view, a second tab holds the conversation with the office
     itself. The two are kept apart on purpose: Speedy is automated and can be
     wrong, staff replies are official, and a citizen must always be able to
     tell which is which. Every staff reply carries the person's name, their
     office and a "Staff" mark. The conversation opens after the citizen
     confirms the email or mobile number on the request, and the launcher shows
     how many replies are waiting — on any page.

     Pass :document on a page that is about one request (the tracking page, the
     receipt) and Speedy starts there. ?speedy=messages in the URL (the link in
     the office's reply email) opens straight on the conversation. --}}
@props(['document' => null])

@php
    $context = $document && ! $document->isInternal()
        ? \App\Support\Ai\SpeedyContext::for($document, \App\Support\CitizenThreadAccess::isVerified(request(), $document))
        : null;

    // No query unless this browser has confirmed a request: the landing page
    // still renders without the database for nearly every visitor.
    $inbox = \App\Support\Ai\SpeedyContext::inbox(request());

    // Fixed text, no query: Speedy names the services itself when asked.
    $generalSuggestions = [
        'How do I file a request?',
        'What do I need to bring?',
        'Do I need an account?',
    ];

    $endpoints = [
        'ask' => route('speedy.ask'),
        'inbox' => route('speedy.inbox'),
        'thread' => route('track.messages.index', ['trackingNumber' => '__TN__']),
        'verify' => route('track.messages.verify', ['trackingNumber' => '__TN__']),
        'post' => route('track.messages.store', ['trackingNumber' => '__TN__']),
    ];
@endphp

@include('partials.qr-scan-helpers')

<div class="speedy fixed bottom-5 right-5 z-50 flex flex-col items-end print:hidden sm:bottom-6 sm:right-6"
     x-data="speedyChat({
        context: @js($context),
        inbox: @js($inbox),
        endpoints: @js($endpoints),
        csrf: @js(csrf_token()),
        suggestions: @js($generalSuggestions),
        accept: @js(\App\Support\UploadRules::accept()),
     })"
     @keydown.escape.window="close()"
     @speedy:open-messages.window="openMessages()">

    {{-- ── Chat panel ─────────────────────────────────────────────────────── --}}
    <section id="speedyPanel" x-show="open" x-cloak x-transition.origin.bottom.right
             class="speedy-panel mb-3 flex w-[calc(100vw-2.5rem)] max-w-[400px] flex-col overflow-hidden rounded-[20px] border border-[rgba(0,64,4,0.12)] bg-white shadow-[0_1px_2px_rgba(0,64,4,0.08),0_18px_48px_-16px_rgba(0,64,4,0.45)]"
             role="dialog" aria-modal="false" aria-labelledby="speedyTitle">

        <header class="flex items-center gap-3 border-b border-[rgba(0,64,4,0.08)] bg-[#f5faf3] px-4 py-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#135226]">
                <img src="{{ asset('images/portal/speedy.png') }}" alt="" class="h-[26px] w-[32px] object-contain">
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="speedyTitle" class="font-display text-[19px] font-black leading-none text-[#004004]">Speedy</h2>
                <p class="mt-1 text-[12.5px] leading-snug text-[#4b5a50]"
                   x-text="tab === 'office' ? 'Messages with City Hall staff' : 'Automated assistant · answers can be wrong'">Automated assistant · answers can be wrong</p>
            </div>
            <button type="button" @click="close()" aria-label="Close Speedy"
                    class="flex h-11 w-11 shrink-0 touch-manipulation items-center justify-center rounded-full text-[#4b5a50] transition-colors hover:bg-white hover:text-[#004004] focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-[#01721a]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </header>

        {{-- The request being discussed, when there is one. --}}
        <div x-show="context" x-cloak class="flex items-center gap-2 border-b border-[rgba(0,64,4,0.08)] px-4 py-2.5">
            <div class="min-w-0 flex-1">
                <p class="flex items-center gap-2 text-[13px] text-[#4b5a50]">
                    About <span class="cz-id px-2 py-0.5 text-[12.5px]" x-text="context?.tracking"></span>
                </p>
                <p class="mt-1 truncate text-[13.5px] font-semibold text-[#1a1b1a]" x-text="context ? context.type + ' · ' + context.status : ''"></p>
            </div>
            <a :href="context?.url" class="shrink-0 text-[13.5px] font-bold text-[#01721a] underline-offset-2 hover:underline">Open</a>
            <button type="button" @click="clearContext()" aria-label="Talk about something else"
                    class="-mr-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#4b5a50] hover:bg-[#eef8ea]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Two tabs, one for the robot and one for the people. --}}
        <div x-show="context" x-cloak class="speedy-tabs" role="tablist" aria-label="Who you are talking to">
            <button type="button" role="tab" id="speedyTabBot" :aria-selected="(tab === 'bot').toString()" aria-controls="speedyBotPane"
                    @click="tab = 'bot'" class="speedy-tab">Speedy</button>
            <button type="button" role="tab" id="speedyTabOffice" :aria-selected="(tab === 'office').toString()" aria-controls="speedyOfficePane"
                    @click="openMessages()" class="speedy-tab">
                Office messages
                <span x-show="context?.unread > 0" x-text="context?.unread" class="speedy-count"></span>
            </button>
        </div>

        {{-- ── Speedy (automated) ─────────────────────────────────────────── --}}
        <div id="speedyBotPane" x-show="tab === 'bot'" role="tabpanel" aria-labelledby="speedyTabBot"
             class="flex min-h-0 flex-1 flex-col gap-3 p-4">
            {{-- Replies waiting on other requests this browser has confirmed. --}}
            <template x-for="t in waitingElsewhere" :key="t.tracking">
                <button type="button" @click="openThread(t.tracking)" class="speedy-waiting">
                    <span class="speedy-count" x-text="t.unread"></span>
                    <span class="min-w-0 flex-1 text-left">
                        <span class="block font-bold text-[#004004]" x-text="t.unread === 1 ? 'New reply from the office' : 'New replies from the office'"></span>
                        <span class="block truncate text-[12.5px] text-[#4b5a50]" x-text="t.type + ' · ' + t.tracking"></span>
                    </span>
                    <span class="shrink-0 font-bold text-[#01721a]">Read</span>
                </button>
            </template>

            {{-- Conversation --}}
            <div class="speedy-log space-y-3 overflow-y-auto" x-ref="log"
                 role="log" aria-live="polite" aria-label="Conversation with Speedy">
                {{-- Opening line, so the window never opens on nothing. Fixed
                     when the page loads: switching requests later must not
                     rewrite what was already said. --}}
                <div class="speedy-msg-bot">
                    <span class="speedy-who">Speedy</span>
                    <p class="speedy-bubble" x-text="greeting"></p>
                </div>

                <template x-for="m in messages" :key="m.id">
                    <div :class="m.role === 'user' ? 'speedy-msg-user' : 'speedy-msg-bot'">
                        <span class="speedy-who" x-show="m.role !== 'user'">Speedy</span>
                        <p class="speedy-bubble" x-text="m.text"></p>
                    </div>
                </template>

                <div x-show="loading" class="flex items-center gap-1.5 pl-1" role="status" aria-label="Speedy is typing">
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-[#01721a]" style="animation-delay:0ms"></span>
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-[#01721a]" style="animation-delay:200ms"></span>
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-[#01721a]" style="animation-delay:400ms"></span>
                </div>
            </div>

            {{-- Scan a QR without leaving the chat. .qr-surface keeps the decoder
                 reading undistorted pixels (see .qr-viewport in app.css). --}}
            <div x-show="scanning" x-cloak class="space-y-2">
                <div class="qr-viewport relative overflow-hidden rounded-[16px] bg-[#1e1e1e]">
                    <div id="speedyQrReader" class="qr-surface"></div>
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative aspect-square w-[70%]">
                            <span class="absolute left-0 top-0 h-[30%] w-[30%] rounded-tl-[11px] border-l-[6px] border-t-[6px] border-white"></span>
                            <span class="absolute right-0 top-0 h-[30%] w-[30%] rounded-tr-[11px] border-r-[6px] border-t-[6px] border-white"></span>
                            <span class="absolute bottom-0 left-0 h-[30%] w-[30%] rounded-bl-[11px] border-b-[6px] border-l-[6px] border-white"></span>
                            <span class="absolute bottom-0 right-0 h-[30%] w-[30%] rounded-br-[11px] border-b-[6px] border-r-[6px] border-white"></span>
                        </div>
                    </div>
                </div>
                <p x-show="scanError" x-text="scanError" class="rounded-[12px] bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-800" role="alert"></p>
                <button type="button" @click="stopScan()" class="cz-btn cz-btn-secondary cz-btn-sm w-full">Stop camera</button>
            </div>

            {{-- Suggestions, until the conversation starts. --}}
            <div class="flex flex-wrap gap-2" x-show="messages.length === 0 && ! scanning">
                <button type="button" x-show="! context" @click="askForTracking()" class="speedy-chip speedy-chip-strong">
                    Check my request
                </button>
                <button type="button" x-show="context && context.can_verify" @click="openMessages()" class="speedy-chip speedy-chip-strong">
                    Message the office
                </button>
                <template x-for="s in suggestions" :key="s">
                    <button type="button" @click="ask(s)" class="speedy-chip" x-text="s"></button>
                </template>
            </div>

            {{-- Composer --}}
            <form @submit.prevent="ask(draft)" class="flex items-center gap-2" x-show="! scanning">
                <label for="speedyInput" class="sr-only">Message Speedy</label>
                <input id="speedyInput" type="text" x-model="draft" x-ref="input" :disabled="loading" maxlength="500"
                       autocomplete="off" placeholder="Ask, or type a tracking number…"
                       class="req-field min-w-0 flex-1 !min-h-[44px] !py-2 !text-[15px]">
                <button type="button" @click="startScan()" aria-label="Scan a QR code"
                        class="speedy-icon-btn">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="6" height="6" rx="1"/>
                        <rect x="14.5" y="3.5" width="6" height="6" rx="1"/>
                        <rect x="3.5" y="14.5" width="6" height="6" rx="1"/>
                        <path stroke-linecap="round" d="M14.5 14.5h2m4 0h-2m0 0v2m0 4h-6v-2"/>
                    </svg>
                </button>
                <button type="submit" :disabled="loading || ! draft.trim()" aria-label="Send"
                        class="cz-btn cz-btn-primary h-11 w-11 shrink-0 !min-h-[44px] !p-0">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/>
                    </svg>
                </button>
            </form>
        </div>

        {{-- ── Office messages (people) ───────────────────────────────────── --}}
        <div id="speedyOfficePane" x-show="tab === 'office'" x-cloak role="tabpanel" aria-labelledby="speedyTabOffice"
             class="flex min-h-0 flex-1 flex-col gap-3 p-4">

            {{-- Nothing on file to confirm against. --}}
            <p x-show="context && ! context.can_verify" class="text-[14.5px] leading-relaxed text-[#353535]">
                This request has no email or mobile number on file, so messaging is not available for it.
                Please contact the handling office directly.
            </p>

            {{-- Confirm first: reading the conversation needs it, not only writing. --}}
            <form x-show="context && context.can_verify && ! context.verified" @submit.prevent="verify()" class="space-y-3">
                <p class="text-[14.5px] leading-relaxed text-[#353535]">
                    Your messages with the office are private. To open them, type the
                    <strong>email address or mobile number</strong> you filed this request with.
                </p>
                <label for="speedyDetail" class="sr-only">Email or mobile number on this request</label>
                <input id="speedyDetail" type="text" x-model="detail" x-ref="detail" autocomplete="off" required
                       placeholder="Email or mobile number" class="req-field !min-h-[44px] !py-2 !text-[15px]">
                <p x-show="officeError" x-text="officeError" class="rounded-[12px] bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-800" role="alert"></p>
                <button type="submit" :disabled="officeBusy || ! detail.trim()" class="cz-btn cz-btn-primary cz-btn-sm w-full">Open messages</button>
            </form>

            {{-- The conversation. --}}
            <template x-if="context && context.verified">
                <div class="flex min-h-0 flex-1 flex-col gap-3">
                    <p class="text-[12.5px] leading-snug text-[#4b5a50]">
                        Real City Hall staff reply here, usually during office hours (Mon–Fri, 8 AM–5 PM).
                    </p>

                    <div class="speedy-log space-y-3 overflow-y-auto" x-ref="thread" role="log" aria-live="polite" aria-label="Messages with the office">
                        <p x-show="thread.length === 0" class="py-4 text-center text-[14px] text-[#4b5a50]">
                            No messages yet. Ask the office anything about this request.
                        </p>
                        <template x-for="m in thread" :key="m.id">
                            <div :class="m.from === 'citizen' ? 'speedy-msg-user' : (m.from === 'system' ? 'speedy-msg-update' : 'speedy-msg-staff')">
                                <span x-show="m.from === 'staff'" class="speedy-who-staff">
                                    <span x-text="m.name"></span>
                                    <span class="speedy-staff-mark">Staff</span>
                                </span>
                                <div class="speedy-bubble speedy-bubble-rich">
                                    <p x-text="m.body"></p>
                                    <a x-show="m.attachment" :href="m.attachment?.url" class="speedy-attachment">
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.4 12.8 12 19.2a4.5 4.5 0 0 1-6.4-6.4l7.1-7.1a3 3 0 0 1 4.3 4.3l-7.1 7.1a1.5 1.5 0 0 1-2.2-2.2l6.4-6.3"/></svg>
                                        <span class="truncate" x-text="m.attachment?.name"></span>
                                    </a>
                                </div>
                                <span class="speedy-time" x-text="(m.from === 'citizen' ? 'You · ' : (m.from === 'system' ? 'Update · ' : '')) + m.at"></span>
                            </div>
                        </template>
                    </div>

                    <form @submit.prevent="sendToOffice()" class="space-y-2">
                        <label for="speedyOfficeBody" class="sr-only">Message to the office</label>
                        <textarea id="speedyOfficeBody" x-model="officeDraft" rows="2" maxlength="5000" :disabled="officeBusy"
                                  placeholder="Write to the office…" class="req-field !min-h-[64px] !py-2 !text-[15px]"></textarea>
                        <p x-show="officeError" x-text="officeError" class="rounded-[12px] bg-red-50 px-3 py-2 text-[13px] font-semibold text-red-800" role="alert"></p>
                        <div class="flex items-center gap-2">
                            <label class="speedy-icon-btn cursor-pointer" title="Attach a file">
                                <span class="sr-only">Attach a file</span>
                                <input type="file" class="sr-only" x-ref="officeFile" :accept="accept" @change="officeFileName = $event.target.files[0]?.name || ''">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.4 12.8 12 19.2a4.5 4.5 0 0 1-6.4-6.4l7.1-7.1a3 3 0 0 1 4.3 4.3l-7.1 7.1a1.5 1.5 0 0 1-2.2-2.2l6.4-6.3"/></svg>
                            </label>
                            <span class="min-w-0 flex-1 truncate text-[12.5px] text-[#4b5a50]" x-text="officeFileName"></span>
                            <button type="submit" :disabled="officeBusy || ! officeDraft.trim()" class="cz-btn cz-btn-primary cz-btn-sm">Send to office</button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </section>

    {{-- New replies, announced without opening the panel. --}}
    <p class="sr-only" role="status" x-text="inbox.total > 0 ? inbox.total + (inbox.total === 1 ? ' new reply' : ' new replies') + ' from the office in Speedy' : ''"></p>

    {{-- ── Launcher ───────────────────────────────────────────────────────── --}}
    <button type="button" @click="toggle()" :aria-expanded="open.toString()" aria-controls="speedyPanel"
            class="group relative flex h-[69px] w-[69px] touch-manipulation items-center justify-center rounded-full bg-[#135226] shadow-[0_8px_24px_-8px_rgba(0,64,4,0.7)] transition-transform hover:scale-105 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#8dff3c] motion-reduce:transition-none sm:h-[80px] sm:w-[80px]"
            aria-label="Ask Speedy">
        <img src="{{ asset('images/portal/speedy.png') }}" alt=""
             class="h-[48px] w-[59px] object-contain sm:h-[56px] sm:w-[69px]">
        <span x-show="inbox.total > 0" x-cloak x-text="inbox.total > 9 ? '9+' : inbox.total" aria-hidden="true"
              class="speedy-badge"></span>
    </button>
</div>

@once
<script>
    window.speedyChat = function (config) {
        const url = (template, tracking) => template.replace('__TN__', encodeURIComponent(tracking));

        return {
            context: config.context,
            inbox: config.inbox,
            accept: config.accept,
            greeting: config.context
                ? `Hi! Ask me anything about ${config.context.tracking} — when it will be ready, what happens next, or what to bring.`
                : 'Hi, I’m Speedy! Ask me how filing and tracking work, or give me your tracking number and I’ll check your request.',
            tab: 'bot',
            open: false,
            loading: false,
            scanning: false,
            scanError: '',
            draft: '',
            messages: [],
            nextId: 1,
            // Office tab
            thread: [],
            detail: '',
            officeDraft: '',
            officeFileName: '',
            officeBusy: false,
            officeError: '',
            subscribed: {},

            init() {
                this.inbox.threads.forEach((t) => this.listen(t.tracking));
                if (this.context?.verified) this.listen(this.context.tracking);

                // Fallback when there is no live socket: check for replies now
                // and then, only for a browser that has confirmed a request.
                setInterval(() => {
                    if (this.inbox.threads.length || this.context?.verified) this.refreshInbox();
                    if (this.open && this.tab === 'office' && this.context?.verified) this.loadThread();
                }, 45000);

                // The office's reply email links here with ?speedy=messages.
                if (new URLSearchParams(window.location.search).get('speedy') === 'messages' && this.context) {
                    this.$nextTick(() => this.openMessages());
                }
            },

            get suggestions() {
                return this.context ? this.context.suggestions : config.suggestions;
            },

            get waitingElsewhere() {
                return this.inbox.threads.filter((t) => t.unread > 0 && t.tracking !== this.context?.tracking);
            },

            toggle() {
                this.open ? this.close() : this.show();
            },

            show() {
                this.open = true;
                this.$nextTick(() => (this.tab === 'office' ? this.$refs.detail : this.$refs.input)?.focus());
            },

            close() {
                this.stopScan();
                this.open = false;
            },

            say(text) {
                this.messages.push({ id: this.nextId++, role: 'assistant', text });
                this.scrollLog();
            },

            askForTracking() {
                this.say('What is your tracking number? It is on your confirmation email and printed slip, and looks like SPD-20261016-ABC123. You can also scan the QR code with the camera button.');
                this.$nextTick(() => this.$refs.input?.focus());
            },

            clearContext() {
                this.context = null;
                this.tab = 'bot';
                this.thread = [];
                this.say('Okay — ask me anything about the service, or give me another tracking number.');
            },

            setContext(context) {
                const changed = context?.tracking !== this.context?.tracking;
                this.context = context;
                if (changed) {
                    this.thread = [];
                    this.officeError = '';
                    this.detail = '';
                }
                if (context?.verified) this.listen(context.tracking);
            },

            async post(endpoint, body, isForm = false) {
                return fetch(endpoint, {
                    method: 'POST',
                    headers: Object.assign(
                        { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                        isForm ? {} : { 'Content-Type': 'application/json' },
                    ),
                    body: isForm ? body : JSON.stringify(body),
                });
            },

            async ask(text) {
                text = (text || '').trim();
                if (!text || this.loading) return;

                this.messages.push({ id: this.nextId++, role: 'user', text });
                this.draft = '';
                this.loading = true;
                this.scrollLog();

                try {
                    const res = await this.post(config.endpoints.ask, { question: text, tracking: this.context?.tracking ?? null });

                    if (res.status === 429) {
                        this.say('You’ve asked a lot in a short time — please wait a moment and try again.');
                    } else if (res.status === 422) {
                        this.say('Please type a slightly longer question.');
                    } else if (!res.ok) {
                        this.say('Sorry, I couldn’t answer that right now. Please try again shortly.');
                    } else {
                        const data = await res.json();
                        // A tracking number in the message switches the topic;
                        // an explicit null means the old topic is gone.
                        if (data.context !== undefined) this.setContext(data.context);
                        this.say(data.answer);
                    }
                } catch (e) {
                    this.say('Sorry, something went wrong. Please try again.');
                } finally {
                    this.loading = false;
                }
            },

            // ── Office messages ──────────────────────────────────────────

            openMessages() {
                if (!this.context) {
                    this.show();
                    this.askForTracking();
                    return;
                }
                this.open = true;
                this.tab = 'office';
                this.officeError = '';
                if (this.context.verified) {
                    this.loadThread();
                } else {
                    this.$nextTick(() => this.$refs.detail?.focus());
                }
            },

            async openThread(tracking) {
                await this.ask(tracking);
                if (this.context?.tracking === tracking) this.openMessages();
            },

            async verify() {
                if (this.officeBusy || !this.detail.trim()) return;
                this.officeBusy = true;
                this.officeError = '';
                try {
                    const res = await this.post(url(config.endpoints.verify, this.context.tracking), { detail: this.detail });
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 429) {
                        this.officeError = 'Too many tries. Please wait a minute and try again.';
                    } else if (!res.ok) {
                        this.officeError = data.errors?.detail?.[0] || 'That didn’t work. Please try again.';
                    } else {
                        this.detail = '';
                        this.context.verified = true;
                        this.showThread(data.messages);
                        this.listen(this.context.tracking);
                        this.refreshInbox();
                    }
                } catch (e) {
                    this.officeError = 'Something went wrong. Please try again.';
                } finally {
                    this.officeBusy = false;
                }
            },

            async loadThread() {
                if (!this.context?.verified) return;
                try {
                    const res = await fetch(url(config.endpoints.thread, this.context.tracking), { headers: { 'Accept': 'application/json' } });
                    if (res.status === 403) {
                        // The confirmation expired with the session.
                        this.context.verified = false;
                        return;
                    }
                    if (res.ok) {
                        this.showThread((await res.json()).messages);
                        this.refreshInbox();
                    }
                } catch (e) { /* the next ping or poll tries again */ }
            },

            showThread(messages) {
                this.thread = messages || [];
                // Opening the tab reads them (the endpoint marks them read).
                if (this.context) this.context.unread = 0;
                this.$nextTick(() => {
                    const box = this.$refs.thread;
                    if (box) box.scrollTop = box.scrollHeight;
                });
            },

            async sendToOffice() {
                if (this.officeBusy || !this.officeDraft.trim()) return;
                this.officeBusy = true;
                this.officeError = '';

                const form = new FormData();
                form.append('body', this.officeDraft);
                const file = this.$refs.officeFile?.files[0];
                if (file) form.append('attachment', file);

                try {
                    const res = await this.post(url(config.endpoints.post, this.context.tracking), form, true);
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 429) {
                        this.officeError = 'You’ve sent a lot in a short time — please wait a moment.';
                    } else if (!res.ok) {
                        const errors = data.errors || {};
                        this.officeError = (errors.body || errors.attachment || [data.message || 'Your message was not sent. Please try again.'])[0];
                    } else {
                        this.officeDraft = '';
                        this.officeFileName = '';
                        if (this.$refs.officeFile) this.$refs.officeFile.value = '';
                        this.showThread(data.messages);
                    }
                } catch (e) {
                    this.officeError = 'Something went wrong. Your message was not sent.';
                } finally {
                    this.officeBusy = false;
                }
            },

            async refreshInbox() {
                try {
                    const res = await fetch(config.endpoints.inbox, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    this.inbox = await res.json();
                    this.inbox.threads.forEach((t) => this.listen(t.tracking));
                    const current = this.inbox.threads.find((t) => t.tracking === this.context?.tracking);
                    if (current && this.context) this.context.unread = current.unread;
                } catch (e) { /* offline: the badge keeps its last count */ }
            },

            // The public channel only says that the conversation changed —
            // never what was said — so the thread is fetched through the
            // confirmed-citizen endpoint.
            listen(tracking) {
                if (this.subscribed[tracking]) return;
                this.subscribed[tracking] = true;
                // Alpine can call this before the deferred bundle has created
                // Echo, so wait for it rather than bailing out for good.
                window.whenEcho(() => {
                    try {
                        window.Echo.channel('documents.' + tracking).listen('.thread', () => {
                            if (this.open && this.tab === 'office' && this.context?.tracking === tracking) {
                                this.loadThread();
                            } else {
                                this.refreshInbox();
                            }
                        });
                    } catch (e) { /* the poll covers it */ }
                });
            },

            // ── QR scanning ──────────────────────────────────────────────

            startScan() {
                if (!window.SpeedQr) return;
                this.scanError = '';
                this.scanning = true;
                let handled = false;

                this.$nextTick(() => {
                    window.SpeedQr.start('speedyQrReader', (decoded) => {
                        if (handled) return;
                        const tracking = window.SpeedQr.extractTracking(decoded);
                        if (!tracking) {
                            this.scanError = window.SpeedQr.FOREIGN_CODE_MESSAGE;
                            return;
                        }
                        handled = true;
                        this.stopScan();
                        this.ask(tracking);
                    }, (error) => {
                        this.scanError = window.SpeedQr.describe(error);
                    });
                });
            },

            stopScan() {
                if (!this.scanning) return;
                this.scanning = false;
                window.SpeedQr?.stop();
                this.$nextTick(() => this.$refs.input?.focus());
            },

            scrollLog() {
                this.$nextTick(() => {
                    const log = this.$refs.log;
                    if (log) log.scrollTop = log.scrollHeight;
                });
            },
        };
    };
</script>
@endonce
