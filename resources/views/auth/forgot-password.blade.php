@section('title', 'Forgot Password')

<x-guest-layout>
    <div class="rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-card p-6 sm:p-8 transition-colors duration-200">
        
        {{-- Header & Icon --}}
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 border border-brand-500/20 mb-3 shadow-xs">
                <i class="ri-lock-password-line text-2xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-light-text dark:text-dark-text">
                Reset Password
            </h1>
            <p class="text-xs sm:text-sm text-light-muted dark:text-dark-muted mt-1">
                Enter your verified work email to receive password reset instructions
            </p>
        </div>

        {{-- Session Status Alert --}}
        @if (session('status'))
            <div class="mb-5 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                <i class="ri-checkbox-circle-fill text-sm flex-shrink-0"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4" id="forgotPasswordForm">
            @csrf

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
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
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

            {{-- Submit Button --}}
            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center gap-2 group cursor-pointer" id="resetSubmitBtn">
                    <span>Email Password Reset Link</span>
                    <i class="ri-mail-send-line text-sm group-hover:translate-x-0.5 transition-transform"></i>
                </button>
            </div>
        </form>

        {{-- Back to Sign In --}}
        <div class="mt-6 pt-5 border-t border-light-border dark:border-dark-border text-center text-xs text-light-muted dark:text-dark-muted">
            Remembered your credentials? 
            <a href="{{ route('login') }}" class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                Back to Sign In
            </a>
        </div>
    </div>
</x-guest-layout>
