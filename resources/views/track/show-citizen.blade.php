@php
    // Same status → pill-class mapping as <x-status-badge>, kept inline here
    // because this badge needs specific ids (#statusBadge/#statusLabel) for
    // the live Echo/poll JS below to patch in place.
    $pillClassMap = [
        'completed' => 'p-green', 'approved' => 'p-green',
        'pending' => 'p-muted',
        'returned' => 'p-red', 'rejected' => 'p-red', 'denied' => 'p-red',
        'on_hold' => 'p-hold',
    ];
    $pillClass = $pillClassMap[$document->status] ?? 'p-amber'; // in_progress / in_review / in_transit
@endphp

<x-citizen-layout>
    <x-slot name="title">Tracking {{ $document->tracking_number }}</x-slot>

    {{-- Back / Home live in the shared public header — no in-body duplicate here. --}}

    <div class="mx-auto max-w-2xl space-y-6">

        @if(session('ticket_submitted'))
            <div class="rounded-xl border border-hairline bg-green-wash px-6 py-4 text-sm text-green-deep">
                <p class="font-semibold">Your request has been submitted.</p>
                <p class="mt-1">Save your tracking number <span class="id-chip font-bold">{{ session('ticket_submitted') }}</span>. A confirmation with your QR code has been emailed to you.</p>
            </div>
        @endif

        {{-- ── Status Progress (the first thing the citizen sees) ───────────── --}}
        <div class="panel">
            <div class="ph"><h2>Status Progress</h2></div>
            <div class="pb"><x-routing-stepper :document="$document" /></div>
        </div>

        {{-- ── Live Status Card ─────────────────────────────────────────────── --}}
        <div class="panel" id="statusCard">
            <div class="ph">
                <h2>Current Status</h2>
                <div class="flex items-center gap-2 text-xs text-ink-soft" id="lastChecked">
                    <svg class="h-3.5 w-3.5 animate-spin text-green hidden" id="pollSpinner" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="lastCheckedText">Auto-updates every 30 s</span>
                </div>
            </div>

            <div class="pb">
                {{-- Status badge --}}
                <div class="flex flex-wrap items-center gap-4">
                    <span id="statusBadge" class="pill {{ $pillClass }}">
                        <span id="statusLabel">{{ $document->statusEnum()->label() }}</span>
                    </span>

                    <div>
                        <p class="text-xs text-ink-soft">Handled by</p>
                        <p class="text-sm font-semibold text-ink" id="currentDept">
                            {{ $document->assignedTo?->name ?? 'Not yet assigned' }}
                        </p>
                    </div>
                </div>

                {{-- Plain-language explanation of the current status (Help / Match) --}}
                <p class="mt-3 text-sm text-ink-soft" id="statusDescription">{{ $document->statusEnum()->description() }}</p>

                {{-- The decision itself: who closed the request and why. Without
                     this the citizen only sees "Denied" and a generic sentence
                     telling them a reason should exist. --}}
                @php
                    $decisionStatuses = [
                        \App\Enums\DocumentStatus::Denied->value,
                        \App\Enums\DocumentStatus::Returned->value,
                        \App\Enums\DocumentStatus::OnHold->value,
                    ];
                    $showsDecision = in_array($document->status, $decisionStatuses, true);
                    $decisionActor = $showsDecision ? $document->decisionActor() : null;
                    $isDenied = $document->status === \App\Enums\DocumentStatus::Denied->value;
                @endphp

                @if($showsDecision && ($decisionActor || filled($document->remarks)))
                    <div class="mt-4 rounded-[10px] border {{ $isDenied ? 'border-status-red/30 bg-red-50/60' : 'border-hairline bg-green-wash/40' }} p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $isDenied ? 'text-status-red' : 'text-ink-soft' }}">
                            {{ $isDenied ? 'Reason for denial' : 'Message from staff' }}
                        </p>

                        @if($decisionActor)
                            <p class="mt-1 text-sm text-ink">
                                <span class="font-semibold">{{ $decisionActor['name'] }}</span>
                                <span class="text-ink-soft">· {{ $decisionActor['role'] }}</span>
                                @if($document->decided_at)
                                    <span class="text-ink-soft">· {{ $document->decided_at->format('M j, Y g:i A') }}</span>
                                @endif
                            </p>
                        @endif

                        @if(filled($document->remarks))
                            <p class="mt-2 whitespace-pre-line text-sm text-ink">{{ $document->remarks }}</p>
                        @else
                            <p class="mt-2 text-sm italic text-ink-soft">No reason was recorded. Please contact the office for details.</p>
                        @endif
                    </div>
                @endif

                <details class="mt-3 text-sm">
                    <summary class="inline-flex cursor-pointer items-center gap-1.5 font-semibold text-green hover:underline">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18z"/></svg>
                        What do the statuses mean?
                    </summary>
                    <dl class="mt-2 space-y-1.5 border-t border-hairline pt-2">
                        @foreach(\App\Enums\DocumentStatus::cases() as $s)
                            <div class="flex flex-col gap-0.5 sm:flex-row sm:gap-3">
                                <dt class="shrink-0 font-semibold text-ink sm:w-40">{{ $s->label() }}</dt>
                                <dd class="text-ink-soft">{{ $s->description() }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </details>

                {{-- Update banner (hidden until status changes) --}}
                <div id="updateBanner"
                     class="mt-4 hidden rounded-xl border border-hairline bg-green-wash px-4 py-3 text-sm font-medium text-green-deep">
                    Status updated! Refreshing…
                </div>

                {{-- QR-gated pickup: the emailed/printed QR is the claim stub. --}}
                @if($document->status === 'completed')
                    @if($document->claimed_at)
                        <div class="mt-4 rounded-xl border border-hairline bg-green-wash px-4 py-3 text-sm text-green-deep">
                            <b>Released.</b> This document was claimed at the counter on
                            <span class="id-chip">{{ $document->claimed_at->format('M d, Y') }}</span>.
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-hairline-strong bg-green-wash px-4 py-3 text-sm text-green-deep">
                            <b>Ready for pickup.</b> Bring your QR code (from your confirmation email or printed slip)
                            to the counter — staff will scan it to release your document. No QR means slower manual verification.
                        </div>
                    @endif

                    @if(! empty($verifyUrl))
                        <p class="mt-3 text-xs text-ink-soft">
                            Third parties can confirm this document is genuine at any time via its
                            <a href="{{ $verifyUrl }}" class="font-semibold text-green underline">official verification page</a>.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- ── Predicted completion (self-hosted analytics) ─────────────────── --}}
        <x-eta-estimate :prediction="$prediction ?? null" :document="$document" />

        {{-- ── Document details ─────────────────────────────────────────────── --}}
        {{-- Internal document images are intentionally NOT shown on the public
             tracking page; they are only visible to authorized staff. --}}
        <div class="panel">
            <div class="ph">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-wash text-green-deep">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-[15px] font-bold text-ink">{{ $document->document_type }}</p>
                        <p class="text-[12.5px] text-ink-soft">{{ $document->citizen_name ?? 'N/A' }} &middot; submitted {{ $document->created_at->format('M d, Y') }}</p>
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Tracking ID</p>
                    <span class="id-chip mt-1 text-[13px] font-bold text-green-deep">{{ $document->tracking_number }}</span>
                </div>
            </div>

            {{-- Who / where is responsible, plus the requester's contact details. --}}
            <div class="pb">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">THED ID</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $document->department?->code ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Department</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $document->department?->name ?? 'Not yet routed' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Staff assigned</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $document->assignedTo?->name ?? 'Not yet assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Email</dt>
                        <dd class="mt-0.5 break-words text-sm text-ink">{{ $document->citizen_email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10.5px] font-semibold uppercase tracking-wide text-ink-soft">Contact number</dt>
                        <dd class="mt-0.5 text-sm text-ink">{{ $document->citizen_contact ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 border-t border-hairline pt-4">
                    <a href="{{ route('track.slip', $document->tracking_number) }}" target="_blank" rel="noopener" class="cr-btn cr-btn-sm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                        Print claim slip
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Self-hosted AI assistant (Pillar 3) — floats bottom-right ─────── --}}
        <x-doc-assistant :document="$document" />

        {{-- ── Messages from staff (public posts only — internal notes never shown) ── --}}
        @php $publicPosts = $document->comments->where('visibility', 'public'); @endphp
        <div class="panel">
            <div class="ph"><h2>Messages from staff</h2></div>
            <div class="pb space-y-3" id="citizenMessages">
                @forelse($publicPosts as $post)
                    <div class="rounded-xl border border-hairline bg-paper p-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[13px] font-bold text-ink">{{ $post->author_type === 'system' ? 'Update' : 'Staff' }}</span>
                            <span class="text-[12px] text-ink-soft">{{ optional($post->created_at)->format('M d, Y h:i A') }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-ink">{{ $post->body }}</p>
                    </div>
                @empty
                    <p id="citizenMessagesEmpty" class="text-sm italic text-ink-soft">No messages yet.</p>
                @endforelse
            </div>
        </div>

        {{-- ── Documents needing revision / rejected (per-requirement) ────────── --}}
        @php
            $needsRevision = $document->requirements->where('review_status', 'needs_revision');
            $rejectedReqs = $document->requirements->where('review_status', 'rejected');
        @endphp
        @if($needsRevision->isNotEmpty() || $rejectedReqs->isNotEmpty())
        <div class="panel">
            <div class="ph">
                <div>
                    <h2>Documents to fix</h2>
                    <p class="sub mt-0.5">Staff reviewed your uploads. Re-upload <span class="font-semibold text-green-deep">only</span> the items marked below.</p>
                </div>
            </div>
            <div class="pb space-y-4">
                @if(session('upload_success'))
                    <div class="rounded-lg border border-hairline bg-green-wash px-4 py-3 text-sm text-green-deep">{{ session('upload_success') }}</div>
                @endif
                @if($errors->any())
                    <div class="rounded-lg border border-status-red-wash bg-status-red-wash px-4 py-3 text-sm text-status-red">{{ $errors->first() }}</div>
                @endif
                @foreach($needsRevision as $req)
                    <div class="rounded-xl border border-status-amber-wash bg-status-amber-wash p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-status-amber">{{ $req->label }}</span>
                            <span class="rounded-full bg-white/60 px-2 py-0.5 text-[11px] font-semibold text-status-amber">Needs revision</span>
                        </div>
                        @if($req->review_comment)
                            <p class="mt-1 text-sm text-status-amber"><span class="font-semibold">What to fix:</span> {{ $req->review_comment }}</p>
                        @endif
                        @if($document->status !== 'completed')
                            <form method="POST" action="{{ route('track.requirement-reupload', [$document->tracking_number, $req]) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                                @csrf
                                <label for="reupload-{{ $req->id }}" class="sr-only">Re-upload {{ $req->label }}</label>
                                <input id="reupload-{{ $req->id }}" type="file" name="file" accept="image/*,.pdf,.doc,.docx" required
                                       class="block text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-green file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-green-deep" />
                                <button type="submit" class="cr-btn cr-btn-primary min-h-[44px]">Re-upload</button>
                            </form>
                        @endif
                    </div>
                @endforeach
                @foreach($rejectedReqs as $req)
                    <div class="rounded-xl border border-status-red-wash bg-status-red-wash p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-status-red">{{ $req->label }}</span>
                            <span class="rounded-full bg-white/60 px-2 py-0.5 text-[11px] font-semibold text-status-red">Rejected</span>
                        </div>
                        @if($req->review_comment)
                            <p class="mt-1 text-sm text-status-red"><span class="font-semibold">Reason:</span> {{ $req->review_comment }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Citizen upload (notifies current department only) ──────────────── --}}
        @if($document->status !== 'completed')
        <div class="panel">
            <div class="ph">
                <div>
                    <h2>Upload supporting documents</h2>
                    <p class="sub mt-0.5">Files are sent only to <span class="font-semibold text-green-deep">the office handling your ticket</span>.</p>
                </div>
            </div>
            <div class="pb">
                @if(session('upload_success'))
                    <div class="mb-4 rounded-lg border border-hairline bg-green-wash px-4 py-3 text-sm text-green-deep">
                        {{ session('upload_success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-status-red-wash bg-status-red-wash px-4 py-3 text-sm text-status-red">
                        {{ $errors->first() }}
                    </div>
                @endif
                <form method="POST" action="{{ route('track.citizen-upload', $document->tracking_number) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label for="citizen_attachments" class="block text-sm font-medium text-ink">Files (up to 5 — images, PDF or Word)</label>
                        <input type="file" id="citizen_attachments" name="attachments[]" accept="image/*,.pdf,.doc,.docx" multiple required
                               class="mt-1 block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-green file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-green-deep" />
                    </div>
                    <div>
                        <label for="citizen_note" class="block text-sm font-medium text-ink">Short note (optional)</label>
                        <textarea id="citizen_note" name="note" rows="2" maxlength="1000" placeholder="e.g. Missing ID copy attached"
                                  class="mt-1 block w-full rounded-lg border border-hairline-strong text-sm shadow-sm">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="cr-btn cr-btn-primary w-full justify-center sm:w-auto">
                        Send to department
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- ── Scan Timeline ─────────────────────────────────────────────────── --}}
        <div class="panel">
            <div class="ph"><h2>Activity Log</h2></div>
            <div class="pb divide-y divide-hairline" id="timeline">
                @forelse($timeline as $log)
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full
                                {{ $log['action'] === 'in' ? 'bg-green-bright' : 'bg-hairline-strong' }}">
                            </span>
                            <span class="text-sm text-ink">{{ $log['event'] }}</span>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-ink-soft">{{ $log['timestamp'] }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-ink-soft">No activity recorded yet.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── Live status: real-time via Laravel Echo / Reverb, 30 s poll fallback ─ --}}
    <script>
        const trackingNumber = @json($document->tracking_number);
        const statusEndpoint = '/track/' + encodeURIComponent(trackingNumber) + '/status';
        let currentStatus    = @json($document->status);
        let liveConnected    = false;

        // Mirrors $pillClassMap in the Blade above (components/status-badge.blade.php's map).
        const statusPill = {
            completed:   { cls: 'p-green', label: 'Completed'  },
            approved:    { cls: 'p-green', label: 'Approved'   },
            pending:     { cls: 'p-muted', label: 'Pending'    },
            returned:    { cls: 'p-red',   label: 'Returned / For Revision' },
            rejected:    { cls: 'p-red',   label: 'Rejected'   },
            denied:      { cls: 'p-red',   label: 'Denied'     },
            on_hold:     { cls: 'p-hold',  label: 'On Hold'    },
            in_progress: { cls: 'p-amber', label: 'In Progress' },
            in_review:   { cls: 'p-amber', label: 'In Review'  },
            in_transit:  { cls: 'p-amber', label: 'In Progress' },
        };

        // Plain-language status descriptions (mirrors DocumentStatus::description()).
        const statusDesc = @json(collect(\App\Enums\DocumentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->description()]));

        function getStatusClasses(status) {
            return statusPill[status] ?? statusPill['in_transit'];
        }

        // Update the status badge + current location in place (no reload).
        function applyStatus(status, department) {
            const badge = document.getElementById('statusBadge');
            const label = document.getElementById('statusLabel');
            const dept  = document.getElementById('currentDept');
            const classes = getStatusClasses(status);

            badge.className = `pill ${classes.cls}`;
            label.textContent = classes.label;

            const desc = document.getElementById('statusDescription');
            if (desc && statusDesc[status]) { desc.textContent = statusDesc[status]; }

            if (department) {
                dept.textContent = department;
            } else if (department === null) {
                dept.textContent = 'Not yet assigned';
            }
            currentStatus = status;
        }

        // Prepend a new activity row to the timeline (matching the Blade markup).
        function prependTimelineEntry(entry) {
            const timeline = document.getElementById('timeline');
            if (!timeline) return;

            // Drop the "No activity recorded yet." placeholder if present.
            const placeholder = timeline.querySelector('p');
            if (placeholder) placeholder.remove();

            const row = document.createElement('div');
            row.className = 'flex items-start justify-between gap-4 py-3 transition-colors';
            const dotColor = entry.action === 'in' ? 'bg-emerald-500' : 'bg-gray-400';
            row.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${dotColor}"></span>
                    <span class="eventText text-sm text-gray-700"></span>
                </div>
                <span class="tsText shrink-0 text-xs font-semibold text-gray-400"></span>`;
            // Use textContent to avoid injecting unescaped names into the DOM.
            row.querySelector('.eventText').textContent = entry.event ?? '';
            row.querySelector('.tsText').textContent = entry.timestamp ?? '';
            timeline.prepend(row);

            // Brief highlight so the citizen notices the new entry.
            row.classList.add('bg-emerald-50');
            setTimeout(() => row.classList.remove('bg-emerald-50'), 2000);
        }

        function flashBanner(message) {
            const banner = document.getElementById('updateBanner');
            if (!banner) return;
            banner.textContent = message;
            banner.classList.remove('hidden');
            clearTimeout(window.__bannerTimer);
            window.__bannerTimer = setTimeout(() => banner.classList.add('hidden'), 5000);
        }

        function setLiveIndicator() {
            const checkedText = document.getElementById('lastCheckedText');
            if (checkedText) {
                checkedText.innerHTML =
                    '<span class="inline-flex items-center gap-1.5">' +
                    '<span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>Live</span>';
            }
        }

        // ── Real-time updates via Laravel Echo / Reverb ────────────────────────
        if (window.Echo) {
            try {
                window.Echo.channel('documents.' + trackingNumber)
                    .listen('.moved', (e) => {
                        applyStatus(e.status, e.current_department);
                        prependTimelineEntry(e);
                        flashBanner('Status updated!');
                    })
                    .listen('.comment', (e) => {
                        // Only public posts ever reach this public channel.
                        const box = document.getElementById('citizenMessages');
                        if (!box || document.getElementById('cmsg-' + e.id)) return;
                        document.getElementById('citizenMessagesEmpty')?.remove();
                        const el = document.createElement('div');
                        el.id = 'cmsg-' + e.id;
                        el.className = 'rounded-xl border border-hairline bg-paper p-3';
                        el.innerHTML = '<div class="flex items-center justify-between">' +
                            '<span class="text-[13px] font-bold text-ink">' + (e.author_type === 'system' ? 'Update' : 'Staff') + '</span>' +
                            '<span class="text-[12px] text-ink-soft">' + (e.timestamp || '') + '</span></div>' +
                            '<p class="mt-1 whitespace-pre-wrap text-sm text-ink"></p>';
                        el.querySelector('p').textContent = e.body;
                        box.prepend(el);
                        flashBanner('New message from staff');
                    });

                const pusher = window.Echo.connector?.pusher;
                if (pusher) {
                    pusher.connection.bind('connected', () => { liveConnected = true; setLiveIndicator(); });
                    pusher.connection.bind('unavailable', () => { liveConnected = false; });
                    pusher.connection.bind('disconnected', () => { liveConnected = false; });
                } else {
                    liveConnected = true;
                    setLiveIndicator();
                }
            } catch (_) {
                liveConnected = false; // Echo unavailable — poll fallback stays active.
            }
        }

        // ── Fallback: poll every 30 s only while the live socket is not connected ─
        async function pollStatus() {
            if (liveConnected) return;

            const spinner = document.getElementById('pollSpinner');
            const checkedText = document.getElementById('lastCheckedText');
            spinner.classList.remove('hidden');

            try {
                const res = await fetch(statusEndpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                // Status changed but we have no live timeline payload — reload to refresh it.
                if (data.status !== currentStatus) {
                    flashBanner('Status updated! Refreshing…');
                    setTimeout(() => location.reload(), 1800);
                    return;
                }

                applyStatus(data.status, data.current_department);

                const now = new Date();
                checkedText.textContent = 'Last checked ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (_) {
                // silent fail — network may be temporarily down
            } finally {
                spinner.classList.add('hidden');
            }
        }

        setInterval(pollStatus, 30000);
    </script>
</x-citizen-layout>
