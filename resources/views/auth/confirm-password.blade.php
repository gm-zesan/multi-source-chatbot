@section('title', 'Confirm Password')

<x-guest-layout>
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-logo-wrapper">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="Entrepreneurs Automation" class="auth-logo">
                @else
                    <div class="auth-logo-fallback">
                        <i class="ri-shield-keyhole-line"></i>
                    </div>
                @endif
            </div>
            <h1 class="auth-title">Confirm Password</h1>
            <p class="auth-subtitle">Please confirm your password before continuing.</p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
            @csrf

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

            <div class="form-group mb-0">
                <button type="submit" class="btn submit-button auth-submit-btn">
                    <span>Confirm Password</span>
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
            });
        </script>
    @endpush
</x-guest-layout>
