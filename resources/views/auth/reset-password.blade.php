@section('title', 'Set New Password')

<x-guest-layout>
    <div class="rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-card p-6 sm:p-8 transition-colors duration-200">
        
        {{-- Header & Icon --}}
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 border border-brand-500/20 mb-3 shadow-xs">
                <i class="ri-lock-password-line text-2xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-light-text dark:text-dark-text">
                Set New Password
            </h1>
            <p class="text-xs sm:text-sm text-light-muted dark:text-dark-muted mt-1">
                Choose a strong replacement password for your account
            </p>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            {{-- Email Address --}}
            <div>
                <label for="email" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                    Email Address <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-light-muted dark:text-dark-muted">
                        <i class="ri-mail-line text-sm"></i>
                    </div>
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        value="{{ old('email', $request->email) }}" 
                        required 
                        autofocus 
                        autocomplete="username" 
                        placeholder="name@company.com" 
                        class="w-full pl-10 pr-3.5 py-2.5 rounded-xl text-xs sm:text-sm bg-light-canvas dark:bg-dark-canvas border @error('email') border-rose-500 focus:border-rose-500 focus:ring-rose-500/20 @else border-light-border dark:border-dark-border focus:border-brand-500 focus:ring-brand-500/20 @enderror text-light-text dark:text-dark-text placeholder:text-light-muted/60 dark:placeholder:text-dark-muted/60 focus:outline-none focus:ring-2 transition-colors duration-150"
                    >
                </div>
                @error('email')
                    <div class="flex items-center gap-1 text-3xs text-rose-500 mt-1.5 font-medium">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                    New Password <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-light-muted dark:text-dark-muted">
                        <i class="ri-lock-2-line text-sm"></i>
                    </div>
                    <input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="new-password" 
                        placeholder="••••••••" 
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl text-xs sm:text-sm bg-light-canvas dark:bg-dark-canvas border @error('password') border-rose-500 focus:border-rose-500 focus:ring-rose-500/20 @else border-light-border dark:border-dark-border focus:border-brand-500 focus:ring-brand-500/20 @enderror text-light-text dark:text-dark-text placeholder:text-light-muted/60 dark:placeholder:text-dark-muted/60 focus:outline-none focus:ring-2 transition-colors duration-150"
                    >
                    <button type="button" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-colors" id="togglePassword" aria-label="Toggle password visibility" tabindex="-1">
                        <i class="ri-eye-line text-sm" id="togglePasswordIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="flex items-center gap-1 text-3xs text-rose-500 mt-1.5 font-medium">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                    Confirm Password <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-light-muted dark:text-dark-muted">
                        <i class="ri-lock-check-line text-sm"></i>
                    </div>
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password" 
                        placeholder="••••••••" 
                        class="w-full pl-10 pr-10 py-2.5 rounded-xl text-xs sm:text-sm bg-light-canvas dark:bg-dark-canvas border @error('password_confirmation') border-rose-500 focus:border-rose-500 focus:ring-rose-500/20 @else border-light-border dark:border-dark-border focus:border-brand-500 focus:ring-brand-500/20 @enderror text-light-text dark:text-dark-text placeholder:text-light-muted/60 dark:placeholder:text-dark-muted/60 focus:outline-none focus:ring-2 transition-colors duration-150"
                    >
                    <button type="button" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-colors" id="toggleConfirmPassword" aria-label="Toggle password confirmation visibility" tabindex="-1">
                        <i class="ri-eye-line text-sm" id="toggleConfirmPasswordIcon"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <div class="flex items-center gap-1 text-3xs text-rose-500 mt-1.5 font-medium">
                        <i class="ri-error-warning-line"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror
            </div>

            {{-- Submit Button --}}
            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center gap-2 group cursor-pointer">
                    <span>Update Password</span>
                    <i class="ri-check-line text-sm group-hover:scale-110 transition-transform"></i>
                </button>
            </div>
        </form>

        {{-- Back to Sign In --}}
        <div class="mt-6 pt-5 border-t border-light-border dark:border-dark-border text-center text-xs text-light-muted dark:text-dark-muted">
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                Back to Sign In
            </a>
        </div>
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
                                icon.className = 'ri-eye-off-line text-sm';
                            } else {
                                input.type = 'password';
                                icon.className = 'ri-eye-line text-sm';
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
