{{-- Endorsement chain timeline. Expects $document with requestSteps.department
     (+ optionally requestSteps.actedBy) and requestingDepartment loaded. --}}
@php use App\Models\RequestStep; @endphp

<ol>
    <li class="flex gap-3">
        <div class="flex flex-col items-center">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white">✓</span>
            <span class="w-px flex-1 bg-emerald-200"></span>
        </div>
        <div class="pb-5">
            <p class="text-sm font-semibold text-gray-800">{{ $document->requestingDepartment?->name }}</p>
            <p class="text-xs text-gray-500">Filed the request · {{ $document->created_at->format('M j, Y g:i A') }}</p>
        </div>
    </li>
    @foreach($document->requestSteps as $step)
        @php
            $isCurrent = $step->status === RequestStep::STATUS_CURRENT;
            $isApproved = $step->status === RequestStep::STATUS_APPROVED;
            $isHalted = in_array($step->status, [RequestStep::STATUS_DENIED, RequestStep::STATUS_RETURNED], true);
        @endphp
        <li class="flex gap-3">
            <div class="flex flex-col items-center">
                @if($isCurrent)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-white ring-4 ring-amber-100">{{ $loop->iteration }}</span>
                @elseif($isApproved)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white">✓</span>
                @elseif($isHalted)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">✕</span>
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-400 ring-2 ring-gray-200">{{ $loop->iteration }}</span>
                @endif
                @unless($loop->last)
                    <span class="w-px flex-1 {{ $isApproved ? 'bg-emerald-200' : 'bg-gray-200' }}"></span>
                @endunless
            </div>
            <div class="pb-5">
                <p class="text-sm font-semibold {{ $isCurrent ? 'text-amber-800' : ($isHalted ? 'text-red-700' : 'text-gray-800') }}">
                    {{ $step->department?->name }}
                    <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-500">{{ $step->department?->code }}</span>
                </p>
                <p class="text-xs text-gray-500">{{ $step->action }}</p>

                @if($isCurrent)
                    <p class="mt-0.5 text-xs font-semibold text-amber-700">
                        Awaiting this office
                        @if($step->started_at)
                            · here since {{ $step->started_at->diffForHumans() }}
                        @endif
                    </p>
                @elseif($step->acted_at)
                    <p class="mt-0.5 text-xs text-gray-500">
                        @if($isApproved) Approved @elseif($step->status === RequestStep::STATUS_DENIED) Denied @else Returned @endif
                        by <span class="font-semibold text-gray-700">{{ $step->actedBy?->name ?? '—' }}</span>
                        · {{ $step->acted_at->format('M j, Y g:i A') }}
                    </p>
                @endif

                @if($step->remarks)
                    <p class="mt-1 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs italic text-gray-600">“{{ $step->remarks }}”</p>
                @endif

                @if($step->signature_path)
                    <img src="{{ route('requests.steps.signature', $step) }}" alt="Signature of {{ $step->actedBy?->name }}"
                         class="mt-1.5 h-12 rounded border border-gray-200 bg-white px-2 py-1">
                @endif
            </div>
        </li>
    @endforeach
</ol>
