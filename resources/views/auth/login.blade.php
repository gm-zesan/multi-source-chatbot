@section('title', 'Sign In')

<x-guest-layout>
    <div class="rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-card p-6 sm:p-8 transition-colors duration-200">
        
        {{-- Header & Brand Icon --}}
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 border border-brand-500/20 mb-3 shadow-xs">
                <i class="ri-sparkling-fill text-2xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-light-text dark:text-dark-text">
                Welcome back
            </h1>
            <p class="text-xs sm:text-sm text-light-muted dark:text-dark-muted mt-1">
                Sign in to your enterprise automation console
            </p>
        </div>

        {{-- Status Alert (e.g., password reset link sent) --}}
        @if (session('status'))
            <div class="mb-5 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                <i class="ri-checkbox-circle-fill text-sm flex-shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Flash Error Alert --}}
        @if (session('error'))
            <div class="mb-5 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs flex items-center gap-2">
                <i class="ri-error-warning-fill text-sm flex-shrink-0"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{-- General Validation Alert --}}
        @if ($errors->any() && !($errors->has('email') && !$errors->has('password') && count($errors->all()) === 1))
            <div class="mb-5 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs flex items-center gap-2">
                <i class="ri-error-warning-fill text-sm flex-shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        {{-- Login Form --}}
        <form method="POST" action="{{ route('login') }}" class="space-y-4" id="loginForm" autocomplete="on">
            @csrf

            {{-- Email Address --}}
            <div>
                <label for="email" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                    Email Address <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-light-muted dark:text-dark-muted">
                        <i class="ri-mail-line text-sm"></i>
                    </div>
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="name@company.com" 
                        class="w-full pl-10 pr-3.5 py-2.5 rounded-xl text-xs sm:text-sm bg-light-canvas dark:bg-dark-canvas border @error('email') border-rose-500 focus:border-rose-500 focus:ring-rose-500/20 @else border-light-border dark:border-dark-border focus:border-brand-500 focus:ring-brand-500/20 @enderror text-light-text dark:text-dark-text placeholder:text-light-muted/60 dark:placeholder:text-dark-muted/60 focus:outline-none focus:ring-2 transition-colors duration-150"
                    >
                </div>
                @error('email')
                    <div class="flex items-center gap-1 text-3xs text-rose-500 mt-1.5 font-medium">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-light-text dark:text-dark-text">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-3xs font-medium text-brand-600 dark:text-brand-400 hover:underline">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-light-muted dark:text-dark-muted">
                        <i class="ri-lock-2-line text-sm"></i>
                    </div>
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="current-password" 
                        placeholder="••••••••" 
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl text-xs sm:text-sm bg-light-canvas dark:bg-dark-canvas border @error('password') border-rose-500 focus:border-rose-500 focus:ring-rose-500/20 @else border-light-border dark:border-dark-border focus:border-brand-500 focus:ring-brand-500/20 @enderror text-light-text dark:text-dark-text placeholder:text-light-muted/60 dark:placeholder:text-dark-muted/60 focus:outline-none focus:ring-2 transition-colors duration-150"
                    >
                    <button type="button" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-colors" id="togglePassword" aria-label="Toggle password visibility" tabindex="-1">
                        <i class="ri-eye-line text-sm" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="flex items-center gap-1 text-3xs text-rose-500 mt-1.5 font-medium">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Remember Me --}}
            <div class="flex items-center">
                <input id="remember_me" type="checkbox" name="remember" class="w-4 h-4 rounded border-light-border dark:border-dark-border text-brand-600 focus:ring-brand-500/20 bg-light-canvas dark:bg-dark-canvas cursor-pointer">
                <label for="remember_me" class="ml-2 text-xs text-light-muted dark:text-dark-muted cursor-pointer select-none">
                    Remember this device for 30 days
                </label>
            </div>

            {{-- Submit Button --}}
            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center gap-2 group cursor-pointer" id="loginSubmitBtn">
                    <span id="btnText">Sign In</span>
                    <i class="ri-arrow-right-line text-sm group-hover:translate-x-0.5 transition-transform" id="btnIcon"></i>
                    <i class="ri-loader-4-line animate-spin text-sm hidden" id="btnSpinner"></i>
                </button>
            </div>
        </form>

        {{-- Social Login Divider --}}
        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-light-border dark:border-dark-border"></div>
            </div>
            <div class="relative flex justify-center text-3xs uppercase">
                <span class="bg-light-surface dark:bg-dark-surface px-3 text-light-muted dark:text-dark-muted font-mono tracking-wider">
                    Or continue with
                </span>
            </div>
        </div>

        {{-- Social Login Grid --}}
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('auth.social.redirect', 'google') }}" class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text text-xs font-medium transition-colors" id="googleLoginBtn">
                <svg width="16" height="16" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                </svg>
                <span>Google</span>
            </a>

            <a href="{{ route('auth.social.redirect', 'facebook') }}" class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text text-xs font-medium transition-colors" id="facebookLoginBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span>Facebook</span>
            </a>
        </div>

        {{-- Switch to Register --}}
        <div class="mt-6 pt-5 border-t border-light-border dark:border-dark-border text-center text-xs text-light-muted dark:text-dark-muted">
            Don't have an enterprise account? 
            <a href="{{ route('register') }}" class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                Create an account
            </a>
        </div>
    </div>

    @push('custom-scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Password visibility toggle
                const toggleBtn = document.getElementById('togglePassword');
                const passwordInput = document.getElementById('password');
                const toggleIcon = document.getElementById('togglePasswordIcon');

                if (toggleBtn && passwordInput && toggleIcon) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (passwordInput.type === 'password') {
                            passwordInput.type = 'text';
                            toggleIcon.className = 'ri-eye-off-line text-sm';
                        } else {
                            passwordInput.type = 'password';
                            toggleIcon.className = 'ri-eye-line text-sm';
                        }
                    });
                }

                // Submit button loading state
                const loginForm = document.getElementById('loginForm');
                const submitBtn = document.getElementById('loginSubmitBtn');
                const btnIcon = document.getElementById('btnIcon');
                const btnSpinner = document.getElementById('btnSpinner');

                if (loginForm && submitBtn) {
                    loginForm.addEventListener('submit', function() {
                        if (loginForm.checkValidity()) {
                            submitBtn.disabled = true;
                            if (btnIcon) btnIcon.classList.add('hidden');
                            if (btnSpinner) btnSpinner.classList.remove('hidden');
                        }
                    });
                }
            });
        </script>
    @endpush
</x-guest-layout>
