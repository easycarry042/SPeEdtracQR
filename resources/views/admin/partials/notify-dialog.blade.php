{{-- "Notify about this request" — the super admin flags a request for whoever
     owns it (the assignee, or the supervisors of its office) rather than
     reassigning it. One dialog for the command center and the Requests page:
     anything can open it with

         $dispatch('admin-notify', { tracking, type, citizen, purpose,
                                     stageLabel, assignee, office, over, nudgeUrl })

     Drawn like the Review modal's own dialogs (Deny, Decline): header,
     body, footer on one card, the note field full width under its label. --}}
<div x-data="{ open: false, sel: {} }"
     @admin-notify.window="sel = $event.detail; open = true; $nextTick(() => $refs.note?.focus())">
    <div x-show="open" x-cloak
         class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 px-4"
         @keydown.escape.window="open = false" @click.self="open = false"
         role="dialog" aria-modal="true" aria-labelledby="notifyTitle">
        <form method="POST" :action="sel.nudgeUrl" x-show="open" x-transition
              class="w-full max-w-md rounded-2xl border border-hairline bg-paper shadow-xl">
            @csrf
            <div class="flex items-start justify-between gap-3 border-b border-hairline px-6 py-4">
                <div class="min-w-0">
                    <h3 id="notifyTitle" class="text-base font-bold text-ink">Notify about this request</h3>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                        <span class="font-semibold text-green-deep" x-text="sel.type"></span>
                        <span class="code" x-text="sel.tracking"></span>
                    </p>
                </div>
                <button type="button" @click="open = false" class="dm-close" aria-label="Close">
                    <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="space-y-4 px-6 py-5">
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-ink-soft">Citizen</dt><dd class="text-ink" x-text="sel.citizen || '—'"></dd>
                    <template x-if="sel.purpose">
                        <dt class="text-ink-soft">Purpose</dt>
                    </template>
                    <template x-if="sel.purpose">
                        <dd class="text-ink" x-text="sel.purpose"></dd>
                    </template>
                    <dt class="text-ink-soft">Stage</dt><dd class="text-ink" x-text="sel.stageLabel || '—'"></dd>
                    <dt class="text-ink-soft">Office</dt><dd class="text-ink" x-text="sel.office || 'Reached no office'"></dd>
                    <dt class="text-ink-soft">Handled by</dt><dd class="text-ink" x-text="sel.assignee || 'Not assigned yet'"></dd>
                    <template x-if="sel.over">
                        <dt class="text-ink-soft">Past its stage time</dt>
                    </template>
                    <template x-if="sel.over">
                        <dd class="font-semibold text-status-red" x-text="'+' + sel.over"></dd>
                    </template>
                </dl>

                <p class="rounded-[10px] bg-green-wash px-3 py-2.5 text-sm text-ink">
                    Sends a bell notification to
                    <strong class="text-green-deep" x-text="sel.assignee || (sel.office ? `the ${sel.office} supervisors` : 'the supervisors')"></strong>
                    asking them to follow up. It does not reassign the request.
                </p>

                <div>
                    <label for="notifyNote" class="block text-sm font-semibold text-ink">Note <span class="font-normal text-ink-soft">(optional)</span></label>
                    <textarea id="notifyNote" x-ref="note" name="note" rows="3" maxlength="280"
                              placeholder="Add context for whoever picks this up…"
                              class="mt-1 w-full rounded-lg border border-hairline-strong bg-paper px-3 py-2.5 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/25"></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-hairline px-6 py-4">
                <button type="button" @click="open = false" class="cr-btn !px-5 !py-2.5 !text-sm">Cancel</button>
                <button type="submit" class="cr-btn cr-btn-primary !px-5 !py-2.5 !text-sm">Send notification</button>
            </div>
        </form>
    </div>
</div>
