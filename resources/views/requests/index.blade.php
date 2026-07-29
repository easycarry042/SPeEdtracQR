<x-app-layout>
    {{-- The layout's title bar already says "Internal Requests", so only the
         action moves up here rather than repeating the heading beneath it. --}}
    @if($canFile)
        <x-slot name="pageActions">
            <a href="{{ route('requests.create') }}" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                File Request
            </a>
        </x-slot>
    @endif

    @php
        $tabs = $department
            ? [
                ['key' => 'awaiting', 'label' => 'Awaiting my office', 'list' => $awaiting],
                ['key' => 'filed', 'label' => 'Filed by my office', 'list' => $filed],
                ['key' => 'closed', 'label' => 'Closed', 'list' => $closed],
            ]
            : [
                ['key' => 'awaiting', 'label' => 'Active', 'list' => $awaiting],
                ['key' => 'closed', 'label' => 'Closed', 'list' => $closed],
            ];
    @endphp

    <div class="page-shell page-shell-loose" x-data="{ tab: 'awaiting' }">

        @if(session('status'))
            <div class="panel"><div class="pb text-[13px] font-medium text-green-deep">{{ session('status') }}</div></div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="segchips" role="tablist" aria-label="Request queues">
                @foreach($tabs as $tab)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $tab['key'] }}'"
                            @click="tab = '{{ $tab['key'] }}'" :class="tab === '{{ $tab['key'] }}' ? 'on' : ''">
                        {{ $tab['label'] }}
                        <span class="ml-1.5 rounded-full bg-white/70 px-1.5 text-[11px] font-semibold text-ink-soft">{{ $tab['list']->count() }}</span>
                    </button>
                @endforeach
            </div>

            <form method="GET" class="flex items-center gap-2">
                <div class="field">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m21 21-4.3-4.3"/></svg>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search tracking # or request…" aria-label="Search internal requests" class="w-52">
                </div>
                <button type="submit" class="cr-btn cr-btn-primary">Search</button>
                @if($search !== '')
                    <a href="{{ route('requests.index') }}" class="cr-btn">Clear</a>
                @endif
            </form>
        </div>

        @foreach($tabs as $tab)
            <div x-show="tab === '{{ $tab['key'] }}'" @if(!$loop->first) x-cloak @endif>
                <div class="panel">
                    <div class="table-wrap">
                        <table class="reg">
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>From</th>
                                    <th>Current stage</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tab['list'] as $doc)
                                    @php
                                        $current = $doc->requestSteps->firstWhere('status', \App\Models\RequestStep::STATUS_CURRENT);
                                        $stage = $doc->statusEnum();
                                        $aging = $current?->started_at;
                                        $stale = $aging && $aging->diffInHours(now()) >= 48;
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('requests.show', $doc) }}" class="nm text-ink hover:text-green hover:underline">{{ $doc->purpose }}</a>
                                            <p class="mt-0.5 font-mono text-[11px] text-ink-soft">{{ $doc->tracking_number }} · {{ $doc->document_type }}</p>
                                        </td>
                                        <td class="muted">
                                            {{ $doc->requestingDepartment?->code ?? '—' }}
                                            <p class="text-[11px] text-ink-soft">{{ $doc->creator?->name }}</p>
                                        </td>
                                        <td>
                                            @if($current)
                                                <p class="text-[13px] font-medium text-ink">{{ $current->department?->name }}</p>
                                                <p class="text-[11px] {{ $stale ? 'font-semibold text-status-red' : 'text-ink-soft' }}">
                                                    {{ $current->action }}
                                                    @if($aging) · {{ $aging->diffForHumans() }} @endif
                                                </p>
                                            @else
                                                <span class="muted text-[13px]">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="pill {{ match($doc->internalStatusBand()) {
                                                'green' => 'p-green',
                                                'red' => 'p-red',
                                                'returned' => 'p-orange',
                                                default => 'p-amber',
                                            } }}">{{ $doc->internalStatusLabel() }}</span>
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('requests.show', $doc) }}" class="cr-btn cr-btn-sm">Open</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="p-0">
                                            @if($tab['key'] === 'awaiting' && $department)
                                                <x-empty-state title="Nothing awaiting {{ $department->name }}" icon="inbox">
                                                    Requests routed to your office for action will appear here.
                                                </x-empty-state>
                                            @elseif($tab['key'] === 'filed')
                                                <x-empty-state title="No active requests in transit" icon="document">
                                                    Requests your office has filed will show here until they're closed.
                                                </x-empty-state>
                                            @else
                                                <x-empty-state title="Nothing here yet" icon="inbox">
                                                    Requests your office has closed will be archived here.
                                                </x-empty-state>
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
