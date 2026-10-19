<x-guest-layout>
    <x-slot name="title">Login</x-slot>

    <div class="auth-shell">
        <a href="{{ route('welcome') }}" class="auth-home-link">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6-6-6 6 6 6"/>
            </svg>
            Back to Homepage
        </a>

        <section class="auth-card">
            {{-- Brand half --}}
            <div class="auth-left">
                {{-- The two municipal seals, as on the staff page bar. --}}
                <div class="auth-seals">
                    <img src="{{ asset('images/staff/seal-1.png') }}" alt="City of San Pedro seal">
                    <img src="{{ asset('images/staff/seal-2.png') }}" alt="Tourism Office seal">
                </div>

                {{-- The mark, large and centred as before. --}}
                <div class="auth-brand">
                    {{-- The white mark, darting forward with speed streaks trailing
                         behind it. Decorative motion only; stills for reduced motion. --}}
                    <span class="auth-logo-stage">
                        <span class="auth-trail" aria-hidden="true">
                            <i style="--t:6%; --w:56%; --c:#d4ea6b; --d:1.5s; --l:0s;"></i>
                            <i style="--t:17%; --w:83%; --c:#4fbf6a; --d:1.9s; --l:0.35s;"></i>
                            <i style="--t:27%; --w:40%; --c:#7ddc5a; --d:1.3s; --l:0.8s;"></i>
                            <i style="--t:37%; --w:45%; --c:#9fd18a; --d:1.7s; --l:0.2s;"></i>
                            <i style="--t:48%; --w:86%; --c:#d4ea6b; --d:2.0s; --l:0.6s;"></i>
                            <i style="--t:60%; --w:35%; --c:#2f9e4f; --d:1.4s; --l:0.1s;"></i>
                            <i style="--t:71%; --w:64%; --c:#5fc36f; --d:1.8s; --l:0.9s;"></i>
                            <i style="--t:83%; --w:40%; --c:#7ddc5a; --d:1.6s; --l:0.45s;"></i>
                            <i style="--t:94%; --w:75%; --c:#8fd46e; --d:2.1s; --l:0.25s;"></i>
                        </span>
                        <span class="auth-logo-fly">
                            <img src="{{ asset('images/staff/logo-white.png') }}" alt="SPeED TraQR Logo" class="auth-logo">
                        </span>
                    </span>
                    <h1 class="brand-title">SPeED <span>TraQR</span></h1>
                    <p class="brand-subtitle">Secure document tracking and QR verification.</p>
                </div>

                <p class="auth-foot">City Government of San Pedro, Laguna</p>
            </div>

            {{-- Form half. Two jobs on one card: staff sign in, and — for anyone,
                 no account needed — tracking an internal request by its QR or
                 number (the client's request). --}}
            <div class="auth-right" x-data="{ tab: @js(request('tab') === 'track' ? 'track' : 'signin') }">
                <div class="auth-tabs" role="tablist" aria-label="What would you like to do?">
                    <button type="button" role="tab" :aria-selected="tab === 'signin'" :class="tab === 'signin' && 'on'" @click="tab = 'signin'">Staff sign in</button>
                    <button type="button" role="tab" :aria-selected="tab === 'track'" :class="tab === 'track' && 'on'" @click="tab = 'track'">Track a request</button>
                </div>

                <div x-show="tab === 'track'" x-cloak>
                    @include('requests.partials.public-track-form', ['heading' => true])
                </div>

                <div x-show="tab === 'signin'">
                <h2 class="auth-heading">Welcome</h2>
                <p class="auth-subheading">Input your credentials to continue</p>

                <x-auth-session-status class="auth-status" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <div class="field-shell">
                            <span class="field-icon" aria-hidden="true">
                                <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6"/></svg>
                            </span>
                            <input id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="{{ __('Enter your email') }}"
                                class="form-input form-input-with-icon" />
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="form-error" />
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <div class="field-shell" x-data="{ show: false }">
                            <span class="field-icon" aria-hidden="true">
                                <svg fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path stroke-linecap="round" d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></svg>
                            </span>
                            <input id="password"
                                :type="show ? 'text' : 'password'"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="{{ __('Enter your password') }}"
                                class="form-input form-input-with-icon form-input-with-toggle" />

                            <x-password-toggle />
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="form-error" />
                    </div>

                    <button type="submit" class="auth-button">
                        Login
                        <svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </form>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
