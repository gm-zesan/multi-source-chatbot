<section>
    <header class="text-center">
        <h2 class="update_info_title">
            {{ __('Profile Information') }}
        </h2>

        <p class="update_info_subtitle">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <div class="card table-card">
        <div class="card-body custom-form">
            <form method="post" action="{{ route('profile.update') }}" class="row g-3 mt-0">
                @csrf
                @method('patch')

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" name="name" class="form-label custom-label" type="text" class="form-control custom-input" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                    <x-input-error class="mt-2 error-messages" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="email" class="form-label custom-label" :value="__('Email')" class="form-label custom-label"/>
                    <x-text-input id="email" name="email" type="email" class="form-control custom-input" :value="old('email', $user->email)" required autocomplete="username" />
                    <x-input-error class="mt-2 error-messages" :messages="$errors->get('email')" />
        
                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                        <div>
                            <p class="text-sm mt-2 text-gray-800">
                                {{ __('Your email address is unverified.') }}
        
                                <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    {{ __('Click here to re-send the verification email.') }}
                                </button>
                            </p>
        
                            @if (session('status') === 'verification-link-sent')
                                <p class="mt-2 font-medium text-sm text-green-600">
                                    {{ __('A new verification link has been sent to your email address.') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div style="margin-top: 25px">
                    <x-primary-button class="btn submit-button">{{ __('Update') }}</x-primary-button>

                    @if (session('status') === 'profile-updated')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2000)"
                            class="text-sm text-gray-600"
                        >{{ __('Saved.') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </div>
</section>
