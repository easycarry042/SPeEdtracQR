{{-- The one error report for the whole app.

     A problem is announced in front of whoever caused it — pinned to the top of
     the VIEWPORT — rather than printed as small print under the offending field
     or at the top of a long form, where it is off-screen as often as not and a
     refused submit reads as a dead button.

     One instance per page (the layouts include it), driven from anywhere by:

         window.ErrorAlert.show(['message', …], optionalFieldToFocus)
         window.ErrorAlert.hide()

     z-[120] so it clears the create-document modal at z-[100]. --}}
<div id="formErrorAlert" class="pointer-events-none fixed inset-x-0 top-4 z-[120] hidden justify-center px-4 sm:top-6" role="alert" aria-live="assertive">
    <div id="formErrorCard" class="pointer-events-auto w-full max-w-[560px] rounded-2xl border-2 border-[#c2410c] bg-white p-4 shadow-2xl sm:p-5">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[rgba(234,82,0,.12)] text-[#c2410c]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 3.5h.01M10.3 4.2 2.5 17.5A2 2 0 0 0 4.2 20.5h15.6a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0z"/>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p id="formErrorTitle" class="font-display text-[17px] font-black text-[#7c2d12] sm:text-[19px]">We can't submit this yet</p>
                <ul id="formErrorList" class="mt-1.5 space-y-1 text-sm text-[#7c2d12] sm:text-[15px]"></ul>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" id="formErrorGo" class="rounded-full bg-[#c2410c] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#9a3412] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#ea5200]/40">
                        Take me there
                    </button>
                    <button type="button" id="formErrorClose" class="rounded-full border border-[rgba(124,45,18,.3)] px-4 py-2 text-sm font-bold text-[#7c2d12] transition hover:bg-[rgba(234,82,0,.08)] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#ea5200]/40">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.ErrorAlert = (function () {
        const alertEl = document.getElementById('formErrorAlert');
        const card = document.getElementById('formErrorCard');
        const list = document.getElementById('formErrorList');
        const title = document.getElementById('formErrorTitle');
        const goBtn = document.getElementById('formErrorGo');
        const closeBtn = document.getElementById('formErrorClose');

        // Where "Take me there" jumps to: the first thing that is actually wrong.
        let target = null;

        function hide() {
            alertEl.classList.add('hidden');
            alertEl.classList.remove('flex');
        }

        /**
         * @param {string[]} messages
         * @param {Element|null} [focusTarget]
         * @param {string} [heading]
         */
        function show(messages, focusTarget, heading) {
            const problems = (messages || []).filter(Boolean);
            if (! problems.length) { return; }

            list.textContent = '';
            problems.forEach((message) => {
                const item = document.createElement('li');
                // A single problem reads better as a sentence than as a bullet.
                item.className = problems.length > 1 ? 'list-outside list-disc ml-4' : '';
                item.textContent = message;
                list.append(item);
            });

            title.textContent = heading
                || (problems.length > 1 ? `${problems.length} things need fixing` : "We can't submit this yet");

            target = focusTarget || null;
            goBtn.classList.toggle('hidden', ! target);

            alertEl.classList.remove('hidden');
            alertEl.classList.add('flex');

            // Replay the entrance so a second failed attempt is visibly a new
            // answer rather than the same card sitting there.
            card.classList.remove('req-pop');
            void card.offsetWidth;
            card.classList.add('req-pop');
        }

        goBtn.addEventListener('click', () => {
            if (! target) { return; }
            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            // Scroll first, then focus without a second jump — focus() alone
            // lands the field under this alert on some browsers.
            target.focus({ preventScroll: true });
        });

        closeBtn.addEventListener('click', hide);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { hide(); }
        });

        return { show, hide };
    })();
</script>
