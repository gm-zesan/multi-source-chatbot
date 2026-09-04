@section('title', 'Set New Password')

<x-guest-layout>
    <div class="auth-top-nav">
        <a href="{{ route('login') }}" class="auth-back-link">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Sign In</span>
        </a>
    </div>

    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-logo-wrapper">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="Entrepreneurs Automation" class="auth-logo">
                @else
                    <div class="auth-logo-fallback">
                        <i class="ri-lock-password-line"></i>
                    </div>
                @endif
            </div>
            <h1 class="auth-title">Set New Password</h1>
            <p class="auth-subtitle">Please choose a new password for your account</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="auth-form">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

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
                        value="{{ old('email', $request->email) }}" 
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
                <label for="password" class="custom-label">New Password <span class="text-danger">*</span></label>
                <div class="auth-input-group has-toggle">
                    <span class="input-icon">
                        <i class="ri-lock-2-line"></i>
                    </span>
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="new-password" 
                        placeholder="Password" 
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

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="password_confirmation" class="custom-label">Confirm New Password <span class="text-danger">*</span></label>
                <div class="auth-input-group has-toggle">
                    <span class="input-icon">
                        <i class="ri-lock-check-line"></i>
                    </span>
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password" 
                        placeholder="Confirm Password" 
                        class="form-control custom-input @error('password_confirmation') is-invalid @enderror"
                    >
                    <button type="button" class="password-toggle-btn" id="toggleConfirmPassword" aria-label="Show or hide password" tabindex="-1">
                        <i class="ri-eye-line" id="toggleConfirmPasswordIcon"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <div class="error_msg">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            <div class="form-group mb-0">
                <button type="submit" class="btn submit-button auth-submit-btn">
                    <span>Reset Password</span>
                    <i class="ri-check-line ms-1"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="auth-page-footer">
        <p>&copy; {{ date('Y') }} Entrepreneurs Automation. All rights reserved.</p>
    </div>

    @push('custom-scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function setupToggle(btnId, inputId, iconId) {
                    const btn = document.getElementById(btnId);
                    const input = document.getElementById(inputId);
                    const icon = document.getElementById(iconId);
                    if (btn && input && icon) {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (input.type === 'password') {
                                input.type = 'text';
                                icon.classList.remove('ri-eye-line');
                                icon.classList.add('ri-eye-off-line');
                            } else {
                                input.type = 'password';
                                icon.classList.remove('ri-eye-off-line');
                                icon.classList.add('ri-eye-line');
                            }
                        });
                    }
                }

                setupToggle('togglePassword', 'password', 'togglePasswordIcon');
                setupToggle('toggleConfirmPassword', 'password_confirmation', 'toggleConfirmPasswordIcon');
            });
        </script>
    @endpush
</x-guest-layout>
