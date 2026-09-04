@section('title', 'Forgot Password')

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
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your email and we'll send you a link to reset your password</p>
        </div>

        {{-- Session Status --}}
        @if (session('status'))
            <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                <i class="ri-checkbox-circle-fill me-2 text-success"></i>
                <div>{{ session('status') }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="auth-form" id="forgotPasswordForm">
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

            <div class="form-group mb-0">
                <button type="submit" class="btn submit-button auth-submit-btn" id="resetSubmitBtn">
                    <span>Email Password Reset Link</span>
                    <i class="ri-mail-send-line ms-1"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="auth-page-footer">
        <p>&copy; {{ date('Y') }} Entrepreneurs Automation. All rights reserved.</p>
    </div>
</x-guest-layout>
