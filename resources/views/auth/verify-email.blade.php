@section('title', 'Verify Email')

<x-guest-layout>
    <div class="card auth-card">
        <div class="auth-header">
            <div class="auth-logo-wrapper">
                @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="Entrepreneurs Automation" class="auth-logo">
                @else
                    <div class="auth-logo-fallback">
                        <i class="ri-mail-check-line"></i>
                    </div>
                @endif
            </div>
            <h1 class="auth-title">Verify Your Email</h1>
            <p class="auth-subtitle">Please verify your email address by clicking on the link we sent you.</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                <i class="ri-checkbox-circle-fill me-2 text-success"></i>
                <div>A new verification link has been sent to your email address.</div>
            </div>
        @endif

        <div class="d-flex flex-column gap-2 mt-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn submit-button auth-submit-btn w-100">
                    <span>Resend Verification Email</span>
                    <i class="ri-mail-send-line ms-1"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center mt-2">
                @csrf
                <button type="submit" class="btn btn-link auth-link small">
                    Log Out
                </button>
            </form>
        </div>
    </div>

    <div class="auth-page-footer">
        <p>&copy; {{ date('Y') }} Entrepreneurs Automation. All rights reserved.</p>
    </div>
</x-guest-layout>
