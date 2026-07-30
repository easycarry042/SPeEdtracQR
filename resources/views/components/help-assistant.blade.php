@php
    // Suggestions are built from the real service catalogue, so the chips offer
    // questions this office can actually answer.
    $suggestions = app(\App\Support\Ai\HelpAssistant::class)->suggestions();
@endphp

{{-- Public help desk: a floating launcher bottom-right that opens a chat panel.
     Unlike the per-document assistant, this one answers procedural questions
     ("what do I need for X?", "how do I track my request?") and holds no
     document context, which is what makes it safe on a public page. --}}
<div class="fixed bottom-5 right-5 z-50 print:hidden"
     x-data="helpAssistant(@js(csrf_token()), @js($suggestions))"
     @keydown.escape.window="open = false">

    {{-- Chat panel --}}
    <div x-show="open" x-cloak x-transition.origin.bottom.right
         class="mb-3 flex w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-2xl"
         role="dialog" aria-label="Help desk">
        <div class="flex items-center gap-3 border-b border-emerald-100 bg-emerald-50/70 px-4 py-3">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600/10 text-emerald-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 9a3.8 3.8 0 1 1 5.3 3.5c-.9.4-1.5 1.2-1.5 2.1v.4M12 18h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
            </span>
            <div class="flex-1">
                <h2 class="text-sm font-bold text-gray-800">How can we help?</h2>
                <p class="text-xs text-gray-400">Requirements, how to file, how to claim.</p>
            </div>
            <button type="button" @click="open = false" aria-label="Close help"
                    class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex flex-col gap-4 p-4">
            {{-- Conversation --}}
            <div class="max-h-72 space-y-3 overflow-y-auto" x-ref="log" x-show="messages.length > 0" x-cloak>
                <template x-for="m in messages" :key="m.id">
                    <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                        <span class="inline-block max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-sm"
                              :class="m.role === 'user' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-800'"
                              x-text="m.text"></span>
                    </div>
                </template>
                <div x-show="loading" class="flex items-center gap-1.5 text-sm text-gray-400" role="status" aria-label="Assistant is typing">
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-emerald-400" style="animation-delay:0ms"></span>
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-emerald-400" style="animation-delay:200ms"></span>
                    <span class="cr-typing-dot h-1.5 w-1.5 rounded-full bg-emerald-400" style="animation-delay:400ms"></span>
                </div>
            </div>

            {{-- Suggested questions (before any conversation) --}}
            <div class="flex flex-wrap gap-2" x-show="messages.length === 0">
                <template x-for="s in suggestions" :key="s">
                    <button type="button" @click="ask(s)"
                            class="rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50"
                            x-text="s"></button>
                </template>
            </div>

            {{-- Input --}}
            <form @submit.prevent="ask(draft)" class="flex gap-2">
                <input type="text" x-model="draft" :disabled="loading" maxlength="500" x-ref="input"
                       placeholder="Ask about requirements, filing, claiming…"
                       class="flex-1 rounded-xl border border-gray-300 text-sm shadow-sm focus:border-emerald-400 focus:ring-emerald-400" />
                <button type="submit" :disabled="loading || !draft.trim()"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Send
                </button>
            </form>

            <p class="text-[11px] leading-snug text-gray-400">
                For the status of your own request, enter your tracking number in the search above.
            </p>
        </div>
    </div>

    {{-- Launcher bubble --}}
    <button type="button" @click="toggle()"
            class="ml-auto flex items-center gap-2 rounded-full bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:bg-emerald-700"
            :aria-expanded="open" aria-label="Open the help desk">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.2 9a3.8 3.8 0 1 1 5.3 3.5c-.9.4-1.5 1.2-1.5 2.1v.4M12 18h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
        </svg>
        <span x-text="open ? 'Close' : 'Need help?'"></span>
    </button>
</div>

<script>
    if (!window.helpAssistant) {
        window.helpAssistant = function (csrf, suggestions) {
            return {
                csrf,
                draft: '',
                open: false,
                loading: false,
                messages: [],
                nextId: 1,
                suggestions: suggestions || ['How do I submit a request?', 'How do I track my request?'],

                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.input?.focus());
                    }
                },

                async ask(text) {
                    text = (text || '').trim();
                    if (!text || this.loading) return;

                    this.messages.push({ id: this.nextId++, role: 'user', text });
                    this.draft = '';
                    this.loading = true;
                    this.scrollLog();

                    try {
                        const res = await fetch('{{ route('help.ask') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({ question: text }),
                        });

                        let reply;
                        if (res.status === 429) {
                            reply = 'You’ve asked a lot in a short time — please wait a moment and try again.';
                        } else if (res.status === 422) {
                            reply = 'Please type a slightly longer question.';
                        } else if (!res.ok) {
                            reply = 'Sorry, I couldn’t answer that right now. Please try again shortly.';
                        } else {
                            reply = (await res.json()).answer;
                        }

                        this.messages.push({ id: this.nextId++, role: 'assistant', text: reply });
                    } catch (e) {
                        this.messages.push({
                            id: this.nextId++,
                            role: 'assistant',
                            text: 'Sorry, I couldn’t reach the help desk. Please check your connection and try again.',
                        });
                    } finally {
                        this.loading = false;
                        this.scrollLog();
                    }
                },

                scrollLog() {
                    this.$nextTick(() => {
                        const log = this.$refs.log;
                        if (log) log.scrollTop = log.scrollHeight;
                    });
                },
            };
        };
    }
</script>
