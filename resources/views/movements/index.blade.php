<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-5" id="movementsPage">

        @php
            $activeSet = match ($tab) {
                'sent' => $sentDocuments,
                'tracking' => $trackingDocuments,
                default => $inboxDocuments,
            };
            $emptyMessage = match ($tab) {
                'sent' => 'No completed documents yet.',
                'tracking' => 'No other active documents to track right now.',
                default => 'Nothing assigned to you right now. An admin assigns documents for you to advance.',
            };
            $pageParam = match ($tab) {
                'sent' => 'sent_page',
                'tracking' => 'tracking_page',
                default => 'inbox_page',
            };

            $fileSvg  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>';
            $linkSvg  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 15l6-6"/><path d="M10 6.5 12 4.5a3.5 3.5 0 0 1 5 5l-2 2"/><path d="M14 17.5 12 19.5a3.5 3.5 0 0 1-5-5l2-2"/></svg>';
        @endphp

        {{-- ── Heading ─────────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3">
            <h1 class="text-lg font-semibold text-green-deep">Movements</h1>
            <span class="chip">{{ $isOrgWide ? 'All offices' : ($user->department?->name ?? 'Your department') }}</span>
        </div>

        {{-- ── Tabs + filters ──────────────────────────────────────────────── --}}
        <div class="toolbar">
            <div class="segchips">
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'inbox']) }}" @class(['on' => $tab === 'inbox'])>My documents · {{ $inboxDocuments->total() }}</a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'tracking']) }}" @class(['on' => $tab === 'tracking'])>Tracking · {{ $trackingDocuments->total() }}</a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'sent']) }}" @class(['on' => $tab === 'sent'])>Completed · {{ $sentDocuments->total() }}</a>
            </div>
            <div class="spacer"></div>
            <form method="GET" action="{{ url()->current() }}" class="toolbar" style="margin:0;">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if($isOrgWide)
                    <div class="field">
                        <select name="department">
                            <option value="">All departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected(request('department') == $dept->id)>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <label class="field" style="gap:6px;cursor:pointer;">
                    <input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))>
                    <span style="font-size:12px">Overdue only</span>
                </label>
                <button type="submit" class="cr-btn cr-btn-primary cr-btn-sm">Filter</button>
                @if(request()->hasAny(['department', 'overdue']))
                    <a href="{{ route('movements.index', ['tab' => $tab]) }}" class="cr-btn cr-btn-sm">Clear</a>
                @endif
            </form>
        </div>

        {{-- ── Queue ───────────────────────────────────────────────────────── --}}
        <div class="mvlist">
            @forelse($activeSet as $document)
                @php
                    if ($document->slaOverdue) {
                        $rowState = 'late'; $timeState = 'late';
                        $statusText = '+' . $document->slaHoursOver . 'h overdue';
                    } elseif ($document->slaPct >= 75) {
                        $rowState = 'warn'; $timeState = 'warn';
                        $statusText = '~' . $document->slaHoursLeft . 'h left';
                    } else {
                        $rowState = ''; $timeState = 'ok';
                        $statusText = $document->slaHoursLeft !== null ? '~' . $document->slaHoursLeft . 'h left' : '';
                    }

                    $reviewImages = $document->attachments
                        ->map(fn ($a) => route('attachments.show', $a))
                        ->filter()
                        ->values();
                @endphp

                <div @class(['mvrow', $rowState])>
                    <div class="head">
                        <div class="who2">
                            <span class="docico">{!! $fileSvg !!}</span>
                            <div>
                                <div class="ty">{{ $document->document_type }}</div>
                                <div class="cz">{{ $document->citizen_name ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="meta">
                            <span class="code">{{ $document->tracking_number }}</span>
                            @if($statusText)
                                <span class="time {{ $timeState }}">{{ $statusText }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Manual status progress; assigned staff get inline controls on their inbox. --}}
                    <x-routing-stepper :document="$document" :controls="$tab === 'inbox'" />

                    @if($reviewImages->isNotEmpty())
                        <div class="thumbs">
                            @foreach($reviewImages->take(3) as $img)
                                <span style="background-image:url('{{ $img }}');background-size:cover;"></span>
                            @endforeach
                        </div>
                    @endif

                    <div class="mvfoot">
                        <a href="{{ url('/track/'.$document->tracking_number) }}" class="link" target="_blank" rel="noopener">{!! $linkSvg !!}Public link</a>
                        <span style="font-size:12px;color:var(--ink-soft);">
                            @if($document->assigned_to)
                                Assigned to {{ $document->assignedTo->name ?? '—' }}
                            @else
                                Unassigned
                            @endif
                        </span>
                    </div>
                </div>
            @empty
                <div class="panel" style="padding:30px;text-align:center;color:var(--ink-soft);">
                    {{ $emptyMessage }}
                </div>
            @endforelse
        </div>

        @if($activeSet->hasPages())
            <div class="pt-2">
                {{ $activeSet->appends(request()->except($pageParam))->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
