@props(['document'])

<div class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm"
     x-data="docAssistant(@js($document->tracking_number), @js(csrf_token()))">
    <div class="flex items-center gap-3 border-b border-indigo-100 bg-indigo-50/60 px-6 py-4">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600/10 text-indigo-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>
            </svg>
        </span>
        <div>
            <h2 class="text-base font-bold text-gray-800">Ask about this document</h2>
            <p class="text-xs text-gray-400">Answers come only from this document’s tracking info.</p>
        </div>
    </div>

    <div class="p-5">
        {{-- Conversation --}}
        <div class="max-h-72 space-y-3 overflow-y-auto" x-ref="log" x-show="messages.length > 0" x-cloak>
            <template x-for="m in messages" :key="m.id">
                <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
                    <span class="inline-block max-w-[85%] whitespace-pre-line rounded-2xl px-4 py-2 text-sm"
                          :class="m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800'"
                          x-text="m.text"></span>
                </div>
            </template>
            <div x-show="loading" class="flex items-center gap-1.5 text-sm text-gray-400">
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-400" style="animation-delay:0ms"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-400" style="animation-delay:150ms"></span>
                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-indigo-400" style="animation-delay:300ms"></span>
            </div>
        </div>

        {{-- Suggested questions (before any conversation) --}}
        <div class="flex flex-wrap gap-2" x-show="messages.length === 0">
            <template x-for="s in suggestions" :key="s">
                <button type="button" @click="ask(s)"
                        class="rounded-full border border-indigo-200 bg-white px-3 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-50"
                        x-text="s"></button>
            </template>
        </div>

        {{-- Input --}}
        <form @submit.prevent="ask(draft)" class="mt-4 flex gap-2">
            <input type="text" x-model="draft" :disabled="loading" maxlength="500"
                   placeholder="Type your question…"
                   class="flex-1 rounded-xl border border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400" />
            <button type="submit" :disabled="loading || !draft.trim()"
                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                Send
            </button>
        </form>
    </div>
</div>

<script>
    if (!window.docAssistant) {
        window.docAssistant = function (trackingNumber, csrf) {
            return {
                trackingNumber,
                csrf,
                draft: '',
                loading: false,
                messages: [],
                nextId: 1,
                suggestions: ['Where is my document now?', 'When will it be ready?', 'What is the current status?'],

                async ask(text) {
                    text = (text || '').trim();
                    if (!text || this.loading) return;

                    this.messages.push({ id: this.nextId++, role: 'user', text });
                    this.draft = '';
                    this.loading = true;
                    this.scrollLog();

                    try {
                        const res = await fetch(`/track/${encodeURIComponent(this.trackingNumber)}/ask`, {
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
                        this.messages.push({ id: this.nextId++, role: 'assistant', text: 'Sorry, something went wrong. Please try again.' });
                    } finally {
                        this.loading = false;
                        this.scrollLog();
                    }
                },

                scrollLog() {
                    this.$nextTick(() => {
                        const el = this.$refs.log;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },
            };
        };
    }
</script>
