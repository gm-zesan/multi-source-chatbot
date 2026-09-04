@section('title', 'Sign In')

<x-guest-layout>
    <div class="auth-top-nav">
        <a href="{{ route('home') }}" class="auth-back-link">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Home</span>
        </a>
        <span class="text-muted small">Admin Access</span>
    </div>

    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-logo-wrapper">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="Entrepreneurs Automation" class="auth-logo">
                @else
                    <div class="auth-logo-fallback">
                        <i class="ri-shield-user-line"></i>
                    </div>
                @endif
            </div>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your account to access the dashboard</p>
        </div>

        {{-- Session Status Alert --}}
        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                <i class="ri-checkbox-circle-fill me-2 text-success"></i>
                <div>{{ session('status') }}</div>
            </div>
        @endif

        {{-- Flash Error Alert --}}
        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 border-0" role="alert" style="background-color: #fef2f2; color: #991b1b; border-radius: 6px; font-size: 13px;">
                <i class="ri-error-warning-fill me-2 text-danger"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        {{-- General Error Alert if multiple / non-field errors --}}
        @if ($errors->has('email') && !$errors->has('password') && count($errors->all()) === 1)
            {{-- Handled per-field below --}}
        @elseif ($errors->any())
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 border-0" role="alert" style="background-color: #fef2f2; color: #991b1b; border-radius: 6px; font-size: 13px;">
                <i class="ri-error-warning-fill me-2 text-danger"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form" id="loginForm" autocomplete="on">
            @csrf

            <!-- Email Address -->
            <div class="form-group">
                <label for="email" class="custom-label">Email Address <span class="text-danger">*</span></label>
                <div class="auth-input-group">
                    <span class="input-icon">
                        <i class="ri-mail-line"></i>
                    </span>
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="Email" 
                        class="form-control custom-input @error('email') is-invalid @enderror"
                    >
                </div>
                @error('email')
                    <div class="error_msg">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password" class="custom-label">Password <span class="text-danger">*</span></label>
                <div class="auth-input-group has-toggle">
                    <span class="input-icon">
                        <i class="ri-lock-2-line"></i>
                    </span>
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="current-password" 
                        placeholder="••••••••" 
                        class="form-control custom-input @error('password') is-invalid @enderror"
                    >
                    <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Show or hide password" tabindex="-1">
                        <i class="ri-eye-line" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="error_msg">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="auth-options">
                <div class="form-check">
                    <input id="remember_me" type="checkbox" class="custom-checkbox" name="remember">
                    <label for="remember_me" class="form-check-label">Remember me</label>
                </div>

                @if (Route::has('password.request'))
                    <a class="auth-link" href="{{ route('password.request') }}">
                        Forgot password?
                    </a>
                @endif
            </div>

            <!-- Submit Button -->
            <div class="form-group mb-0">
                <button type="submit" class="btn submit-button auth-submit-btn" id="loginSubmitBtn">
                    <span id="btnText">Sign In</span>
                    <i class="ri-login-box-line" id="btnIcon"></i>
                    <span class="spinner-border spinner-border-sm d-none" id="btnSpinner" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </form>

        {{-- Social Login Divider --}}
        <div class="social-auth-divider">
            <span>Or continue with</span>
        </div>

        {{-- Social Login Buttons --}}
        <div class="social-auth-grid">
            <a href="{{ route('auth.social.redirect', 'google') }}" class="social-auth-btn" id="googleLoginBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                </svg>
                <span>Google</span>
            </a>

            <a href="{{ route('auth.social.redirect', 'facebook') }}" class="social-auth-btn" id="facebookLoginBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                <span>Facebook</span>
            </a>
        </div>
    </div>

    <div class="auth-page-footer">
        <p>&copy; {{ date('Y') }} Entrepreneurs Automation. All rights reserved.</p>
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
                            toggleIcon.classList.remove('ri-eye-line');
                            toggleIcon.classList.add('ri-eye-off-line');
                        } else {
                            passwordInput.type = 'password';
                            toggleIcon.classList.remove('ri-eye-off-line');
                            toggleIcon.classList.add('ri-eye-line');
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
                            if (btnIcon) btnIcon.classList.add('d-none');
                            if (btnSpinner) btnSpinner.classList.remove('d-none');
                        }
                    });
                }
            });
        </script>
    @endpush
</x-guest-layout>
