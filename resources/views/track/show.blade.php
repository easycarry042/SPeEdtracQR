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

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-status-badge :status="$document->status" />
                @unless($isPublicView)
                    <a href="{{ route('documents.edit', $document) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                        Edit details
                    </a>
                    @if($document->scans->isNotEmpty())
                        <form method="POST" action="{{ route('documents.undo-scan', $document) }}"
                              onsubmit="return confirm('Undo the most recent scan for this document? It will revert to its previous location.')">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                Undo last scan
                            </button>
                        </form>
                    @endif
                @endunless
            </div>

            @unless($isPublicView)
                @if(session('status'))
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                @endif
            @endunless

            @if($document->attachments->isNotEmpty())
                <div class="mt-5">
                    <p class="text-[14px] font-bold text-[#1a1a1a]">Attached Images</p>
                    <x-document-images :document="$document" :limit="12" size="lg" class="mt-2" />
                </div>
            @endif

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
                @if($routingChain->isNotEmpty())
                    <x-routing-stepper :document="$document" :chain="$routingChain" />
                @else
                    <span class="text-gray-500 text-sm">No routing path configured.</span>
                @endif
            </div>

            @if($canAct && $document->status !== 'completed')
                <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    @if($isLastStop)
                        <button type="button"
                                class="js-track-complete inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-600"
                                data-tracking="{{ $document->tracking_number }}">
                            Mark as Done
                        </button>
                    @elseif($nextDepartment)
                        <a href="{{ route('movements.index', ['tab' => 'inbox']) }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            Review &amp; send onward
                        </a>
                    @endif
                </div>
            @endif

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

    @unless($isPublicView)
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const base = @json(url('/documents'));
            document.querySelectorAll('.js-track-complete').forEach(btn => {
                btn.addEventListener('click', async function () {
                    if (!confirm('Mark this document as completed?')) return;
                    btn.disabled = true;
                    const res = await fetch(base + '/' + encodeURIComponent(this.dataset.tracking) + '/complete', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    if (res.ok) location.reload();
                    else { alert('Could not complete document.'); btn.disabled = false; }
                });
            });
        })();
    </script>
    @endunless
</x-app-layout>
