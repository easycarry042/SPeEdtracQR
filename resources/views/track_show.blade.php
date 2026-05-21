<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->tracking_number }} — SPeED TraQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f1f2f1] antialiased text-gray-900">

    <header class="border-b border-emerald-200/60 bg-[#f1f2f1]/90 backdrop-blur-md" style="box-shadow:0 1px 0 0 rgba(16,101,52,0.08)">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/icon.png') }}" alt="SPeED TraQR" class="h-8 w-8 rounded-xl">
                <span class="text-base font-extrabold tracking-tight text-emerald-950">
                    SPeED <span class="font-bold text-emerald-700">TraQR</span>
                </span>
            </a>
            @auth
                <a href="{{ url('/dashboard') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">Dashboard →</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-6 py-10">

        {{-- Document summary card --}}
        <div class="overflow-hidden rounded-2xl border border-emerald-200/80 bg-white shadow-md shadow-emerald-900/8">
            <div class="border-b border-emerald-100 bg-emerald-50/60 px-6 py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-700">Tracking Number</p>
                        <p class="mt-1 font-mono text-2xl font-extrabold tracking-tight text-emerald-950">{{ $document->tracking_number }}</p>
                    </div>
                    @php
                        $statusMap = [
                            'completed'  => ['label' => 'Completed',  'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
                            'in_transit' => ['label' => 'In Transit', 'class' => 'bg-amber-100 text-amber-800 ring-amber-200'],
                            'pending'    => ['label' => 'Pending',    'class' => 'bg-gray-100 text-gray-700 ring-gray-200'],
                        ];
                        $badge = $statusMap[$document->status] ?? ['label' => ucfirst($document->status), 'class' => 'bg-gray-100 text-gray-700 ring-gray-200'];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold ring-1 {{ $badge['class'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $document->status === 'completed' ? 'bg-emerald-500' : ($document->status === 'in_transit' ? 'bg-amber-500' : 'bg-gray-400') }}"></span>
                        {{ $badge['label'] }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Document Type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->document_type }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Submitted By</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->citizen_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Current Department</p>
                    <p class="mt-1 text-sm font-semibold text-emerald-800">{{ $document->currentDepartment->name ?? 'Not yet assigned' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Date Submitted</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800">{{ $document->created_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Timeline --}}
        <div class="mt-8">
            <h2 class="mb-5 text-lg font-bold text-emerald-950">Movement History</h2>

            @if($document->scans->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center text-sm text-gray-400">
                    No scan records yet. Check back after your document has been received.
                </div>
            @else
                <ol class="relative">
                    @foreach($document->scans as $i => $scan)
                        @php
                            $isLast  = $loop->last;
                            $isIn    = $scan->action === 'in';
                            $dotColor   = $isIn ? 'bg-emerald-500' : 'bg-rose-400';
                            $lineColor  = $isLast ? 'border-transparent' : 'border-gray-200';
                        @endphp
                        <li class="relative flex gap-5 pb-8 last:pb-0">
                            {{-- Vertical line --}}
                            @if(!$isLast)
                                <div class="absolute left-[1.125rem] top-8 bottom-0 w-px border-l-2 border-dashed {{ $lineColor }}"></div>
                            @endif

                            {{-- Dot --}}
                            <div class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $isIn ? 'bg-emerald-100' : 'bg-rose-50' }} ring-4 ring-[#f1f2f1]">
                                @if($isIn)
                                    <svg class="h-4 w-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                                    </svg>
                                @else
                                    <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M14 7l5 5-5 5M19 12H7"/>
                                    </svg>
                                @endif
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 overflow-hidden rounded-xl border {{ $isIn ? 'border-emerald-100 bg-white' : 'border-rose-100/80 bg-white' }} px-5 py-4 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-bold {{ $isIn ? 'text-emerald-900' : 'text-rose-800' }}">
                                            {{ $isIn ? 'Arrived at' : 'Left' }}
                                            <span class="ml-1">{{ $scan->department->name ?? '—' }}</span>
                                        </p>
                                        @if($scan->remarks)
                                            <p class="mt-1 text-xs italic text-gray-500">"{{ $scan->remarks }}"</p>
                                        @endif
                                    </div>
                                    <time class="shrink-0 rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-500 ring-1 ring-gray-200">
                                        {{ $scan->scanned_at->format('M d, Y · h:i A') }}
                                    </time>
                                </div>
                                <p class="mt-2 text-xs text-gray-400">Scanned by {{ $scan->user->name ?? 'System' }}</p>
                            </div>
                        </li>
                    @endforeach

                    @if($document->status === 'completed')
                        <li class="relative flex gap-5">
                            <div class="relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 ring-4 ring-[#f1f2f1] shadow-md">
                                <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                            </div>
                            <div class="flex flex-1 items-center overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 shadow-sm">
                                <p class="text-sm font-bold text-emerald-800">Document processing completed</p>
                            </div>
                        </li>
                    @endif
                </ol>
            @endif
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('track.search') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                Track another document
            </a>
        </div>
    </main>

</body>
</html>
