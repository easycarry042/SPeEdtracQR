{{-- Endorsement chain timeline. Expects $document with requestSteps.department
     (+ optionally requestSteps.actedBy) and requestingDepartment loaded. --}}
@php use App\Models\RequestStep; @endphp

<ol class="text-ink">
    <li class="flex gap-3">
        <div class="flex flex-col items-center">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green text-xs font-bold text-white">✓</span>
            <span class="w-px flex-1 bg-green-wash"></span>
        </div>
        <div class="pb-5">
            <p class="text-[13px] font-semibold text-ink">{{ $document->requestingDepartment?->name }}</p>
            <p class="text-[12px] text-ink-soft">Filed the request · {{ $document->created_at->format('M j, Y g:i A') }}</p>
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
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brass text-xs font-bold text-white ring-4 ring-status-amber-wash">{{ $loop->iteration }}</span>
                @elseif($isApproved)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-green text-xs font-bold text-white">✓</span>
                @elseif($isHalted)
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-status-red text-xs font-bold text-white">✕</span>
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#eef2ef] text-xs font-bold text-ink-soft ring-1 ring-hairline-strong">{{ $loop->iteration }}</span>
                @endif
                @unless($loop->last)
                    <span class="w-px flex-1 {{ $isApproved ? 'bg-green-wash' : 'bg-hairline' }}"></span>
                @endunless
            </div>
            <div class="pb-5">
                <p class="text-[13px] font-semibold {{ $isCurrent ? 'text-status-amber' : ($isHalted ? 'text-status-red' : 'text-ink') }}">
                    {{ $step->department?->name }}
                    <span class="ml-1 rounded bg-[#eef2ef] px-1.5 py-0.5 font-mono text-[10px] text-ink-soft">{{ $step->department?->code }}</span>
                </p>
                <p class="text-[12px] text-ink-soft">{{ $step->action }}</p>

                @if($isCurrent)
                    <p class="mt-0.5 text-[12px] font-semibold text-status-amber">
                        Awaiting this office
                        @if($step->started_at)
                            · here since {{ $step->started_at->diffForHumans() }}
                        @endif
                    </p>
                @elseif($step->acted_at)
                    <p class="mt-0.5 text-[12px] text-ink-soft">
                        @if($isApproved) Approved @elseif($step->status === RequestStep::STATUS_DENIED) Denied @else Returned @endif
                        by <span class="font-semibold text-ink">{{ $step->actedBy?->name ?? '—' }}</span>
                        · {{ $step->acted_at->format('M j, Y g:i A') }}
                    </p>
                @endif

                @if($step->remarks)
                    <p class="mt-1 rounded-[8px] bg-[#f6f8f7] px-2.5 py-1.5 text-[12px] italic text-ink">“{{ $step->remarks }}”</p>
                @endif

                @if($step->signature_path)
                    <img src="{{ route('requests.steps.signature', $step) }}"
                         alt="e-signature of {{ $step->actedBy?->name ?? 'the approving supervisor' }}"
                         class="mt-1.5 h-12 rounded-[6px] border border-hairline bg-white px-2 py-1">
                @endif
            </div>
        </li>
    @endforeach
</ol>
