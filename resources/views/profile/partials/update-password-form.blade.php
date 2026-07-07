<section>
    <header class="text-center">
        <h2 class="update_info_title">
            {{ __('Update Password') }}
        </h2>

        <p class="update_info_subtitle">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <div class="card table-card pb-5">
        <div class="card-body custom-form">
            <form method="post" action="{{ route('password.update') }}" class="row g-3 mt-0">
                @csrf
                @method('put')
                <div class="col-12">
                    <div>
                        <x-input-label for="update_password_current_password" class="form-label custom-label" :value="__('Current Password')" />
                        <x-text-input id="update_password_current_password" class="form-control custom-input" name="current_password" type="password" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password" class="form-label custom-label" :value="__('New Password')" />
                        <x-text-input id="update_password_password" class="form-control custom-input" name="password" type="password" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password_confirmation" class="form-label custom-label" :value="__('Confirm Password')" />
                        <x-text-input id="update_password_password_confirmation" class="form-control custom-input" name="password_confirmation" type="password" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="mt-5">
                        <x-primary-button class="btn submit-button">{{ __('Update') }}</x-primary-button>
                
                            @if (session('status') === 'password-updated')
                                <p
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-transition
                                    x-init="setTimeout(() => show = false, 2000)"
                                    class="text-sm text-gray-600"
                                >{{ __('Saved.') }}</p>
                            @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
