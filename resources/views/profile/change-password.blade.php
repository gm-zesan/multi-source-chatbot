@extends('admin.app')

@php
    $isPasswordSet = Auth::user()->password_set ?? true;
@endphp

@section('title')
    {{ $isPasswordSet ? 'Change Password' : 'Set Password' }}
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8 col-12 mx-auto">
                <div class="card table-card">
                    <div class="card-header table-header">
                        <div class="title-with-breadcrumb">
                            <div class="table-title">
                                {{ $isPasswordSet ? 'Change Password' : 'Set Account Password' }}
                            </div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('dashboard') }}">Dashboard</a>
                                    </li>
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('profile.edit') }}">Profile</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        {{ $isPasswordSet ? 'Change Password' : 'Set Password' }}
                                    </li>
                                </ol>
                            </nav>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="add-new">
                            Edit Profile <i class="ri-user-settings-line ms-1"></i>
                        </a>
                    </div>

                    <div class="card-body custom-form">
                        @if (session('status') === 'password-updated' || session('status') === 'password-set' || session('success'))
                            <div class="alert alert-success d-flex align-items-center mb-4 py-2 px-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                                <i class="ri-checkbox-circle-fill me-2 text-success"></i>
                                <div>
                                    {{ session('status') === 'password-set' ? 'Password has been set successfully.' : 'Password has been updated successfully.' }}
                                </div>
                            </div>
                        @endif

                        @if (!$isPasswordSet)
                            <div class="alert alert-info d-flex align-items-center mb-4 py-2 px-3 border-0" role="alert" style="background-color: #eff6ff; color: #1e40af; border-radius: 6px; font-size: 13px;">
                                <i class="ri-information-fill me-2 text-primary"></i>
                                <div>You signed in using a social account (Google/Facebook). Set a password below to enable standard email and password sign-in.</div>
                            </div>
                        @endif

                        <form method="post" action="{{ route('password.update') }}" id="passwordForm">
                            @csrf
                            @method('put')

                            @if ($isPasswordSet)
                                <!-- Current Password -->
                                <div class="mb-3">
                                    <label for="current_password" class="form-label custom-label">Current Password <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input 
                                            type="password" 
                                            class="form-control custom-input pe-5 @error('current_password', 'updatePassword') is-invalid @enderror" 
                                            name="current_password" 
                                            id="current_password" 
                                            placeholder="••••••••" 
                                            autocomplete="current-password"
                                        >
                                        <button 
                                            type="button" 
                                            class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted px-3" 
                                            onclick="togglePasswordVisibility('current_password', this)" 
                                            tabindex="-1" 
                                            style="box-shadow: none !important; outline: none !important; background: transparent;"
                                        >
                                            <i class="ri-eye-line"></i>
                                        </button>
                                    </div>
                                    @error('current_password', 'updatePassword')
                                        <div class="error_msg">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label custom-label">{{ $isPasswordSet ? 'New Password' : 'Password' }} <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input 
                                        type="password" 
                                        class="form-control custom-input pe-5 @error('password', 'updatePassword') is-invalid @enderror" 
                                        name="password" 
                                        id="password" 
                                        placeholder="••••••••" 
                                        autocomplete="new-password"
                                    >
                                    <button 
                                        type="button" 
                                        class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted px-3" 
                                        onclick="togglePasswordVisibility('password', this)" 
                                        tabindex="-1" 
                                        style="box-shadow: none !important; outline: none !important; background: transparent;"
                                    >
                                        <i class="ri-eye-line"></i>
                                    </button>
                                </div>
                                @error('password', 'updatePassword')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label custom-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input 
                                        type="password" 
                                        class="form-control custom-input pe-5 @error('password_confirmation', 'updatePassword') is-invalid @enderror" 
                                        name="password_confirmation" 
                                        id="password_confirmation" 
                                        placeholder="••••••••" 
                                        autocomplete="new-password"
                                    >
                                    <button 
                                        type="button" 
                                        class="btn border-0 position-absolute end-0 top-50 translate-middle-y text-muted px-3" 
                                        onclick="togglePasswordVisibility('password_confirmation', this)" 
                                        tabindex="-1" 
                                        style="box-shadow: none !important; outline: none !important; background: transparent;"
                                    >
                                        <i class="ri-eye-line"></i>
                                    </button>
                                </div>
                                @error('password_confirmation', 'updatePassword')
                                    <div class="error_msg">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions mt-4">
                                <div class="row">
                                    <div class="col-sm-4 col-6">
                                        <button type="submit" class="btn submit-button" id="submitPasswordBtn">
                                            <span>{{ $isPasswordSet ? 'Update Password' : 'Set Password' }}</span>
                                            <span class="ms-1 spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div class="col-sm-4 col-6">
                                        <a href="{{ route('profile.edit') }}" class="btn cancel-button">Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
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