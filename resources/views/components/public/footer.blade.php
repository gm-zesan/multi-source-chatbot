<footer class="border-t border-light-border dark:border-dark-border bg-light-surface/60 dark:bg-dark-surface/60 backdrop-blur-md pt-16 pb-12 transition-colors duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        
        {{-- Top Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-8 lg:gap-12">
            
            {{-- Column 1: Brand Info (Spans 2 on mobile, 1 on desktop) --}}
            <div class="col-span-2 space-y-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <img src="{{ asset('images/logo/ea-app-icon.png') }}" alt="Entrepreneurs Automation" class="w-7 h-7 rounded-md object-contain shadow-xs">
                    <span class="font-bold text-sm tracking-tight text-light-text dark:text-dark-text">
                        Entrepreneurs Automation
                    </span>
                </a>
                <p class="text-xs text-light-muted dark:text-dark-muted max-w-sm leading-relaxed">
                    {{ __('public.footer.desc') }}
                </p>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-3xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <i class="ri-checkbox-circle-fill text-emerald-500 text-xs"></i>
                        <span>{{ app()->getLocale() === 'bn' ? 'সকল কোর সিস্টেম সক্রিয়' : 'All Core Systems Operational' }}</span>
                    </span>
                </div>
            </div>

            {{-- Column 2: Platform --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    {{ __('public.footer.col_platform') }}
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('platform') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.platform_overview') }}</a></li>
                    <li><a href="{{ route('unified-inbox') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.agent_sales') }}</a></li>
                    <li><a href="{{ route('automation') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.agent_ops') }}</a></li>
                    <li><a href="{{ route('business-intelligence') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.agent_cfo') }}</a></li>
                    <li><a href="{{ route('knowledge-engine') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.knowledge_engine') }}</a></li>
                </ul>
            </div>

            {{-- Column 3: Ecosystem & Integrations --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    {{ __('public.footer.col_solutions') }}
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('solutions') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.solutions') }}</a></li>
                    <li><a href="{{ route('integrations') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.integrations') }}</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.pricing') }}</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.contact') }}</a></li>
                </ul>
            </div>

            {{-- Column 4: Company & Legal --}}
            <div class="space-y-3">
                <div class="text-xs font-bold uppercase tracking-wider text-light-text dark:text-dark-text">
                    {{ __('public.footer.col_legal') }}
                </div>
                <ul class="space-y-2 text-xs text-light-muted dark:text-dark-muted">
                    <li><a href="{{ route('about') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.about') }}</a></li>
                    <li><a href="{{ route('resources') }}" class="hover:text-brand-500 transition-colors">{{ __('public.nav.resources') }}</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-brand-500 transition-colors">FAQ</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-brand-500 transition-colors">{{ __('public.footer.privacy') }}</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-brand-500 transition-colors">{{ __('public.footer.terms') }}</a></li>
                    <li><a href="{{ route('cookies') }}" class="hover:text-brand-500 transition-colors">{{ __('public.footer.cookies') }}</a></li>
                </ul>
            </div>
        </div>

        {{-- Bottom Copyright Strip --}}
        <div class="pt-8 border-t border-light-border dark:border-dark-border flex flex-col sm:flex-row items-center justify-between gap-4 text-3xs text-light-muted dark:text-dark-muted">
            <div class="flex items-center gap-3">
                <span>&copy; {{ date('Y') }} Entrepreneurs Automation. {{ __('public.footer.rights') }}</span>
                <span class="text-light-border dark:text-dark-border">/</span>
                <span class="font-mono">v2.0 Enterprise</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('privacy') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">{{ __('public.footer.privacy') }}</a>
                <a href="{{ route('terms') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">{{ __('public.footer.terms') }}</a>
                <a href="{{ route('cookies') }}" class="hover:text-light-text dark:hover:text-dark-text transition-colors">{{ __('public.footer.cookies') }}</a>
            </div>
        </div>
    </div>
</footer>
