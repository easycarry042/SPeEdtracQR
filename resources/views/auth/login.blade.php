<x-guest-layout>
    <x-slot name="title">Login</x-slot>

    <section class="auth-card glass-panel">
        <div class="auth-grid">
            <div class="auth-left">
                <div class="auth-brand">
                    <img src="{{ asset('images/logo.png') }}" alt="SPeED TraQR Logo" class="auth-logo">
                    <h1 class="brand-title">SPeED <span>TraQR</span></h1>
                    <p class="brand-subtitle">Secure document tracking and QR verification.</p>
                </div>
            </div>

            <div class="auth-right">
                <h2 class="auth-heading">Welcome</h2>
                <p class="auth-subheading">Input your credentials to continue</p>

                <x-auth-session-status class="brand-subtitle" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <div class="field-shell">
                            <span class="field-icon" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7l8.5 6 8.5-6"/>
                                </svg>
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
                        <x-input-error :messages="$errors->get('email')" class="brand-subtitle" />
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <div class="field-shell" x-data="{ show: false }">
                            <span class="field-icon" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="9"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.5a1.6 1.6 0 00-.8 3v2a.8.8 0 001.6 0v-2a1.6 1.6 0 00-.8-3z" fill="currentColor" stroke="none"/>
                                </svg>
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
                        <x-input-error :messages="$errors->get('password')" class="brand-subtitle" />
                    </div>

                    <button type="submit" class="auth-button auth-button-spaced">
                        Login
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- The reveal toggle is Alpine-driven now (see x-password-toggle), so no
         script is needed here. --}}
</x-guest-layout>
