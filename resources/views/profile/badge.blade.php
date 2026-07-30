<x-app-layout>
    <div class="mx-auto w-full max-w-md py-6">
        @if(session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
        @endif

        {{-- The printable card. Staff scan this when signing an endorsement hop,
             which is what replaced the "confirm your password" prompt. --}}
        <div class="panel p-6 text-center print:border-0 print:shadow-none">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-soft">San Pedro · records office</p>
            <p class="mt-1 text-lg font-extrabold text-green-deep">{{ $badgeUser->name }}</p>
            <p class="text-[13px] text-ink-soft">
                {{ \Illuminate\Support\Str::headline($badgeUser->getRoleNames()->first() ?? 'staff') }}@if($badgeUser->department) · {{ $badgeUser->department->name }}@endif
            </p>

            <img src="data:image/svg+xml;base64,{{ $badgeSvg }}" alt="Staff badge QR code"
                 class="mx-auto mt-4 h-56 w-56" />

            <p class="mt-3 text-[12px] text-ink-soft">
                Scan this code to sign an endorsement decision. Treat it like a signature —
                anyone holding it can sign as you.
            </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <button type="button" onclick="window.print()" class="cr-btn cr-btn-primary">Print badge</button>

            <form method="POST" action="{{ route('profile.badge.regenerate') }}"
                  onsubmit="return confirm('Issue a new badge? The card you printed before will stop working.')">
                @csrf
                <button type="submit" class="cr-btn cr-btn-sm">Badge lost — issue a new one</button>
            </form>
        </div>
    </div>
</x-app-layout>
