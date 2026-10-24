<x-guest-layout>
    <x-slot name="title">Track an internal request</x-slot>

    {{-- The public internal-request tracker (InternalTrackerController). Same
         card, fields and button as the sign-in page it is reached from. Shows
         where a request is and who is handling it — never what it asks for,
         its remarks or its files (App\Support\InternalTracking). --}}
    <div class="auth-shell">
        <a href="{{ route('login', ['tab' => 'track']) }}" class="auth-home-link">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/>
            </svg>
            Back to sign in
        </a>

        <section class="auth-card it-card">
            <div class="auth-right">
                <h1 class="auth-heading">Track an internal request</h1>
                <p class="auth-subheading">Which office has it, and who there is handling it. No account needed.</p>

                @include('requests.partials.public-track-form')

                @if($citizenNumber)
                    <div class="it-note" role="status">
                        <b>{{ $citizenNumber }}</b> is a citizen request.
                        <a href="{{ route('citizen.track', ['tracking' => $citizenNumber]) }}">Track it on the citizen tracking page →</a>
                    </div>
                @elseif($error)
                    <div class="it-note is-error" role="alert">{{ $error }}</div>
                @endif

                @if($result)
                    <div class="it-result" aria-live="polite">
                        <div class="it-result-head">
                            <span class="mono font-bold">{{ $result['tracking'] }}</span>
                            <span class="pill {{ match($result['band']) { 'green' => 'p-green', 'red' => 'p-red', 'returned' => 'p-orange', default => 'p-amber' } }}">{{ $result['status'] }}</span>
                        </div>

                        <dl class="it-facts">
                            <div><dt>With</dt><dd>{{ $result['now']['office'] ?? '—' }}</dd></div>
                            @if($result['shows_names'])
                                <div><dt>Handled by</dt><dd>{{ $result['now']['person'] ?? 'Nobody yet' }}</dd></div>
                            @endif
                            <div class="it-facts-wide"><dt>Note</dt><dd>{{ $result['now']['note'] }}</dd></div>
                            <div><dt>Filed by</dt><dd>{{ $result['filed_by'] ?? '—' }}</dd></div>
                            <div><dt>Filed on</dt><dd>{{ $result['filed_on'] }}</dd></div>
                        </dl>

                        <h2 class="it-subhead">Offices it has been through</h2>
                        <ol class="it-steps">
                            @forelse($result['steps'] as $step)
                                <li @class(['is-current' => $step['current']])>
                                    <span class="it-dot" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-ink">{{ $step['office'] ?? 'Unknown office' }} <span class="mono text-xs text-ink-soft">{{ $step['code'] }}</span></span>
                                        <span class="block text-sm text-ink-soft">{{ $step['state'] }}</span>
                                    </span>
                                    <span class="mono shrink-0 text-xs text-ink-soft">{{ $step['when'] }}</span>
                                </li>
                            @empty
                                <li><span class="text-sm text-ink-soft">Not routed to any office yet.</span></li>
                            @endforelse
                        </ol>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-guest-layout>
