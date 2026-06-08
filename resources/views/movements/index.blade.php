<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Document Movements</h1>
                @if(!$isOrgWide && $user->department)
                    <p class="mt-1 text-sm text-emerald-700">{{ $user->department->name }}</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6" id="movementsPage">

        {{-- ── Tabs ────────────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-1 rounded-2xl border border-gray-200 bg-gray-100 p-1 w-fit shadow-sm">
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'inbox']) }}"
               class="px-5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $tab === 'inbox' ? 'bg-white text-emerald-900 shadow' : 'text-gray-500 hover:text-gray-800' }}">
                Inbox
                @if($inboxDocuments->total() > 0)
                    <span class="ml-1.5 rounded-full {{ $tab === 'inbox' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }} px-2 py-0.5 text-xs font-bold">
                        {{ $inboxDocuments->total() }}
                    </span>
                @endif
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'tracking']) }}"
               class="px-5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $tab === 'tracking' ? 'bg-white text-emerald-900 shadow' : 'text-gray-500 hover:text-gray-800' }}">
                Tracking
                @if($trackingDocuments->total() > 0)
                    <span class="ml-1.5 rounded-full {{ $tab === 'tracking' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }} px-2 py-0.5 text-xs font-bold">
                        {{ $trackingDocuments->total() }}
                    </span>
                @endif
            </a>
            <a href="{{ request()->fullUrlWithQuery(['tab' => 'sent']) }}"
               class="px-5 py-2 rounded-xl text-sm font-semibold transition-all
                      {{ $tab === 'sent' ? 'bg-white text-emerald-900 shadow' : 'text-gray-500 hover:text-gray-800' }}">
                Sent Today
                @if($sentDocuments->total() > 0)
                    <span class="ml-1.5 rounded-full {{ $tab === 'sent' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }} px-2 py-0.5 text-xs font-bold">
                        {{ $sentDocuments->total() }}
                    </span>
                @endif
            </a>
        </div>

        {{-- ── Filters ─────────────────────────────────────────────────────── --}}
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if($isOrgWide)
                <select name="department" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(request('department') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            @endif
            <label class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm cursor-pointer">
                <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="font-medium text-gray-700">Overdue only</span>
            </label>
            <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">
                Filter
            </button>
            @if(request()->hasAny(['department', 'overdue']))
                <a href="{{ route('movements.index', ['tab' => $tab]) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>

        {{-- ── Card Grid ───────────────────────────────────────────────────── --}}
        @php
            $activeSet = match ($tab) {
                'sent' => $sentDocuments,
                'tracking' => $trackingDocuments,
                default => $inboxDocuments,
            };
            $emptyMessage = match ($tab) {
                'sent' => 'No documents sent today.',
                'tracking' => 'No documents to track. Items you forwarded or are on your route will appear here.',
                default => 'No documents in your inbox.',
            };
        @endphp

        @if($activeSet->isEmpty())
            <div class="rounded-2xl border border-gray-200/90 bg-white px-6 py-16 text-center shadow-sm">
                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M4.5 19.5l15-15M19.5 4.5l-15 15"/>
                </svg>
                <p class="text-sm font-medium text-gray-500">{{ $emptyMessage }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($activeSet as $document)
                    @php
                        if ($document->slaOverdue) {
                            $borderColor = 'border-l-red-500';
                            $slaBarColor = 'bg-red-500';
                            $slaBadge    = 'bg-red-100 text-red-700';
                            $slaLabel    = '+' . $document->slaHoursOver . 'h overdue';
                            $slaIcon     = 'text-red-500';
                        } elseif ($document->slaPct >= 75) {
                            $borderColor = 'border-l-amber-400';
                            $slaBarColor = 'bg-amber-400';
                            $slaBadge    = 'bg-amber-100 text-amber-700';
                            $slaLabel    = '~' . $document->slaHoursLeft . 'h left';
                            $slaIcon     = 'text-amber-500';
                        } else {
                            $borderColor = 'border-l-emerald-500';
                            $slaBarColor = 'bg-emerald-500';
                            $slaBadge    = 'bg-emerald-50 text-emerald-700';
                            $slaLabel    = '~' . $document->slaHoursLeft . 'h left';
                            $slaIcon     = 'text-emerald-500';
                        }
                    @endphp

                    <div class="relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 border-l-4 {{ $borderColor }} bg-white shadow-sm transition hover:shadow-md">

                        {{-- SLA progress strip at top --}}
                        <div class="h-1 w-full bg-gray-100">
                            <div class="h-1 {{ $slaBarColor }} transition-all duration-500" style="width: {{ $document->slaPct }}%"></div>
                        </div>

                        <div class="flex flex-col gap-4 p-5">

                            {{-- Header row --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/>
                                        </svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-gray-900">{{ $document->document_type }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ $document->citizen_name ?? '—' }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="font-mono text-xs font-semibold text-emerald-700">{{ $document->tracking_number }}</p>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $slaBadge }}">
                                        {{ $slaLabel }}
                                    </span>
                                </div>
                            </div>

                            @if($document->routingChain->isNotEmpty())
                                <x-routing-stepper :document="$document" :chain="$document->routingChain" compact />
                            @else
                                <p class="text-xs text-gray-400 italic">No routing path set for this document.</p>
                            @endif

                            <x-document-images :document="$document" class="mt-1" />

                            @if($tab === 'tracking' && $document->currentDepartment)
                                <p class="text-xs text-gray-500">
                                    Currently at: <span class="font-semibold text-emerald-800">{{ $document->currentDepartment->name }}</span>
                                </p>
                            @endif

                            @if($tab === 'inbox' && $document->canAct)
                                @php
                                    $reviewImages = $document->attachments
                                        ->map(fn ($a) => route('attachments.show', $a))
                                        ->filter()
                                        ->values();
                                @endphp
                                <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                                    <a href="{{ url('/track/'.$document->tracking_number) }}" target="_blank" rel="noopener"
                                       class="text-xs font-medium text-gray-400 hover:text-gray-700 transition shrink-0" title="Public tracking page for citizens">
                                        Public link
                                    </a>
                                    <div class="flex-1"></div>
                                    <button type="button"
                                            class="js-review-btn inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95"
                                            data-document-id="{{ $document->id }}"
                                            data-tracking="{{ $document->tracking_number }}"
                                            data-from="{{ $document->current_department_id }}"
                                            data-to="{{ $document->nextDepartment?->id ?? '' }}"
                                            data-dept="{{ $document->nextDepartment?->name ?? '' }}"
                                            data-type="{{ $document->document_type }}"
                                            data-citizen="{{ $document->citizen_name ?? '' }}"
                                            data-remarks="{{ $document->remarks ?? '' }}"
                                            data-description="{{ $document->description ?? '' }}"
                                            data-images='@json($reviewImages)'
                                            data-last-stop="{{ $document->isLastStop ? '1' : '0' }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1 1 0 0 1 0-.639l1.7-3.2a1 1 0 0 1 .87-.561h11.79a1 1 0 0 1 .87.561l1.7 3.2a1 1 0 0 1 0 .639l-1.7 3.2a1 1 0 0 1-.87.561H4.606a1 1 0 0 1-.87-.561l-1.7-3.2z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6"/>
                                        </svg>
                                        Review
                                    </button>
                                    @if($document->isLastStop)
                                        <button type="button"
                                                class="js-complete-btn inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 active:scale-95"
                                                data-tracking="{{ $document->tracking_number }}">
                                            Done
                                        </button>
                                    @endif
                                </div>
                            @elseif($tab !== 'sent')
                                <div class="flex gap-2 border-t border-gray-100 pt-3">
                                    <span class="flex-1 rounded-xl bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-500">
                                        Use <strong>Review</strong> to open files
                                    </span>
                                    @if($tab === 'tracking')
                                        <span class="flex-1 rounded-xl bg-emerald-50 px-3 py-2 text-center text-xs font-semibold text-emerald-700">
                                            Tracking progress
                                        </span>
                                    @endif
                                </div>
                            @else
                                <div class="flex gap-2 pt-1">
                                    <span class="flex-1 rounded-xl bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-500">
                                        Use <strong>Review</strong> to open files
                                    </span>
                                    <span class="flex-1 rounded-xl bg-gray-100 px-3 py-2 text-center text-xs font-semibold text-gray-400">
                                        Sent out today
                                    </span>
                                </div>
                            @endif

                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if($activeSet->hasPages())
                <div class="pt-2">
                    @php
                        $pageParam = match ($tab) {
                            'sent' => 'sent_page',
                            'tracking' => 'tracking_page',
                            default => 'inbox_page',
                        };
                    @endphp
                    {{ $activeSet->appends(request()->except($pageParam))->links() }}
                </div>
            @endif
        @endif

        {{-- ── Review Modal ─────────────────────────────────────────────────── --}}
        <div id="reviewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4 overflow-y-auto">
            <div class="my-4 w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                <h3 class="text-lg font-bold text-gray-900">Review Document</h3>
                <p class="mt-1 font-mono text-sm font-semibold text-emerald-700" id="reviewTrackingLabel"></p>

                <div class="mt-4 rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm">
                    <p><span class="font-semibold text-gray-600">Type:</span> <span id="reviewType"></span></p>
                    <p class="mt-1"><span class="font-semibold text-gray-600">Citizen:</span> <span id="reviewCitizen"></span></p>
                    <p class="mt-1 hidden" id="reviewRemarksWrap"><span class="font-semibold text-gray-600">File name:</span> <span id="reviewRemarks"></span></p>
                    <p class="mt-1 hidden" id="reviewDescWrap"><span class="font-semibold text-gray-600">Description:</span> <span id="reviewDescription"></span></p>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Attached files</p>
                    <div id="reviewImages" class="flex flex-wrap gap-2"></div>
                    <p id="reviewNoImages" class="hidden text-sm text-gray-400 italic">No images attached yet.</p>
                </div>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Add photos (optional, up to 10)</label>
                    <input id="reviewAttachment" type="file" accept="image/*" multiple
                           class="w-full text-sm text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800">
                    <button type="button" id="reviewAddPhotosBtn"
                            class="mt-2 w-full rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 transition hover:bg-emerald-100 disabled:opacity-50">
                        Save photos to document
                    </button>
                </div>

                <div id="reviewSendSection" class="mt-4 border-t border-gray-100 pt-4">
                    <p class="mb-2 text-sm font-semibold text-gray-800">Send to next department</p>
                    <select id="reviewDeptSelect"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                        <option value="">— Select department —</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                    <p id="reviewDeptHint" class="mt-1 hidden text-xs text-emerald-600"></p>
                    <div class="mt-3">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Remarks (optional)</label>
                        <input id="reviewRemarksInput" type="text" placeholder="Add a note..."
                               class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    </div>
                </div>

                <div id="reviewLastStopNote" class="mt-4 hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    This is the <strong>final stop</strong> on the route. After review, mark the document as done.
                </div>

                <div id="reviewResult" class="mt-3 hidden rounded-xl border p-3 text-sm font-semibold"></div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="button" id="reviewCancelBtn"
                            class="flex-1 min-w-[6rem] rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Close
                    </button>
                    <button type="button" id="reviewSendBtn"
                            class="flex-1 min-w-[6rem] rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                        Send onward
                    </button>
                    <button type="button" id="reviewCompleteBtn"
                            class="hidden flex-1 min-w-[6rem] rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600">
                        Mark as Done
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Complete Confirmation Modal ──────────────────────────────────── --}}
        <div id="completeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                <h3 class="text-lg font-bold text-gray-900">Mark as Completed</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Close document <span id="completeTrackingLabel" class="font-mono font-semibold text-emerald-700"></span>? This is the final step.
                </p>
                <div id="completeResult" class="mt-3 hidden rounded-xl border p-3 text-sm font-semibold"></div>
                <div class="mt-5 flex gap-3">
                    <button type="button" id="completeCancelBtn"
                            class="flex-1 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="completeSubmitBtn"
                            class="flex-1 rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600 disabled:opacity-60">
                        Mark Complete
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const scanUrl = @json(route('api.scan.store'));
            const attachmentUrlBase = @json(url('/documents'));
            const completeUrlBase = @json(url('/documents'));

            const reviewModal = document.getElementById('reviewModal');
            const completeModal = document.getElementById('completeModal');
            const reviewDeptSelect = document.getElementById('reviewDeptSelect');
            const reviewSendBtn = document.getElementById('reviewSendBtn');
            const reviewCompleteBtn = document.getElementById('reviewCompleteBtn');
            const reviewResult = document.getElementById('reviewResult');
            const reviewImages = document.getElementById('reviewImages');
            const reviewNoImages = document.getElementById('reviewNoImages');
            const reviewSendSection = document.getElementById('reviewSendSection');
            const reviewLastStopNote = document.getElementById('reviewLastStopNote');
            const completeTrackingLabel = document.getElementById('completeTrackingLabel');
            const completeResult = document.getElementById('completeResult');
            const completeSubmitBtn = document.getElementById('completeSubmitBtn');

            let reviewState = { tracking: '', from: '', documentId: '' };

            function showModal(modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function hideModal(modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            function renderReviewImages(urls) {
                reviewImages.innerHTML = '';
                if (!urls || !urls.length) {
                    reviewNoImages.classList.remove('hidden');
                    return;
                }
                reviewNoImages.classList.add('hidden');
                urls.forEach(url => {
                    const a = document.createElement('a');
                    a.href = url;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.className = 'block overflow-hidden rounded-lg ring-1 ring-gray-200 hover:ring-emerald-400';
                    const img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Attachment';
                    img.className = 'h-24 w-24 object-cover bg-gray-100';
                    img.loading = 'lazy';
                    a.appendChild(img);
                    reviewImages.appendChild(a);
                });
            }

            function openReview(btn) {
                reviewState.tracking = btn.dataset.tracking;
                reviewState.from = btn.dataset.from;
                reviewState.documentId = btn.dataset.documentId || '';
                const isLast = btn.dataset.lastStop === '1';

                document.getElementById('reviewTrackingLabel').textContent = reviewState.tracking;
                document.getElementById('reviewType').textContent = btn.dataset.type || '—';
                document.getElementById('reviewCitizen').textContent = btn.dataset.citizen || '—';

                const remarks = btn.dataset.remarks || '';
                const desc = btn.dataset.description || '';
                document.getElementById('reviewRemarksWrap').classList.toggle('hidden', !remarks);
                document.getElementById('reviewRemarks').textContent = remarks;
                document.getElementById('reviewDescWrap').classList.toggle('hidden', !desc);
                document.getElementById('reviewDescription').textContent = desc;

                let images = [];
                try { images = JSON.parse(btn.dataset.images || '[]'); } catch (e) {}
                renderReviewImages(images);

                reviewDeptSelect.value = btn.dataset.to || '';
                document.getElementById('reviewRemarksInput').value = '';
                document.getElementById('reviewAttachment').value = '';
                reviewResult.classList.add('hidden');

                const hint = document.getElementById('reviewDeptHint');
                if (btn.dataset.dept && !isLast) {
                    hint.textContent = 'Suggested next: ' + btn.dataset.dept;
                    hint.classList.remove('hidden');
                } else {
                    hint.classList.add('hidden');
                }

                reviewSendSection.classList.toggle('hidden', isLast);
                reviewLastStopNote.classList.toggle('hidden', !isLast);
                reviewSendBtn.classList.toggle('hidden', isLast);
                reviewCompleteBtn.classList.toggle('hidden', !isLast);
                reviewSendBtn.disabled = !reviewDeptSelect.value;
                showModal(reviewModal);
            }

            document.querySelectorAll('.js-review-btn').forEach(btn => {
                btn.addEventListener('click', () => openReview(btn));
            });

            reviewDeptSelect?.addEventListener('change', () => {
                reviewSendBtn.disabled = !reviewDeptSelect.value;
            });

            document.getElementById('reviewCancelBtn')?.addEventListener('click', () => hideModal(reviewModal));
            reviewModal?.addEventListener('click', e => { if (e.target === reviewModal) hideModal(reviewModal); });

            async function markComplete(tracking) {
                const res = await fetch(completeUrlBase + '/' + encodeURIComponent(tracking) + '/complete', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                return res.json().then(data => ({ ok: res.ok, data }));
            }

            document.getElementById('reviewAddPhotosBtn')?.addEventListener('click', async function () {
                const input = document.getElementById('reviewAttachment');
                if (!reviewState.documentId || !input?.files?.length) {
                    reviewResult.classList.remove('hidden');
                    reviewResult.className = 'mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900';
                    reviewResult.textContent = 'Choose one or more photos first.';
                    return;
                }
                const btn = document.getElementById('reviewAddPhotosBtn');
                btn.disabled = true;
                btn.textContent = 'Uploading…';
                const formData = new FormData();
                Array.from(input.files).forEach(file => formData.append('attachments[]', file));
                try {
                    const res = await fetch(attachmentUrlBase + '/' + reviewState.documentId + '/attachments', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await res.json();
                    reviewResult.classList.remove('hidden');
                    if (res.ok) {
                        reviewResult.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
                        reviewResult.textContent = data.message || 'Photos saved.';
                        const urls = (data.attachments || []).map(a => a.url);
                        const existing = Array.from(reviewImages.querySelectorAll('a')).map(a => a.href);
                        renderReviewImages(existing.concat(urls));
                        input.value = '';
                    } else {
                        reviewResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                        reviewResult.textContent = data.message || 'Upload failed.';
                    }
                } catch (e) {
                    reviewResult.classList.remove('hidden');
                    reviewResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                    reviewResult.textContent = 'Network error.';
                }
                btn.disabled = false;
                btn.textContent = 'Save photos to document';
            });

            reviewSendBtn?.addEventListener('click', async function () {
                if (!reviewDeptSelect.value) return;
                reviewSendBtn.disabled = true;
                reviewSendBtn.textContent = 'Sending…';
                const formData = new FormData();
                formData.append('tracking_number', reviewState.tracking);
                formData.append('department_id', reviewState.from);
                formData.append('action', 'out');
                formData.append('next_department_id', reviewDeptSelect.value);
                formData.append('remarks', document.getElementById('reviewRemarksInput').value || '');
                formData.append('scanned_at', new Date().toISOString());
                formData.append('offline_uuid', crypto.randomUUID());
                const files = document.getElementById('reviewAttachment').files;
                if (files?.length === 1) {
                    formData.append('attachment', files[0]);
                } else if (files?.length > 1) {
                    Array.from(files).forEach(file => formData.append('attachments[]', file));
                }
                try {
                    const res = await fetch(scanUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: formData });
                    const data = await res.json();
                    reviewResult.classList.remove('hidden');
                    if (res.ok) {
                        reviewResult.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
                        reviewResult.textContent = data.message || 'Sent successfully.';
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        reviewResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                        reviewResult.textContent = data.message || 'Failed.';
                        reviewSendBtn.disabled = false;
                        reviewSendBtn.textContent = 'Send onward';
                    }
                } catch (e) {
                    reviewResult.classList.remove('hidden');
                    reviewResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                    reviewResult.textContent = 'Network error.';
                    reviewSendBtn.disabled = false;
                    reviewSendBtn.textContent = 'Send onward';
                }
            });

            reviewCompleteBtn?.addEventListener('click', async function () {
                reviewCompleteBtn.disabled = true;
                const { ok, data } = await markComplete(reviewState.tracking);
                reviewResult.classList.remove('hidden');
                if (ok) {
                    reviewResult.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
                    reviewResult.textContent = data.message || 'Document completed.';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    reviewResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                    reviewResult.textContent = data.message || 'Failed.';
                    reviewCompleteBtn.disabled = false;
                }
            });

            document.querySelectorAll('.js-complete-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    completeTrackingLabel.textContent = this.dataset.tracking;
                    completeResult.classList.add('hidden');
                    completeModal.dataset.tracking = this.dataset.tracking;
                    showModal(completeModal);
                });
            });

            document.getElementById('completeCancelBtn')?.addEventListener('click', () => hideModal(completeModal));
            completeModal?.addEventListener('click', e => { if (e.target === completeModal) hideModal(completeModal); });
            completeSubmitBtn?.addEventListener('click', async function () {
                completeSubmitBtn.disabled = true;
                const { ok, data } = await markComplete(completeModal.dataset.tracking);
                completeResult.classList.remove('hidden');
                if (ok) {
                    completeResult.className = 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800';
                    completeResult.textContent = data.message || 'Done.';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    completeResult.className = 'mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-800';
                    completeResult.textContent = data.message || 'Failed.';
                    completeSubmitBtn.disabled = false;
                }
            });

            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') { hideModal(reviewModal); hideModal(completeModal); }
            });
        })();
    </script>

</x-app-layout>
