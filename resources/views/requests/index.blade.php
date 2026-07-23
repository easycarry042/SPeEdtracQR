<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold tracking-tight text-emerald-950">Internal Requests</h1>
            @if($canFile)
                <a href="{{ route('requests.create') }}"
                   class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                    + File Request
                </a>
            @endif
        </div>
    </x-slot>

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
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach($tabs as $tab)
                    <button type="button" @click="tab = '{{ $tab['key'] }}'"
                            :class="tab === '{{ $tab['key'] }}' ? 'bg-emerald-700 text-white' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'"
                            class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        {{ $tab['label'] }}
                        <span class="ml-1.5 rounded-full px-2 py-0.5 text-xs"
                              :class="tab === '{{ $tab['key'] }}' ? 'bg-white/20' : 'bg-gray-100'">{{ $tab['list']->count() }}</span>
                    </button>
                @endforeach
            </div>

            <form method="GET" class="flex gap-2">
                <input type="text" name="q" value="{{ $search }}" placeholder="Search tracking # or request…"
                       class="w-56 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-900">Search</button>
                @if($search !== '')
                    <a href="{{ route('requests.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
                @endif
            </form>
        </div>

        @foreach($tabs as $tab)
            <div x-show="tab === '{{ $tab['key'] }}'" @if(!$loop->first) x-cloak @endif>
                <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Request</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">From</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Amount</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Current hop</th>
                                    <th class="px-4 py-3.5 text-left text-xs font-semibold tracking-wider text-gray-500">Status</th>
                                    <th class="px-4 py-3.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse($tab['list'] as $doc)
                                    @php
                                        $current = $doc->requestSteps->firstWhere('status', \App\Models\RequestStep::STATUS_CURRENT);
                                        $stage = $doc->statusEnum();
                                        $aging = $current?->started_at;
                                        $stale = $aging && $aging->diffInHours(now()) >= 48;
                                    @endphp
                                    <tr class="transition hover:bg-gray-50/60">
                                        <td class="px-4 py-3">
                                            <a href="{{ route('requests.show', $doc) }}" class="text-sm font-semibold text-gray-800 hover:text-emerald-700 hover:underline">
                                                {{ $doc->purpose }}
                                            </a>
                                            <p class="mt-0.5 font-mono text-xs text-gray-400">{{ $doc->tracking_number }} · {{ $doc->document_type }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $doc->requestingDepartment?->code ?? '—' }}
                                            <p class="text-xs text-gray-400">{{ $doc->creator?->name }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $doc->amount !== null ? '₱'.number_format((float) $doc->amount, 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($current)
                                                <p class="text-sm font-semibold text-gray-700">{{ $current->department?->name }}</p>
                                                <p class="text-xs {{ $stale ? 'font-semibold text-red-600' : 'text-gray-400' }}">
                                                    {{ $current->action }}
                                                    @if($aging) · {{ $aging->diffForHumans() }} @endif
                                                </p>
                                            @else
                                                <span class="text-sm text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold
                                                {{ match($stage->band()) {
                                                    'green' => 'bg-emerald-100 text-emerald-800',
                                                    'brass' => 'bg-amber-100 text-amber-800',
                                                    'warn' => 'bg-red-100 text-red-700',
                                                    'hold' => 'bg-amber-100 text-amber-700',
                                                    default => 'bg-gray-100 text-gray-600',
                                                } }}">
                                                {{ $stage->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('requests.show', $doc) }}"
                                               class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                                                Open
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">
                                            @if($tab['key'] === 'awaiting' && $department)
                                                Nothing is awaiting {{ $department->name }} right now.
                                            @elseif($tab['key'] === 'filed')
                                                Your office has no active requests in transit.
                                            @else
                                                Nothing here yet.
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
