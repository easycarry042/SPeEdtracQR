<x-guest-layout>
    <section class="auth-card">
        <div class="auth-grid">
            <div class="auth-left">
                <div class="auth-brand">
                    <div class="brand-badge"></div>
                    <h1 class="brand-title">SPeED <span>TraQR</span></h1>
                    <p class="brand-subtitle">Create your account to access SPeED TraQR.</p>
                </div>
            </div>

            <div class="auth-right">
                <h2 class="auth-heading">REGISTER</h2>
                <p class="auth-subheading">Create your account</p>

                <form method="POST" action="{{ route('register') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="name" class="form-label">{{ __('FULL NAME') }}</label>
                        <div>
                            <input id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                autofocus
                                autocomplete="name"
                                class="form-input" />
                        </div>
                        <x-input-error :messages="$errors->get('name')" class="brand-subtitle" />
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">{{ __('EMAIL') }}</label>
                        <div>
                            <input id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autocomplete="username"
                                class="form-input" />
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
                                autocomplete="new-password"
                                class="form-input" />
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="brand-subtitle" />
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">{{ __('CONFIRM PASSWORD') }}</label>
                        <div>
                            <input id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="form-input" />
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="brand-subtitle" />
                    </div>

                    <div class="auth-row">
                        <a class="auth-link" href="{{ route('login') }}">
                            Already have an account?
                        </a>

                        <button type="submit" class="auth-button" style="width:auto;padding:14px 24px;">
                            Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</x-guest-layout>
