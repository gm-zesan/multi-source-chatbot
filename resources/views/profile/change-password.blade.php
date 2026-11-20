@extends('admin.app')

@php
    $isPasswordSet = Auth::user()->password_set ?? true;
@endphp

@section('title', $isPasswordSet ? 'Change Password' : 'Set Password')

@section('content')
<div class="space-y-4 max-w-2xl mx-auto">
    <!-- Header / Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-light-border dark:border-dark-border">
        <div>
            <h1 class="text-base font-semibold text-light-text dark:text-dark-text tracking-tight">
                {{ $isPasswordSet ? 'Change Password' : 'Set Account Password' }}
            </h1>
            <p class="text-xs text-light-muted dark:text-dark-muted">Ensure your account is protected with a strong, distinct password</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">
            <i class="ri-user-settings-line text-sm"></i>
            <span>Edit Profile</span>
        </a>
    </div>

    <!-- Alert Notifications -->
    @if (session('status') === 'password-updated' || session('status') === 'password-set' || session('success'))
        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-md text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
            <i class="ri-checkbox-circle-line text-sm"></i>
            <span>{{ session('status') === 'password-set' ? 'Password has been set successfully.' : 'Password has been updated successfully.' }}</span>
        </div>
    @endif

    @if (!$isPasswordSet)
        <div class="p-3 bg-sky-500/10 border border-sky-500/20 rounded-md text-sky-600 dark:text-sky-400 text-xs flex items-center gap-2">
            <i class="ri-information-line text-sm"></i>
            <span>You signed in using a social account (Google/Facebook). Set a password below to enable standard email and password sign-in.</span>
        </div>
    @endif

    <div class="app-card">
        <div class="app-card-header">
            <div class="flex items-center gap-2">
                <i class="ri-lock-password-line text-brand-500 text-sm"></i>
                <span class="text-xs font-semibold text-light-text dark:text-dark-text">Security Credentials</span>
            </div>
        </div>
        <div class="app-card-body">
            <form method="post" action="{{ route('password.update') }}" id="passwordForm" class="space-y-4">
                @csrf
                @method('put')

                @if ($isPasswordSet)
                    <!-- Current Password -->
                    <div class="space-y-1">
                        <label for="current_password" class="text-2xs font-semibold text-light-text dark:text-dark-text">Current Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input 
                                type="password" 
                                class="app-input w-full pr-10 @error('current_password', 'updatePassword') border-red-500 @enderror" 
                                name="current_password" 
                                id="current_password" 
                                placeholder="••••••••" 
                                autocomplete="current-password"
                            >
                            <button 
                                type="button" 
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-sm p-1" 
                                onclick="togglePasswordVisibility('current_password', this)" 
                                tabindex="-1" 
                            >
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        @error('current_password', 'updatePassword')
                            <div class="text-2xs text-red-500">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <!-- New Password -->
                <div class="space-y-1">
                    <label for="password" class="text-2xs font-semibold text-light-text dark:text-dark-text">{{ $isPasswordSet ? 'New Password' : 'Password' }} <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input 
                            type="password" 
                            class="app-input w-full pr-10 @error('password', 'updatePassword') border-red-500 @enderror" 
                            name="password" 
                            id="password" 
                            placeholder="••••••••" 
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-sm p-1" 
                            onclick="togglePasswordVisibility('password', this)" 
                            tabindex="-1" 
                        >
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    @error('password', 'updatePassword')
                        <div class="text-2xs text-red-500">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="space-y-1">
                    <label for="password_confirmation" class="text-2xs font-semibold text-light-text dark:text-dark-text">Confirm Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input 
                            type="password" 
                            class="app-input w-full pr-10 @error('password_confirmation', 'updatePassword') border-red-500 @enderror" 
                            name="password_confirmation" 
                            id="password_confirmation" 
                            placeholder="••••••••" 
                            autocomplete="new-password"
                        >
                        <button 
                            type="button" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text text-sm p-1" 
                            onclick="togglePasswordVisibility('password_confirmation', this)" 
                            tabindex="-1" 
                        >
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    @error('password_confirmation', 'updatePassword')
                        <div class="text-2xs text-red-500">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Submit Buttons -->
                <div class="pt-3 border-t border-light-border dark:border-dark-border flex items-center justify-end gap-2">
                    <a href="{{ route('profile.edit') }}" class="app-btn app-btn-secondary">Cancel</a>
                    <button type="submit" class="app-btn app-btn-primary" id="submitPasswordBtn">
                        <i class="ri-key-2-line text-xs"></i>
                        <span>{{ $isPasswordSet ? 'Update Password' : 'Set Password' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input && icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('ri-eye-line');
                icon.classList.add('ri-eye-off-line');
            } else {
                input.type = 'password';
                icon.classList.remove('ri-eye-off-line');
                icon.classList.add('ri-eye-line');
            }
        }
    }
</script>
@endpush
