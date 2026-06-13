<x-guest-layout>
    <section class="auth-card">
        <div class="auth-grid">
            <div class="auth-left">
                <div class="auth-brand">
                    <img src="{{ asset('images/logo.png') }}" alt="SPeED TraQR Logo" class="auth-logo">
                    <h1 class="brand-title">SPeED <span>TraQR</span></h1>
                    <p class="brand-subtitle">Secure document tracking and QR verification.</p>
                </div>
            </div>

            <div class="auth-right">
                <h2 class="auth-heading">WELCOME</h2>
                <p class="auth-subheading">Login to start a session</p>

                <x-auth-session-status class="brand-subtitle" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">{{ __('EMAIL') }}</label>
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
                        <label for="password" class="form-label">{{ __('PASSWORD') }}</label>
                        <div>
                            <input id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="form-input" />
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="brand-subtitle" />
                    </div>

                    <div class="auth-row">
                        <label for="show_password" class="checkbox-wrap">
                            <input id="show_password" type="checkbox" class="checkbox">
                            <span class="pass-toggle">Show Password</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="auth-link" href="{{ route('password.request') }}">
                                Reset Password
                            </a>
                        @endif
                    </div>

                    <button type="submit" class="auth-button">
                        Login
                    </button>

                    {{-- Divider --}}
                    <div class="auth-divider">
                        <span>or</span>
                    </div>

                    {{-- Citizen access — no account required --}}
                    <a href="{{ route('citizen.dashboard') }}" class="citizen-button">
                        Continue as Guest (Citizen Access)
                    </a>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('show_password');
            const passwordInput = document.getElementById('password');

            if (!toggle || !passwordInput) {
                return;
            }

            toggle.addEventListener('change', function () {
                passwordInput.type = this.checked ? 'text' : 'password';
            });
        });
    </script>
</x-guest-layout>
