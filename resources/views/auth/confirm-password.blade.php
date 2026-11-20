@section('title', 'Confirm Password')

<x-guest-layout>
    <div class="rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-card p-6 sm:p-8 transition-colors duration-200">
        
        {{-- Header & Icon --}}
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-brand-500/10 text-brand-500 border border-brand-500/20 mb-3 shadow-xs">
                <i class="ri-shield-keyhole-line text-2xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-light-text dark:text-dark-text">
                Confirm Password
            </h1>
            <p class="text-xs sm:text-sm text-light-muted dark:text-dark-muted mt-1">
                Please confirm your password to proceed to this secure area
            </p>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
            @csrf

            {{-- Password --}}
            <div>
                <label for="password" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1.5">
                    Password <span class="text-rose-500">*</span>
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
                        autocomplete="current-password" 
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

            {{-- Submit Button --}}
            <div>
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl text-xs sm:text-sm font-semibold bg-brand-600 hover:bg-brand-500 text-white shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center gap-2 group cursor-pointer">
                    <span>Confirm Password</span>
                    <i class="ri-arrow-right-line text-sm group-hover:translate-x-0.5 transition-transform"></i>
                </button>
            </div>
        </form>
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
                            toggleIcon.className = 'ri-eye-off-line text-sm';
                        } else {
                            passwordInput.type = 'password';
                            toggleIcon.className = 'ri-eye-line text-sm';
                        }
                    });
                }
            });
        </script>
    @endpush
</x-guest-layout>
