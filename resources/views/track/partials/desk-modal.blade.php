{{-- Look Up detail modal (Figma: STAFF LOOK UP).

     Four frames, one component. 1294:1680 is the detail card on its own;
     1321:1370 and 1321:1750 slide a second card in beside it holding the
     claiming-date calendar and the citizen conversation. `side` is which of
     those two is open — the card only exists while one of them is. --}}
<div x-show="selected" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[27px]"
     @click.self="close()"
     role="dialog" aria-modal="true" aria-label="Request details">

    {{-- The frame lays the two cards out at 704px each on a 1512px canvas. Below
         that they have to share what there is, so they flex down together rather
         than pushing the second one off the side of the screen. --}}
    <div class="mx-auto flex w-full max-w-[1335px] flex-col items-start justify-center gap-[15px] lg:flex-row lg:items-stretch"
         @click.self="close()">

        {{-- ── Detail card ──────────────────────────────────────────────── --}}
        <div class="dm-card dm-fixed w-full max-w-[660px] lg:min-w-0 lg:flex-1" x-show="selected" x-transition.opacity>

            {{-- Header bar: where the request stands, and close — as on the
                 dashboard's Review modal. --}}
            <div class="dm-head">
                <span class="pill"
                      :class="['approved', 'completed'].includes(selected?.status) ? 'p-green' : (['returned', 'denied'].includes(selected?.status) ? 'p-red' : 'p-amber')"
                      x-text="selected?.status_label"></span>

                <button type="button" @click="close()" aria-label="Close" class="dm-close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="dm-body">

                {{-- Title + tracking number --}}
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="dm-title" x-text="selected?.document_type"></p>
                        <p class="dm-subtitle" x-text="selected?.citizen_name || '—'"></p>
                    </div>
                    @include('partials.qr-tile', ['record' => 'selected'])
                </div>

                {{-- Progress: the four stages the prototype draws. Each segment of
                     the line fills once the stage before it is done. --}}
                <ol class="dm-steps" aria-label="Progress">
                    <template x-for="(label, i) in rail" :key="label">
                        <li class="dm-step" :class="'is-' + stageState(i + 1)"
                            :aria-current="stageState(i + 1) === 'current' ? 'step' : null">
                            <span class="dm-step-line" x-show="i > 0" aria-hidden="true"></span>
                            <span class="dm-step-dot" aria-hidden="true">
                                <svg x-show="stageState(i + 1) === 'done'" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 12.5 4 4 8-9"/></svg>
                            </span>
                            <span class="dm-step-label" x-text="label"></span>
                        </li>
                    </template>
                </ol>
                <p x-show="sideState()" x-cloak class="dm-side-state" x-text="sideState()"></p>

                {{-- Claiming date + the three actions --}}
                <div class="mt-6 flex flex-wrap items-stretch gap-2">
                    <div class="dm-date">
                        <p class="dm-date-label">Requested Claiming Date</p>
                        <p class="dm-date-value" x-text="selected?.claim_date || selected?.requested_claim_date || 'Not set yet'"></p>
                    </div>

                    <button type="button" @click="toggleSide('date')"
                            class="dm-tile" :class="side === 'date' ? 'on' : ''" :aria-expanded="side === 'date'">
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5V12l3 2"/></svg>
                        <span>Adjust Date</span>
                    </button>

                    <button type="button" @click="panel = panel === 'attachments' ? null : 'attachments'"
                            class="dm-tile" :class="panel === 'attachments' ? 'on' : ''" :aria-expanded="panel === 'attachments'">
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8L14 3.5Z"/><path stroke-linejoin="round" d="M14 3.5V8h4.5"/></svg>
                        <span>View Attachments</span>
                    </button>

                    <button type="button" @click="toggleSide('messages')"
                            class="dm-tile relative" :class="side === 'messages' ? 'on' : ''" :aria-expanded="side === 'messages'">
                        <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 18.5V7a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 7v6.5a2.5 2.5 0 0 1-2.5 2.5H9l-4 2.5Z"/><path stroke-linecap="round" d="M9 10.5h.01M12 10.5h.01M15 10.5h.01"/></svg>
                        <span>Messages</span>
                        <span x-show="selected?.unread_messages > 0" x-cloak class="dm-tile-badge" x-text="selected?.unread_messages"></span>
                    </button>
                </div>

                {{-- Attachments panel --}}
                <div x-show="panel === 'attachments'" x-cloak class="dm-panel">
                    <template x-if="(selected?.requirements || []).length === 0 && (selected?.attachments || []).length === 0">
                        <p class="text-[length:var(--fs-small)] text-ink-soft">Nothing has been uploaded for this request.</p>
                    </template>

                    <ul class="divide-y divide-hairline">
                        <template x-for="req in (selected?.requirements || [])" :key="req.label">
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="min-w-0 truncate text-[length:var(--fs-small)] font-medium text-ink" x-text="req.label"></span>
                                <template x-if="req.has_file">
                                    <a :href="req.url" target="_blank" rel="noopener" class="shrink-0 text-[length:var(--fs-small)] font-bold text-green hover:underline">Open</a>
                                </template>
                                <template x-if="! req.has_file">
                                    <span class="shrink-0 text-[length:var(--fs-caption)] text-ink-soft">Not uploaded</span>
                                </template>
                            </li>
                        </template>

                        <template x-for="(file, i) in (selected?.attachments || [])" :key="'a' + i">
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="text-[length:var(--fs-small)] text-ink" x-text="'Attachment .' + file.ext"></span>
                                <a :href="file.url" target="_blank" rel="noopener" class="shrink-0 text-[length:var(--fs-small)] font-bold text-green hover:underline">Open</a>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Logs --}}
                <section class="mt-7">
                    <h2 class="dm-section">Logs</h2>

                    <template x-if="(selected?.logs || []).length === 0">
                        <p class="mt-2 text-[length:var(--fs-small)] text-ink-soft">Nothing recorded yet.</p>
                    </template>

                    <ul class="dm-logs">
                        <template x-for="(log, i) in (selected?.logs || [])" :key="i">
                            <li class="dm-log" x-show="i < 2 || allLogs">
                                <span class="dm-log-dot" aria-hidden="true"></span>
                                <span class="dm-log-event" x-text="log.event"></span>
                                <span class="lookup-leader" aria-hidden="true"></span>
                                <span class="dm-log-time" x-text="log.time"></span>
                            </li>
                        </template>
                    </ul>

                    <button type="button" x-show="(selected?.logs || []).length > 2" @click="allLogs = ! allLogs" class="dm-more">
                        <span x-text="allLogs ? 'View less' : 'View more'">View more</span>
                        <svg class="h-4 w-4 transition" :class="allLogs ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                </section>

                {{-- More Info --}}
                <section class="mt-7">
                    <h2 class="dm-section">More Info</h2>

                    <dl class="dm-info">
                        <template x-for="row in [
                            ['Request by', selected?.citizen_name],
                            ['Email', selected?.citizen_email],
                            ['Contact No.', selected?.citizen_contact],
                            ['Request Type', selected?.document_type],
                            ['Request Date', selected?.request_date],
                        ]" :key="row[0]">
                            <div class="dm-info-row">
                                <dt x-text="row[0]"></dt>
                                <dd x-text="row[1] || '—'"></dd>
                            </div>
                        </template>
                    </dl>
                </section>
            </div>

            {{-- Actions, pinned under the scroll — the same footer as the
                 dashboard's Review modal. --}}
            <div class="dm-foot dm-actions">
                <p x-show="error" x-cloak x-text="error" role="alert"
                   class="w-full rounded-[10px] bg-status-red-wash px-3 py-2 text-[length:var(--fs-small)] font-semibold text-status-red"></p>

                <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                    <button type="button" @click="markCompleted()" :disabled="busy"
                            class="cr-btn cr-btn-primary disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="! busy">Mark as Completed</span>
                        <span x-show="busy">Completing…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Side card: calendar (1321:1370) or conversation (1321:1750) ─── --}}
        <div x-show="side" x-cloak x-transition.opacity
             class="dm-fixed flex w-full max-w-[660px] flex-col overflow-hidden rounded-[16px] bg-white shadow-[0_19px_48px_-16px_rgba(0,64,4,0.45)] lg:min-w-0 lg:flex-1"
             :aria-label="side === 'date' ? 'Adjust claiming date' : 'Messages'" role="region">

            {{-- Shared header --}}
            <div class="dm-head">
                <h2 class="dm-section min-w-0 truncate">
                    <span x-show="side === 'date'">Adjust Claiming Date</span>
                    <span x-show="side === 'messages'">
                        Messages · <span class="font-semibold text-ink" x-text="selected?.citizen_name || 'Citizen'"></span>
                    </span>
                </h2>

                <button type="button" @click="side = null" aria-label="Close panel" class="dm-close shrink-0">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            {{-- ── Calendar ── --}}
            <div x-show="side === 'date'" class="flex min-h-0 flex-1 flex-col">
                {{-- Where things stand before anything is picked. --}}
                <div class="dp-context">
                    <div class="dp-context-item">
                        <span class="dp-key dp-key-requested" aria-hidden="true"></span>
                        <span>Citizen asked for</span>
                        <strong x-text="selected?.requested_claim_date || 'no date'"></strong>
                    </div>
                    <div class="dp-context-item" x-show="selected?.claim_date">
                        <span class="dp-key dp-key-set" aria-hidden="true"></span>
                        <span>Currently set</span>
                        <strong x-text="selected?.claim_date"></strong>
                    </div>
                </div>

                {{-- Month, with Previous / Today / Next as one control. --}}
                <div class="dp-head">
                    <p class="dp-month" x-text="monthLabel()" aria-live="polite"></p>
                    <div class="bk-nav" role="group" aria-label="Change month">
                        <button type="button" @click="shiftMonth(-1)" aria-label="Previous month">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button type="button" @click="calendarToToday()">Today</button>
                        <button type="button" @click="shiftMonth(1)" aria-label="Next month">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="dp-dows" aria-hidden="true">
                    <template x-for="(name, i) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="name">
                        <span :class="i === 0 && 'is-sunday'" x-text="name"></span>
                    </template>
                </div>

                <div class="dp-grid" role="grid" aria-label="Claiming date">
                    <template x-for="(week, w) in calendarWeeks()" :key="'w' + w">
                        <div class="dp-week" role="row">
                            <template x-for="cell in week" :key="cell.iso">
                                <div class="dp-cell" role="gridcell">
                                    <button type="button" @click="dateValue = cell.iso"
                                            :disabled="cell.past"
                                            class="dp-day"
                                            :class="{
                                                'is-picked': dateValue === cell.iso,
                                                'is-today': cell.today,
                                                'is-outside': ! cell.inMonth,
                                                'is-sunday': cell.sunday,
                                                'is-requested': cell.iso === selected?.requested_claim_on,
                                                'is-set': cell.iso === selected?.claim_on,
                                            }"
                                            :aria-pressed="dateValue === cell.iso"
                                            :aria-current="cell.today ? 'date' : null"
                                            :aria-label="cell.label
                                                + (cell.iso === selected?.requested_claim_on ? ', requested by the citizen' : '')
                                                + (cell.iso === selected?.claim_on ? ', currently set' : '')
                                                + (cell.past ? ', unavailable' : '')">
                                        <span x-text="cell.day"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="dp-foot">
                    <p class="dp-picked">
                        <template x-if="dateValue">
                            <span>New claiming date <strong x-text="pickedLabel()"></strong></span>
                        </template>
                        <template x-if="! dateValue">
                            <span>Pick a day for the citizen to claim.</span>
                        </template>
                    </p>

                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" x-show="dateValue" @click="dateValue = ''" class="cr-btn">Clear</button>
                        <button type="button" @click="saveDate()" :disabled="busy || ! dateValue" class="cr-btn cr-btn-primary disabled:opacity-40">
                            Apply
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Conversation ── --}}
            <div x-show="side === 'messages'" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-[256px] flex-1 space-y-[8px] overflow-y-auto px-[26.5px] py-[19px]" x-ref="thread">

                    {{-- Empty state, as the frame draws it. --}}
                    <div x-show="(selected?.messages || []).length === 0" class="flex h-full flex-col items-center justify-center text-center">
                        <img src="{{ asset('images/staff/chat-empty.svg') }}" alt=""
                             style="width:80px; height:78.5px; max-width:none;">
                        <p class="mt-[14.5px] text-[20px] font-extrabold text-[#017209]">You are now chatting with the client</p>
                        <p class="text-[18px] font-medium text-black">Your conversation is secured</p>
                    </div>

                    <template x-for="(msg, i) in (selected?.messages || [])" :key="i">
                        <div>
                            {{-- Timestamp above the first message and whenever the
                                 clock moves on, the way a chat groups a burst. --}}
                            <p x-show="i === 0 || msg.time !== selected.messages[i - 1].time"
                               class="py-[8px] text-center text-[14px] font-medium text-black"
                               x-text="msg.time"></p>

                            <div class="flex" :class="msg.from === 'citizen' ? 'justify-start' : 'justify-end'">
                                <div class="max-w-[80%]">
                                    <p class="desk-bubble" :class="msg.from === 'citizen' ? 'from-citizen' : 'from-staff'"
                                       x-text="msg.body"></p>

                                    <template x-if="msg.attachment">
                                        <a :href="msg.attachment.url" target="_blank" rel="noopener"
                                           class="mt-[6px] flex items-center gap-[8px] rounded-[8px] border border-hairline bg-white px-[11px] py-[8px] text-[14px] font-medium text-ink transition hover:bg-green-wash"
                                           :class="msg.from === 'citizen' ? '' : 'justify-end'">
                                            <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                                 style="width:16px; height:16px; max-width:none;">
                                            <span class="min-w-0 truncate" x-text="msg.attachment.name"></span>
                                        </a>
                                    </template>

                                    {{-- Read receipt: the frame's double tick, dimmed
                                         until the citizen has actually opened it. --}}
                                    <p x-show="msg.from !== 'citizen'"
                                       class="mt-[4px] flex items-center justify-end gap-[4px] text-[14px] font-medium text-black">
                                        <span x-text="msg.read ? 'Read' : 'Sent'"></span>
                                        <span class="flex items-center" :class="msg.read ? '' : 'opacity-40'" aria-hidden="true">
                                            <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                 style="width:14.5px; height:14.5px; max-width:none;">
                                            <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                 class="-ml-[7px]" style="width:14.5px; height:14.5px; max-width:none;">
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="h-[2px] w-full bg-[#d9d9d9]"></div>

                <template x-if="selected?.can_message">
                    <form @submit.prevent="sendMessage()" class="px-[23px] py-[14.5px]">
                        {{-- The file rides along with the message it belongs to,
                             so it is staged here and posted on send. --}}
                        <div x-show="file" x-cloak
                             class="mb-[8px] flex items-center gap-[8px] rounded-[8px] bg-green-wash px-[11px] py-[8px]">
                            <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                 style="width:16px; height:16px; max-width:none;">
                            <span class="min-w-0 flex-1 truncate text-[14px] font-medium text-ink" x-text="file?.name"></span>
                            <button type="button" @click="clearFile()"
                                    class="shrink-0 text-[14px] font-bold text-status-red hover:underline">Remove</button>
                        </div>

                        <div class="flex items-center gap-[16px]">
                            <input type="file" x-ref="attachment" class="sr-only"
                                   accept="{{ \App\Support\UploadRules::accept() }}"
                                   @change="pickFile($event)">

                            <button type="button" @click="$refs.attachment.click()"
                                    title="{{ \App\Support\UploadRules::hint() }}" aria-label="Attach a file"
                                    class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                                <img src="{{ asset('images/staff/icon-plus.svg') }}" alt=""
                                     style="width:24px; height:24px; max-width:none;">
                            </button>

                            <label for="deskMessage" class="sr-only">Write your message</label>
                            <input id="deskMessage" type="text" x-model="draft" maxlength="5000"
                                   placeholder="Write your message..." class="desk-composer">

                            <button type="submit" :disabled="sending || ! draft.trim()"
                                    class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash disabled:opacity-40"
                                    aria-label="Send message">
                                <img src="{{ asset('images/staff/icon-send.svg') }}" alt=""
                                     style="width:24px; height:24px; max-width:none;">
                            </button>
                        </div>
                    </form>
                </template>

                <template x-if="! selected?.can_message">
                    <p class="px-[26.5px] py-[17.5px] text-[14px] text-ink-soft">
                        Only the assigned staff member or an admin can reply on this request.
                    </p>
                </template>
            </div>
        </div>
    </div>
</div>
