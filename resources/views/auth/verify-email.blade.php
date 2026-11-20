@section('title', 'Verify Email')

<x-guest-layout>
    <div class="rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-card p-6 sm:p-8 transition-colors duration-200">
        
        {{-- Header & Icon --}}
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 border border-brand-500/20 mb-3 shadow-xs">
                <i class="ri-mail-check-line text-2xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-light-text dark:text-dark-text">
                Verify Your Email
            </h1>
            <p class="text-xs sm:text-sm text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                Thanks for signing up! Please verify your email address by clicking on the link we just sent to your inbox.
            </p>
        </div>

        {{-- Verification Status --}}
        @if (session('status') == 'verification-link-sent')
            <div class="mb-5 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
                <i class="ri-checkbox-circle-fill text-sm flex-shrink-0"></i>
                <span>A new verification link has been sent to your email address.</span>
            </div>
        @endif

        <div class="space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center gap-2 group cursor-pointer">
                    <span>Resend Verification Email</span>
                    <i class="ri-mail-send-line text-sm group-hover:translate-x-0.5 transition-transform"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="text-center">
                @csrf
                <button type="submit" class="text-xs text-light-muted dark:text-dark-muted hover:text-rose-500 transition-colors">
                    Log Out
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
