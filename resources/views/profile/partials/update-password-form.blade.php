@php
    $isPasswordSet = Auth::user()->password_set ?? true;
@endphp

<section>
    <header class="text-center">
        <h2 class="update_info_title">
            {{ $isPasswordSet ? __('Update Password') : __('Set Account Password') }}
        </h2>

        <p class="update_info_subtitle">
            @if ($isPasswordSet)
                {{ __('Ensure your account is using a strong password to stay secure.') }}
            @else
                {{ __('You signed in with a social account. Set a password below to enable email & password sign-in.') }}
            @endif
        </p>
    </header>

    <div class="card table-card pb-5">
        <div class="card-body custom-form">
            <form method="post" action="{{ route('password.update') }}" class="row g-3 mt-0">
                @csrf
                @method('put')
                <div class="col-12">
                    @if ($isPasswordSet)
                        <div class="mb-3">
                            <label for="update_password_current_password" class="form-label custom-label">{{ __('Current Password') }} <span class="text-danger">*</span></label>
                            <input id="update_password_current_password" class="form-control custom-input @error('current_password', 'updatePassword') is-invalid @enderror" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••" />
                            @error('current_password', 'updatePassword')
                                <div class="error_msg">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="update_password_password" class="form-label custom-label">{{ $isPasswordSet ? __('New Password') : __('Password') }} <span class="text-danger">*</span></label>
                        <input id="update_password_password" class="form-control custom-input @error('password', 'updatePassword') is-invalid @enderror" name="password" type="password" autocomplete="new-password" placeholder="••••••••" />
                        @error('password', 'updatePassword')
                            <div class="error_msg">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="update_password_password_confirmation" class="form-label custom-label">{{ __('Confirm Password') }} <span class="text-danger">*</span></label>
                        <input id="update_password_password_confirmation" class="form-control custom-input @error('password_confirmation', 'updatePassword') is-invalid @enderror" name="password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••" />
                        @error('password_confirmation', 'updatePassword')
                            <div class="error_msg">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn submit-button w-100">
                            {{ $isPasswordSet ? __('Update Password') : __('Set Password') }}
                        </button>
                
                        @if (session('status') === 'password-updated' || session('status') === 'password-set')
                            <div class="alert alert-success mt-3 py-2 px-3 border-0" role="alert" style="background-color: #ecfdf5; color: #065f46; border-radius: 6px; font-size: 13px;">
                                <i class="ri-checkbox-circle-fill me-1 text-success"></i>
                                {{ session('status') === 'password-set' ? __('Password has been set successfully!') : __('Password updated successfully!') }}
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
