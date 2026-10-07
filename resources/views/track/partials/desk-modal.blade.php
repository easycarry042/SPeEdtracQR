{{-- Look Up detail modal (Figma: STAFF LOOK UP).

     Four frames, one component. 1294:1680 is the detail card on its own;
     1321:1370 and 1321:1750 slide a second card in beside it holding the
     claiming-date calendar and the citizen conversation. `side` is which of
     those two is open — the card only exists while one of them is. --}}
<div x-show="selected" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto bg-black/40 px-4 py-[34px]"
     @click.self="close()"
     role="dialog" aria-modal="true" aria-label="Request details">

    {{-- The frame lays the two cards out at 704px each on a 1512px canvas. Below
         that they have to share what there is, so they flex down together rather
         than pushing the second one off the side of the screen. --}}
    <div class="mx-auto flex w-full max-w-[1427px] flex-col items-start justify-center gap-[19px] lg:flex-row lg:items-stretch"
         @click.self="close()">

        {{-- ── Detail card ──────────────────────────────────────────────── --}}
        <div class="w-full max-w-[704px] rounded-[20px] bg-white p-[38px] shadow-[0_24px_60px_-20px_rgba(0,64,4,0.45)] lg:min-w-0 lg:flex-1"
             x-show="selected" x-transition.opacity>

            {{-- Header: the completion action on the left, close on the right. --}}
            <div class="flex items-start justify-between gap-4">
                <button type="button" @click="markCompleted()" :disabled="busy"
                        class="inline-flex items-center gap-[10px] text-[20px] font-medium text-[#01721a] transition hover:text-green-deep disabled:opacity-50">
                    <img src="{{ asset('images/staff/icon-checkbox.svg') }}" alt=""
                         style="width:26px; height:26px; max-width:none;">
                    Mark as Completed
                </button>

                <button type="button" @click="close()" aria-label="Close"
                        class="rounded-lg p-1 text-ink-soft transition hover:bg-hairline/40 hover:text-ink">
                    <img src="{{ asset('images/staff/icon-cross.svg') }}" alt=""
                         style="width:24px; height:24px; max-width:none;">
                </button>
            </div>

            <p x-show="error" x-cloak class="mt-3 rounded-[10px] bg-status-red-wash px-4 py-2 text-[15px] font-semibold text-status-red" x-text="error"></p>

            {{-- Title + tracking number --}}
            <div class="mt-[26px] flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[22px] font-black leading-tight text-[#004004]" x-text="selected?.document_type"></p>
                    <p class="text-[22px] font-medium text-black" x-text="selected?.citizen_name || '—'"></p>
                </div>
                <div class="flex items-center gap-[12px]">
                    <span class="flex h-[55px] w-[55px] shrink-0 items-center justify-center rounded-[10px] border border-[#004004] bg-[rgba(141,255,60,0.2)]" aria-hidden="true">
                        <img src="{{ asset('images/staff/icon-qr.svg') }}" alt=""
                             style="width:45px; height:45px; max-width:none;">
                    </span>
                    <div>
                        <p class="text-[22px] font-black leading-tight text-[#004004]">Tracking Number</p>
                        <span class="mono text-[18px] font-bold text-ink" x-text="selected?.tracking_number"></span>
                    </div>
                </div>
            </div>

            {{-- Stage rail --}}
            <div class="mt-[40px] px-[15px]">
                <div class="relative h-[30px]">
                    <div class="absolute left-0 right-0 top-[10px] h-[10px] rounded-[50px] bg-[#d9d9d9]"></div>
                    <div class="absolute left-0 top-[10px] h-[10px] rounded-[50px] bg-[#01721a]"
                         :style="'width: ' + railFill() + '%'"></div>

                    <template x-for="(stage, i) in flow" :key="stage.value">
                        <span class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2"
                              :style="'left: ' + (flow.length > 1 ? (i / (flow.length - 1)) * 100 : 0) + '%'">
                            <img :src="@js(asset('images/staff')) + '/' + nodeArt(i + 1) + '.svg'" alt=""
                                 :style="'width:' + (nodeArt(i + 1) === 'node-current' ? 30 : 20) + 'px; height:' + (nodeArt(i + 1) === 'node-current' ? 30 : 20) + 'px; max-width:none;'">
                        </span>
                    </template>
                </div>

                {{-- End labels anchor to their own edge; centred, the last runs off. --}}
                <div class="relative mt-[8px] h-[24px]">
                    <template x-for="(stage, i) in flow" :key="'label-' + stage.value">
                        <span class="absolute whitespace-nowrap text-[17px] font-medium text-black"
                              :class="i === 0 ? '' : (i === flow.length - 1 ? '' : '-translate-x-1/2')"
                              :style="i === 0 ? 'left:0' : (i === flow.length - 1 ? 'right:0' : 'left: ' + ((i / (flow.length - 1)) * 100) + '%')"
                              x-text="stage.label"></span>
                    </template>
                </div>
            </div>

            {{-- Claiming date + the three tiles --}}
            <div class="mt-[46px] flex flex-wrap items-stretch gap-[10px]">
                <div class="min-h-[94px] flex-1 rounded-[10px] border border-[#e0c47a] bg-[#fdf3dc] px-[24px] py-[15px]">
                    <p class="text-[20px] font-medium text-[#7a5a12]">Requested Claiming Date</p>
                    <p class="text-[20px] font-bold text-[#7a5a12]"
                       x-text="selected?.claim_date || selected?.requested_claim_date || 'Not set yet'"></p>
                </div>

                <button type="button" @click="toggleSide('date')"
                        class="desk-tile" :class="side === 'date' ? 'on' : ''"
                        :aria-expanded="side === 'date'">
                    <img src="{{ asset('images/staff/icon-clock.svg') }}" alt=""
                         style="width:30px; height:30px; max-width:none;">
                    <span>Adjust Date</span>
                </button>

                {{-- The label wraps in the frame's 102px tile, so the accessible
                     name is set explicitly rather than left to the line break. --}}
                <button type="button" @click="panel = panel === 'attachments' ? null : 'attachments'"
                        aria-label="View Attachments"
                        class="desk-tile" :class="panel === 'attachments' ? 'on' : ''">
                    <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                         style="width:26px; height:26px; max-width:none;">
                    <span>View<br>Attachments</span>
                </button>

                <button type="button" @click="toggleSide('messages')"
                        class="desk-tile relative" :class="side === 'messages' ? 'on' : ''"
                        :aria-expanded="side === 'messages'">
                    <img src="{{ asset('images/staff/icon-message.svg') }}" alt=""
                         style="width:34px; height:34px; max-width:none;">
                    <span>Messages</span>
                    <span x-show="selected?.unread_messages > 0" x-cloak
                          class="absolute -right-[6px] -top-[6px] flex h-[25px] min-w-[25px] items-center justify-center rounded-full bg-[#e5342a] px-1 text-[14px] font-bold text-white"
                          x-text="selected?.unread_messages"></span>
                </button>
            </div>

            {{-- Attachments panel --}}
            <div x-show="panel === 'attachments'" x-cloak class="mt-[14px] rounded-[10px] border border-hairline bg-green-wash/40 p-[18px]">
                <template x-if="(selected?.requirements || []).length === 0 && (selected?.attachments || []).length === 0">
                    <p class="text-[15px] text-ink-soft">Nothing has been uploaded for this request.</p>
                </template>

                <ul class="space-y-2">
                    <template x-for="req in (selected?.requirements || [])" :key="req.label">
                        <li class="flex items-center justify-between gap-3 rounded-[8px] bg-white px-3 py-2">
                            <span class="min-w-0 truncate text-[15px] font-medium text-ink" x-text="req.label"></span>
                            <template x-if="req.has_file">
                                <a :href="req.url" target="_blank" rel="noopener"
                                   class="shrink-0 text-[14px] font-bold text-green underline">Open</a>
                            </template>
                            <template x-if="! req.has_file">
                                <span class="shrink-0 text-[13px] text-ink-soft">Not uploaded</span>
                            </template>
                        </li>
                    </template>

                    <template x-for="(file, i) in (selected?.attachments || [])" :key="'a' + i">
                        <li class="flex items-center justify-between gap-3 rounded-[8px] bg-white px-3 py-2">
                            <span class="text-[15px] text-ink" x-text="'Attachment .' + file.ext"></span>
                            <a :href="file.url" target="_blank" rel="noopener"
                               class="shrink-0 text-[14px] font-bold text-green underline">Open</a>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Logs --}}
            <div class="mt-[40px]">
                <h2 class="!text-[22px] !font-black !text-[#004004]">Logs</h2>

                <template x-if="(selected?.logs || []).length === 0">
                    <p class="mt-[16px] text-[17px] text-ink-soft">Nothing recorded yet.</p>
                </template>

                <template x-for="(log, i) in (selected?.logs || [])" :key="i">
                    <div class="mt-[16px] flex items-center gap-[14px]" x-show="i < 2 || allLogs">
                        <img src="{{ asset('images/staff/log-bullet.svg') }}" alt=""
                             style="width:15px; height:15px; max-width:none;" class="shrink-0">
                        <span class="shrink-0 text-[17px] font-medium text-[#353535]" x-text="log.event"></span>
                        <span class="lookup-leader" aria-hidden="true"></span>
                        <span class="shrink-0 text-[17px] font-bold text-[#686868]" x-text="log.time"></span>
                    </div>
                </template>

                <button type="button" x-show="(selected?.logs || []).length > 2" @click="allLogs = ! allLogs"
                        class="mt-[18px] flex w-full items-center gap-[18px] text-[17px] font-medium text-black">
                    <span class="h-[2px] flex-1 bg-[#d9d9d9]"></span>
                    <span class="flex items-center gap-1 whitespace-nowrap">
                        <span x-text="allLogs ? 'View Less' : 'View More'">View More</span>
                        <svg class="h-4 w-4 transition" :class="allLogs ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                        </svg>
                    </span>
                    <span class="h-[2px] flex-1 bg-[#d9d9d9]"></span>
                </button>
            </div>

            {{-- More Info --}}
            <div class="mt-[40px]">
                <h2 class="!text-[22px] !font-black !text-[#004004]">More Info</h2>

                <dl class="mt-[14px]">
                    <template x-for="row in [
                        ['Request by', selected?.citizen_name],
                        ['Email', selected?.citizen_email],
                        ['Contact No.', selected?.citizen_contact],
                        ['Request Type', selected?.document_type],
                        ['Request Date', selected?.request_date],
                    ]" :key="row[0]">
                        <div class="flex items-baseline justify-between gap-6 border-b-2 border-[#d9d9d9] py-[12px] last:border-b-0">
                            <dt class="shrink-0 text-[17px] font-medium text-[#353535]" x-text="row[0]"></dt>
                            <dd class="break-all text-right text-[17px] font-bold text-black" x-text="row[1] || '—'"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            {{-- The desk is for finding and triaging; the deeper review tools live
                 on the request's own page. --}}
            <a :href="@js(url('/track')) + '/' + selected?.tracking_number"
               class="mt-[26px] inline-flex items-center gap-2 text-[16px] font-bold text-green underline">
                Open the full record
            </a>
        </div>

        {{-- ── Side card: calendar (1321:1370) or conversation (1321:1750) ─── --}}
        <div x-show="side" x-cloak x-transition.opacity
             class="flex max-h-[calc(100vh-68px)] w-full max-w-[704px] flex-col rounded-[20px] bg-white shadow-[0_24px_60px_-20px_rgba(0,64,4,0.45)] lg:sticky lg:top-0 lg:min-w-0 lg:flex-1"
             :aria-label="side === 'date' ? 'Adjust claiming date' : 'Messages'" role="region">

            {{-- Shared header --}}
            <div class="flex items-start justify-between gap-4 px-[33px] pb-[18px] pt-[24px]">
                <h2 class="!text-[25px] !font-extrabold !text-black">
                    <span x-show="side === 'date'">Adjust Claiming Date</span>
                    <span x-show="side === 'messages'">
                        Messages · <span x-text="selected?.citizen_name || 'Citizen'"></span>
                    </span>
                </h2>

                <button type="button" @click="side = null" aria-label="Close panel"
                        class="shrink-0 rounded-lg p-1 text-ink-soft transition hover:bg-hairline/40 hover:text-ink">
                    <img src="{{ asset('images/staff/icon-cross.svg') }}" alt=""
                         style="width:24px; height:24px; max-width:none;">
                </button>
            </div>

            <div class="h-[2px] w-full bg-[#d9d9d9]"></div>

            {{-- ── Calendar ── --}}
            <div x-show="side === 'date'" class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center justify-center gap-[40px] py-[18px]">
                    <button type="button" @click="shiftMonth(-1)" class="desk-cal-nav" aria-label="Previous month">
                        <img src="{{ asset('images/staff/icon-angle-right.svg') }}" alt=""
                             class="rotate-180" style="width:22px; height:22px; max-width:none;">
                    </button>

                    {{-- The frame shows the month alone; the year is kept because a
                         claiming date can legitimately fall in the next one. --}}
                    <p class="min-w-[220px] text-center text-[30px] font-bold text-[#004004]" x-text="monthLabel()"></p>

                    <button type="button" @click="shiftMonth(1)" class="desk-cal-nav" aria-label="Next month">
                        <img src="{{ asset('images/staff/icon-angle-right.svg') }}" alt=""
                             style="width:22px; height:22px; max-width:none;">
                    </button>
                </div>

                <div class="grid grid-cols-7 px-[33px] pb-[10px]">
                    <template x-for="(name, i) in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="name">
                        <span class="text-center text-[20px] font-semibold"
                              :class="i === 0 ? 'text-[#a50003]' : 'text-[#004004]'"
                              x-text="name"></span>
                    </template>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto border-t border-black/20">
                    <template x-for="(week, w) in calendarWeeks()" :key="'w' + w">
                        <div class="grid grid-cols-7 border-b border-black/20 px-[33px]">
                            <template x-for="cell in week" :key="cell.iso">
                                <div class="flex items-center justify-center py-[10px]">
                                    <button type="button" @click="dateValue = cell.iso"
                                            :disabled="cell.past"
                                            class="desk-cal-day"
                                            :class="[
                                                dateValue === cell.iso ? 'on' : '',
                                                cell.inMonth ? '' : 'faded',
                                                cell.sunday ? 'sunday' : '',
                                            ]"
                                            :aria-current="dateValue === cell.iso ? 'date' : null"
                                            :aria-label="cell.label"
                                            x-text="cell.day"></button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-black/20 px-[33px] py-[18px]">
                    <p class="text-[15px] text-ink-soft">
                        <template x-if="selected?.requested_claim_date">
                            <span>The citizen asked for <strong x-text="selected?.requested_claim_date"></strong>.</span>
                        </template>
                        <template x-if="! selected?.requested_claim_date">
                            <span>The citizen did not ask for a particular date.</span>
                        </template>
                    </p>

                    <button type="button" @click="saveDate()" :disabled="busy || ! dateValue"
                            class="shrink-0 text-[25px] font-bold text-[#004004] transition hover:text-green disabled:opacity-40">
                        Apply
                    </button>
                </div>
            </div>

            {{-- ── Conversation ── --}}
            <div x-show="side === 'messages'" class="flex min-h-0 flex-1 flex-col">
                <div class="min-h-[320px] flex-1 space-y-[10px] overflow-y-auto px-[33px] py-[24px]" x-ref="thread">

                    {{-- Empty state, as the frame draws it. --}}
                    <div x-show="(selected?.messages || []).length === 0" class="flex h-full flex-col items-center justify-center text-center">
                        <img src="{{ asset('images/staff/chat-empty.svg') }}" alt=""
                             style="width:100px; height:98px; max-width:none;">
                        <p class="mt-[18px] text-[25px] font-extrabold text-[#017209]">You are now chatting with the client</p>
                        <p class="text-[22px] font-medium text-black">Your conversation is secured</p>
                    </div>

                    <template x-for="(msg, i) in (selected?.messages || [])" :key="i">
                        <div>
                            {{-- Timestamp above the first message and whenever the
                                 clock moves on, the way a chat groups a burst. --}}
                            <p x-show="i === 0 || msg.time !== selected.messages[i - 1].time"
                               class="py-[10px] text-center text-[15px] font-medium text-black"
                               x-text="msg.time"></p>

                            <div class="flex" :class="msg.from === 'citizen' ? 'justify-start' : 'justify-end'">
                                <div class="max-w-[80%]">
                                    <p class="desk-bubble" :class="msg.from === 'citizen' ? 'from-citizen' : 'from-staff'"
                                       x-text="msg.body"></p>

                                    <template x-if="msg.attachment">
                                        <a :href="msg.attachment.url" target="_blank" rel="noopener"
                                           class="mt-[6px] flex items-center gap-[8px] rounded-[10px] border border-hairline bg-white px-[14px] py-[8px] text-[15px] font-medium text-ink transition hover:bg-green-wash"
                                           :class="msg.from === 'citizen' ? '' : 'justify-end'">
                                            <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                                 style="width:20px; height:20px; max-width:none;">
                                            <span class="min-w-0 truncate" x-text="msg.attachment.name"></span>
                                        </a>
                                    </template>

                                    {{-- Read receipt: the frame's double tick, dimmed
                                         until the citizen has actually opened it. --}}
                                    <p x-show="msg.from !== 'citizen'"
                                       class="mt-[4px] flex items-center justify-end gap-[4px] text-[15px] font-medium text-black">
                                        <span x-text="msg.read ? 'Read' : 'Sent'"></span>
                                        <span class="flex items-center" :class="msg.read ? '' : 'opacity-40'" aria-hidden="true">
                                            <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                 style="width:18px; height:18px; max-width:none;">
                                            <img src="{{ asset('images/staff/icon-check.svg') }}" alt=""
                                                 class="-ml-[9px]" style="width:18px; height:18px; max-width:none;">
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="h-[2px] w-full bg-[#d9d9d9]"></div>

                <template x-if="selected?.can_message">
                    <form @submit.prevent="sendMessage()" class="px-[29px] py-[18px]">
                        {{-- The file rides along with the message it belongs to,
                             so it is staged here and posted on send. --}}
                        <div x-show="file" x-cloak
                             class="mb-[10px] flex items-center gap-[10px] rounded-[10px] bg-green-wash px-[14px] py-[8px]">
                            <img src="{{ asset('images/staff/icon-file.svg') }}" alt=""
                                 style="width:20px; height:20px; max-width:none;">
                            <span class="min-w-0 flex-1 truncate text-[15px] font-medium text-ink" x-text="file?.name"></span>
                            <button type="button" @click="clearFile()"
                                    class="shrink-0 text-[14px] font-bold text-status-red hover:underline">Remove</button>
                        </div>

                        <div class="flex items-center gap-[20px]">
                            <input type="file" x-ref="attachment" class="sr-only"
                                   accept="{{ \App\Support\UploadRules::accept() }}"
                                   @change="pickFile($event)">

                            <button type="button" @click="$refs.attachment.click()"
                                    title="{{ \App\Support\UploadRules::hint() }}" aria-label="Attach a file"
                                    class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash focus:outline-none focus-visible:ring-2 focus-visible:ring-green">
                                <img src="{{ asset('images/staff/icon-plus.svg') }}" alt=""
                                     style="width:30px; height:30px; max-width:none;">
                            </button>

                            <label for="deskMessage" class="sr-only">Write your message</label>
                            <input id="deskMessage" type="text" x-model="draft" maxlength="5000"
                                   placeholder="Write your message..." class="desk-composer">

                            <button type="submit" :disabled="sending || ! draft.trim()"
                                    class="shrink-0 rounded-lg p-1 transition hover:bg-green-wash disabled:opacity-40"
                                    aria-label="Send message">
                                <img src="{{ asset('images/staff/icon-send.svg') }}" alt=""
                                     style="width:30px; height:30px; max-width:none;">
                            </button>
                        </div>
                    </form>
                </template>

                <template x-if="! selected?.can_message">
                    <p class="px-[33px] py-[22px] text-[16px] text-ink-soft">
                        Only the assigned staff member or an admin can reply on this request.
                    </p>
                </template>
            </div>
        </div>
    </div>
</div>
