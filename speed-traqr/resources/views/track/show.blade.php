@php
    $statusClass = match($document->status) {
        'completed' => 'bg-green-200 text-green-800',
        'pending' => 'bg-blue-200 text-blue-800',
        'returned' => 'bg-rose-200 text-rose-800',
        default => 'bg-yellow-200 text-yellow-800',
    };
@endphp
<x-app-layout>
    @guest
        {{-- Guests don't get the app navbar (and its page title), so keep a heading for the public tracking page --}}
        <x-slot name="header">
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Track Document</h1>
        </x-slot>
    @endguest

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        @unless($isPublicView)
            {{-- Fixed-height panel; the list scrolls inside it --}}
            <div class="flex flex-col rounded-xl border border-[#e0e0e0] bg-white p-3 lg:h-[calc(100vh-9rem)]">
                <div class="max-h-[520px] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 lg:max-h-none">
                    @foreach($documents as $item)
                        <a href="{{ route('track.show', $item->tracking_number) }}" class="flex items-center justify-between rounded-lg border p-3 {{ $item->tracking_number === $document->tracking_number ? 'border-[#1a5c1a] bg-[#e8f5e9]' : 'border-[#e0e0e0] bg-white hover:bg-[#f4faf4]' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#c8efcc] text-[#1a5c1a]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                </span>
                                <div>
                                    <p class="text-[14px] font-semibold text-[#1a1a1a]">{{ $item->document_type }}</p>
                                    <p class="text-[13px] text-[#666666]">{{ $item->status }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[13px] text-[#666666]">{{ $item->created_at->format('m/d/y') }}</p>
                                <span class="text-xl text-[#666666]">›</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endunless

        {{-- Fixed-height panel; the document details scroll inside it --}}
        <div class="rounded-xl border border-[#e0e0e0] bg-white p-6 lg:h-[calc(100vh-9rem)] lg:overflow-y-auto {{ $isPublicView ? 'lg:col-span-2' : '' }}">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#c8efcc] text-[#1a5c1a]">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-[#1a1a1a]">{{ $document->document_type }}</p>
                        <p class="text-[13px] text-[#666666]">{{ $document->citizen_name ?? 'N/A' }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-[#666666]">Tracking ID:</p>
                    <p class="text-xl font-extrabold text-[#1a5c1a] font-mono">{{ $document->tracking_number }}</p>
                </div>
            </div>

            <div class="mt-5">
                <x-status-badge :status="$document->status" />
            </div>

            @unless($isPublicView)
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('documents.sticker', $document) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                        Print QR sticker
                    </a>
                    <a href="{{ route('scan.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-100">
                        Open scanner
                    </a>
                </div>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-left text-sm text-amber-950">
                    <p class="font-semibold">Recording a handoff between departments</p>
                    <p class="mt-1 text-amber-900/90">Use <strong>Scan</strong> (sidebar): pick the <strong>department</strong> that has the paper, then tap <strong>IN</strong> when it arrives there, or <strong>OUT</strong> when it is sent to the next office. Routing uses your <strong>Routing rules</strong> for that document type.</p>
                </div>
            @endunless

            <div class="mt-8">
                <div class="mb-3 text-[14px] font-bold text-[#1a1a1a]">Department Progress</div>
                <div class="flex items-center gap-2">
                    @forelse($routingSteps as $idx => $step)
                        <div class="flex items-center gap-2">
                            @if($idx === count($routingSteps)-1)
                                <span class="h-5 w-5 rounded-full bg-yellow-500 ring-2 ring-[#1a5c1a]"></span>
                            @else
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#4caf50] text-white text-[10px]">✓</span>
                            @endif
                            @if(!$loop->last)
                                <span class="h-1 w-10 {{ $idx < count($routingSteps)-1 ? 'bg-[#4caf50]' : 'bg-[#d9d9d9]' }}"></span>
                            @endif
                        </div>
                    @empty
                        <span class="text-gray-500">No route movement yet.</span>
                    @endforelse
                </div>
            </div>

            <div class="mt-8">
                <h3 class="text-2xl font-extrabold text-[#1a1a1a]">Logs</h3>
                <div class="mt-3 space-y-2">
                    @foreach($timeline as $log)
                        <div class="flex items-center justify-between border-b border-[#e8f5e9] py-2">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-green-600"></span>
                                <span class="text-[14px] text-[#666666]">{{ $log['event'] }}</span>
                            </div>
                            <span class="text-[13px] font-bold text-[#1a5c1a]">{{ $log['timestamp'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
