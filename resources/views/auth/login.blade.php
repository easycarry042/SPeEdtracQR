<x-guest-layout>
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
                <p class="auth-subheading">Login to start a session</p>

                <x-auth-session-status class="brand-subtitle" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <div>
                            <input id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="form-input " />
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="brand-subtitle" />
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">{{ __('Password') }}</label>
                        <div class="relative" x-data="{ show: false }">
                            <input id="password"
                                :type="show ? 'text' : 'password'"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="form-input form-input-with-toggle" />

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
