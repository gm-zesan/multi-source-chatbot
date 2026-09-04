@section('title', 'Register')

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
                        <i class="ri-user-add-line"></i>
                    </div>
                @endif
            </div>
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Register a new account for the platform</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf

            <!-- Name -->
            <div class="form-group">
                <label for="name" class="custom-label">Full Name <span class="text-danger">*</span></label>
                <div class="auth-input-group">
                    <span class="input-icon">
                        <i class="ri-user-3-line"></i>
                    </span>
                    <input 
                        id="name" 
                        type="text" 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        autofocus 
                        autocomplete="name" 
                        placeholder="Name" 
                        class="form-control custom-input @error('name') is-invalid @enderror"
                    >
                </div>
                @error('name')
                    <div class="error_msg">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

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
                <label for="password_confirmation" class="custom-label">Confirm Password <span class="text-danger">*</span></label>
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

            <div class="form-group mb-0 mt-3">
                <button type="submit" class="btn submit-button auth-submit-btn">
                    <span>Register</span>
                    <i class="ri-user-add-line ms-1"></i>
                </button>
            </div>

            <div class="text-center mt-3">
                <span class="text-muted small">Already have an account?</span>
                <a href="{{ route('login') }}" class="auth-link small ms-1">Sign In</a>
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
